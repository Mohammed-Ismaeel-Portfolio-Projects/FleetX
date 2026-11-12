/**
 * C5.9 — Client Expo Web critical journey (Playwright).
 *
 * Extends the existing mobile-app M9.1 Playwright approach (no new E2E framework).
 *
 * Prerequisites:
 * - API :8000 seeded via `php artisan fleetx:c59-seed`
 * - Client Expo web (default http://localhost:8092)
 * - Chromium available to Playwright (`npx playwright install chromium` from mobile-app/)
 *
 * Env:
 *   C59_CLIENT_WEB / FLEETX_CLIENT_WEB
 *   C59_API_BASE / FLEETX_API_BASE
 *   C59_CLIENT_EMAIL / C59_CLIENT_PASSWORD
 *   C59_FIXTURE_PATH
 *   C59_START_WEB=1  — attempt to start `expo start --web --port 8092`
 */

import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import fs from 'node:fs';
import { createRequire } from 'node:module';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const clientRoot = path.join(__dirname, '..');
const mobileRoot = path.join(clientRoot, '..');
const repoRoot = path.join(clientRoot, '..', '..');

const requireFromMobile = createRequire(path.join(mobileRoot, 'package.json'));
let chromium;
try {
  ({ chromium } = requireFromMobile('playwright'));
} catch {
  console.error(
    'FAIL  Playwright not found. From mobile-app/: npm install && npx playwright install chromium'
  );
  process.exit(1);
}

const CLIENT =
  process.env.C59_CLIENT_WEB || process.env.FLEETX_CLIENT_WEB || 'http://127.0.0.1:8092';
const API = process.env.C59_API_BASE || process.env.FLEETX_API_BASE || 'http://localhost:8000/api/v1';
const EMAIL = process.env.C59_CLIENT_EMAIL || 'client@fleetx.com';
const PASSWORD = process.env.C59_CLIENT_PASSWORD || 'Client@123';
const FIXTURE_PATH =
  process.env.C59_FIXTURE_PATH ||
  path.join(repoRoot, 'backend', 'storage', 'app', 'c59-client-e2e-fixture.json');

function ok(m) {
  console.log('PASS ', m);
}
function fail(m) {
  console.error('FAIL ', m);
  process.exit(1);
}

async function waitForTestId(page, testId, timeout = 45000) {
  const loc = page.getByTestId(testId).last();
  await loc.waitFor({ state: 'visible', timeout });
  return loc;
}

async function urlReachable(url, timeoutMs = 8000) {
  const candidates = [url];
  if (url.includes('localhost')) {
    candidates.push(url.replace('localhost', '127.0.0.1'));
  }
  for (const candidate of candidates) {
    const ctrl = new AbortController();
    const t = setTimeout(() => ctrl.abort(), timeoutMs);
    try {
      const res = await fetch(candidate, { signal: ctrl.signal });
      if (res.ok || res.status < 500) return true;
    } catch {
      // try next candidate
    } finally {
      clearTimeout(t);
    }
  }
  return false;
}

async function ensureClientWeb() {
  if (await urlReachable(CLIENT)) {
    ok(`Client web already up at ${CLIENT}`);
    return null;
  }
  if (process.env.C59_START_WEB !== '1') {
    fail(
      `Client web not reachable at ${CLIENT}. Start Expo web on :8092 or set C59_START_WEB=1`
    );
  }

  ok('Starting Expo web on port 8092…');
  const child = spawn(
    'npx expo start --web --port 8092',
    {
      cwd: clientRoot,
      env: { ...process.env, BROWSER: 'none' },
      stdio: 'ignore',
      shell: true,
      detached: process.platform !== 'win32',
    }
  );

  const started = Date.now();
  while (Date.now() - started < 180000) {
    if (await urlReachable(CLIENT, 3000)) {
      ok('Expo web became reachable');
      return child;
    }
    await new Promise((r) => setTimeout(r, 2000));
  }
  child.kill();
  fail('Timed out waiting for Expo web on :8092');
}

function loadFixture() {
  if (!fs.existsSync(FIXTURE_PATH)) {
    fail(`Fixture missing: ${FIXTURE_PATH}. Run php artisan fleetx:c59-seed`);
  }
  return JSON.parse(fs.readFileSync(FIXTURE_PATH, 'utf8'));
}

async function main() {
  console.log('=== C5.9 B — Expo Web critical journey ===\n');
  const fixture = loadFixture();
  const webProc = await ensureClientWeb();

  const browser = await chromium.launch({
    headless: true,
    // Prefer installed Chrome (same as M9.1) to avoid downloading Playwright browsers.
    channel: process.env.PLAYWRIGHT_CHANNEL || 'chrome',
  });

  try {
    const context = await browser.newContext({
      viewport: { width: 390, height: 844 },
    });
    const page = await context.newPage();
    const pageErrors = [];
    page.on('pageerror', (err) => pageErrors.push(String(err)));

    await page.goto(CLIENT, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.evaluate(() => {
      try {
        localStorage.clear();
      } catch {
        // ignore
      }
    });
    await page.reload({ waitUntil: 'domcontentloaded', timeout: 90000 });

    // Auth bootstrap → Login
    await waitForTestId(page, 'login-email', 90000);
    await waitForTestId(page, 'login-password');
    await waitForTestId(page, 'login-submit');
    ok('Launch → login screen');

    await page.getByTestId('login-email').fill(EMAIL);
    await page.getByTestId('login-password').fill('wrong-password');
    await page.getByTestId('login-submit').click();
    await waitForTestId(page, 'login-error', 20000);
    ok('Wrong credentials show login-error (no crash)');

    await page.getByTestId('login-password').fill(PASSWORD);
    const loginResponsePromise = page.waitForResponse(
      (r) => r.url().includes('/auth/login') && r.request().method() === 'POST',
      { timeout: 30000 }
    );
    await page.getByTestId('login-submit').click();
    const loginRes = await loginResponsePromise;
    if (loginRes.status() !== 200) {
      const body = await loginRes.text().catch(() => '');
      fail(`Login HTTP ${loginRes.status()} ${body.slice(0, 180)}`);
    }
    await waitForTestId(page, 'home-title', 60000);
    await waitForTestId(page, 'home-identity');
    ok('Login → Home');

    // Home section resilience — wait for shipments section to leave pure loading.
    await waitForTestId(page, 'home-active-shipments', 45000);
    await page
      .locator(
        [
          '[data-testid="home-shipments-empty"]',
          '[data-testid="home-shipments-error"]',
          '[data-testid^="home-shipment-"]',
          '[data-testid="home-empty"]',
        ].join(', ')
      )
      .first()
      .waitFor({ state: 'visible', timeout: 45000 });
    ok('Home sections settled without blank crash');

    // Requests (deep-link — Expo web tabs can overlay)
    await page.goto(`${CLIENT}/requests`, { waitUntil: 'domcontentloaded' });
    await waitForTestId(page, 'request-filters', 45000);
    ok('Requests list');

    await page.goto(`${CLIENT}/request-details/${fixture.request_id}`, {
      waitUntil: 'domcontentloaded',
    });
    await waitForTestId(page, 'request-details', 45000);
    ok('Request details');

    // Shipments → workspace/details → tracking
    await page.goto(`${CLIENT}/shipments`, { waitUntil: 'domcontentloaded' });
    await waitForTestId(page, 'shipment-screen', 45000);
    ok('Shipment list');

    await page.goto(`${CLIENT}/shipment-details/${fixture.shipment_id}`, {
      waitUntil: 'domcontentloaded',
    });
    await waitForTestId(page, 'shipment-details-screen', 45000);
    ok('Shipment workspace / details');

    // Nonexistent shipment
    await page.goto(`${CLIENT}/shipment-details/00000000-0000-4000-8000-000000000099`, {
      waitUntil: 'domcontentloaded',
    });
    await Promise.race([
      waitForTestId(page, 'shipment-details-error', 30000),
      waitForTestId(page, 'shipment-details-not-found', 30000),
      waitForTestId(page, 'shipment-details-forbidden', 30000),
    ]).catch(() => fail('Nonexistent shipment did not show error/not-found state'));
    const retry404 = page.getByTestId('shipment-details-retry');
    if ((await retry404.count()) > 0 && (await retry404.isVisible().catch(() => false))) {
      fail('404 shipment detail exposed Retry (C5.6 violation)');
    }
    ok('Nonexistent shipment fail-closed UI');

    // Unauthorized / foreign shipment
    await page.goto(`${CLIENT}/shipment-details/${fixture.foreign_shipment_id}`, {
      waitUntil: 'domcontentloaded',
    });
    await Promise.race([
      waitForTestId(page, 'shipment-details-error', 30000),
      waitForTestId(page, 'shipment-details-not-found', 30000),
      waitForTestId(page, 'shipment-details-forbidden', 30000),
    ]).catch(() => fail('Foreign shipment did not deny in UI'));
    ok('Foreign shipment denied in UI');

    // Tracking + journey map
    await page.goto(`${CLIENT}/shipment-tracking/${fixture.shipment_id}`, {
      waitUntil: 'domcontentloaded',
    });
    await waitForTestId(page, 'shipment-progress-screen', 60000);
    await waitForTestId(page, 'shipment-progress-map', 45000);
    ok('Tracking screen + journey map mounted');

    const mapText = (
      await page.getByTestId('shipment-progress-screen').innerText()
    ).toLowerCase();
    if (/(^|[^a-z])eta([^a-z]|$)/i.test(mapText)) {
      fail('Tracking UI leaked ETA terminology');
    }
    if (mapText.includes('driver_id') || mapText.includes('vehicle_id') || mapText.includes('live-fleet')) {
      fail('Tracking UI leaked ops identifiers');
    }
    // Raw coordinate pairs like 52.520008 should not render to customers.
    if (/-?\d+\.\d{4,}/.test(mapText)) {
      fail('Tracking UI appears to show raw coordinates');
    }
    ok('Tracking forbidden-field / raw-coord UI audit');

    // Map should expose one of: markers, route-missing, unavailable, empty — never crash
    const mapModes =
      (await page.getByTestId('shipment-progress-map-canvas').count()) +
      (await page.getByTestId('shipment-progress-map-route-missing').count()) +
      (await page.getByTestId('shipment-progress-map-unavailable').count()) +
      (await page.getByTestId('shipment-progress-map-empty').count()) +
      (await page.locator('[data-testid^="shipment-progress-map-marker-"]').count());
    assert.ok(mapModes > 0, 'Map rendered no known geo mode surface');
    ok('Map geo surface present (full/hubs/locationOnly/none compatible)');

    // Notifications
    await page.goto(`${CLIENT}/notifications`, { waitUntil: 'domcontentloaded' });
    await waitForTestId(page, 'notifications-screen', 45000);
    await Promise.race([
      waitForTestId(page, 'notifications-list', 45000),
      waitForTestId(page, 'notifications-empty', 45000),
    ]);
    ok('Notifications');

    // Messages → conversation
    await page.goto(`${CLIENT}/messages`, { waitUntil: 'domcontentloaded' });
    await waitForTestId(page, 'messages-screen', 45000);
    const row = page.locator(`[data-testid="conversation-row-${fixture.conversation_id}"]`);
    if ((await row.count()) === 0) {
      // Fall back to any row from seed
      const any = page.locator('[data-testid^="conversation-row-"]');
      assert.ok((await any.count()) > 0, 'No conversations — re-run fleetx:c59-seed');
      await any.first().click();
    } else {
      await row.first().click();
    }
    await waitForTestId(page, 'conversation-screen', 45000);
    await waitForTestId(page, 'composer-input');
    const body = `C59 UI smoke ${Date.now()}`;
    await page.getByTestId('composer-input').fill(body);
    const sendPromise = page.waitForResponse(
      (r) =>
        r.url().includes('/conversations/') &&
        r.url().includes('/messages') &&
        r.request().method() === 'POST',
      { timeout: 30000 }
    );
    await page.getByTestId('composer-send').click();
    const sendRes = await sendPromise;
    assert.equal(sendRes.status(), 201);
    ok('Messages → conversation → send');

    // Profile → logout
    await page.goto(`${CLIENT}/profile`, { waitUntil: 'domcontentloaded' });
    await waitForTestId(page, 'profile-screen', 45000);
    await waitForTestId(page, 'profile-logout');
    ok('Profile');

    await page.getByTestId('profile-logout').click();
    await waitForTestId(page, 'login-email', 45000);
    ok('Logout → login');

    // Protected route rejection after logout
    await page.goto(`${CLIENT}/shipments`, { waitUntil: 'domcontentloaded' });
    await waitForTestId(page, 'login-email', 45000);
    ok('Protected route rejected after logout');

    if (pageErrors.length) {
      fail(`JS page errors: ${pageErrors.slice(0, 3).join(' | ')}`);
    }
    ok('No pageerror exceptions during journey');

    // API health still referenced for report
    const health = await fetch(`${API}/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ email: EMAIL, password: 'x' }),
    });
    assert.ok(health.status === 422 || health.status === 401);
    ok(`API still responding (${API})`);

    console.log('\nC5.9 Web journey VERDICT: PASS');
  } finally {
    await browser.close().catch(() => {});
    if (webProc && !webProc.killed) {
      webProc.kill();
    }
  }
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
