/**
 * M8 Expo web smoke: login + identity + conversation list via same API the apps use,
 * and confirm Expo web servers are up.
 *
 * Usage: node scripts/verify-m8-expo-web-smoke.mjs
 */

const DRIVER_WEB = process.env.FLEETX_DRIVER_WEB || 'http://localhost:8081';
const CLIENT_WEB = process.env.FLEETX_CLIENT_WEB || 'http://localhost:8082';
const API = process.env.FLEETX_API_BASE || 'http://localhost:8000/api/v1';

function fail(m) {
  console.error('FAIL:', m);
  process.exit(1);
}
function ok(m) {
  console.log('OK:', m);
}

async function checkWeb(url, label) {
  const res = await fetch(url);
  if (!res.ok) fail(`${label} web ${res.status}`);
  const text = await res.text();
  if (!text || text.length < 20) fail(`${label} web empty`);
  ok(`${label} Expo web reachable (${res.status}, ${text.length} bytes)`);
}

async function login(email, password) {
  const res = await fetch(`${API}/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ email, password }),
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok) fail(`login ${email}: ${res.status}`);
  return json.data.access_token;
}

async function api(token, path) {
  const res = await fetch(`${API}${path}`, {
    headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
  });
  const json = await res.json().catch(() => ({}));
  return { res, json };
}

async function main() {
  await checkWeb(DRIVER_WEB, 'Driver');
  await checkWeb(CLIENT_WEB, 'Customer');

  const driverToken = await login('driver@fleetx.com', 'Driver@123');
  const clientToken = await login('client@fleetx.com', 'Client@123');

  const dMe = await api(driverToken, '/auth/me');
  const dId = await api(driverToken, '/auth/identity');
  if (!dMe.res.ok || !dId.res.ok) fail('driver me/identity');
  if (dMe.json?.data?.role?.name !== 'driver') fail('driver role gate');
  if (!dId.json?.data?.linked) fail('driver linked');
  ok(`Driver me+identity User=${dId.json.data.user_id}`);

  const cMe = await api(clientToken, '/auth/me');
  const cId = await api(clientToken, '/auth/identity');
  if (!cMe.res.ok || !cId.res.ok) fail('client me/identity');
  if (cMe.json?.data?.role?.name !== 'client') fail('client role gate');
  if (!cId.json?.data?.linked || !cId.json?.data?.company?.id) fail('client company');
  ok(`Customer me+identity Company=${cId.json.data.company.id}`);

  const dList = await api(driverToken, '/conversations?type=dispatcher_driver&per_page=5');
  if (!dList.res.ok) fail(`driver list ${dList.res.status}`);
  ok(`Driver conversations=${(dList.json?.data?.data || []).length}`);

  const cList = await api(clientToken, '/conversations?type=dispatcher_customer&per_page=5');
  if (!cList.res.ok) fail(`client list ${cList.res.status}`);
  ok(`Customer conversations=${(cList.json?.data?.data || []).length}`);

  ok('M8 Expo web servers up + auth/messaging contract for UI paths');
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
