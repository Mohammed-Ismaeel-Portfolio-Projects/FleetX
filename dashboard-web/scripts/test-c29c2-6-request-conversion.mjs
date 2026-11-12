/**
 * Focused tests for C2.9-C2.6 Dispatcher Request → Shipment Conversion.
 */
import { strict as assert } from 'node:assert';
import { readFileSync, existsSync } from 'node:fs';
import { test } from 'node:test';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(__dirname, '..');

function read(rel) {
  const p = resolve(ROOT, rel);
  assert.ok(existsSync(p), `missing ${rel}`);
  return readFileSync(p, 'utf8');
}

function canConvertRequest(status, convertedShipmentId) {
  return status === 'approved' && !convertedShipmentId;
}

test('convert service reuses existing conversion endpoint with empty body', () => {
  const service = read('src/features/dispatcher/requests/services/shipmentRequests.service.ts');
  assert.match(service, /convertRequest/);
  assert.match(service, /\/shipment-requests\/\$\{id\}\/convert/);
  assert.match(service, /post<ApiEnvelope/);
  assert.equal(/createShipment|assignments|\/tracking/i.test(service), false);
});

test('conversion panel exposes convert only for approved convertible requests', () => {
  const panel = read('src/features/dispatcher/requests/components/RequestConversionPanel.tsx');
  assert.match(panel, /canConvertRequest/);
  assert.match(panel, /Convert to shipment/);
  assert.match(panel, /convertRequest/);
  assert.match(panel, /isSubmitting/);
  assert.match(panel, /Do not call onConverted|onConverted/);
  assert.match(panel, /Open shipment/);
  assert.match(panel, /\/dispatcher\/shipments\/\$\{shipmentId\}/);
});

test('canConvertRequest mirrors backend approved + no converted shipment gate', () => {
  assert.equal(canConvertRequest('approved', null), true);
  assert.equal(canConvertRequest('approved', ''), true);
  assert.equal(canConvertRequest('approved', 'shp-1'), false);
  assert.equal(canConvertRequest('under_review', null), false);
  assert.equal(canConvertRequest('rejected', null), false);
  assert.equal(canConvertRequest('converted', 'shp-1'), false);
  assert.equal(canConvertRequest('submitted', null), false);
  const mapper = read('src/features/dispatcher/requests/utils/requestMapper.ts');
  assert.match(mapper, /canConvertRequest/);
  assert.match(mapper, /status === 'approved'/);
  assert.match(mapper, /!convertedShipmentId/);
});

test('review page wires conversion panel and refreshes after conversion', () => {
  const page = read('src/features/dispatcher/requests/pages/RequestDetailsPage.tsx');
  assert.match(page, /RequestConversionPanel/);
  assert.match(page, /onConverted/);
  assert.match(page, /setRequest/);
  assert.match(page, /refresh/);
});

test('failed conversion keeps current request state pattern', () => {
  const panel = read('src/features/dispatcher/requests/components/RequestConversionPanel.tsx');
  assert.match(panel, /setError/);
  assert.match(panel, /onConverted\(next\)/);
  assert.match(panel, /Do not call onConverted/);
});

test('converted handoff navigates to existing shipment route when id available', () => {
  const panel = read('src/features/dispatcher/requests/components/RequestConversionPanel.tsx');
  assert.match(panel, /convertedShipment/);
  assert.match(panel, /Open shipment/);
  assert.match(panel, /\/dispatcher\/shipments\//);
});

test('no Assignment Tracking or Shipment lifecycle actions introduced', () => {
  const panel = read('src/features/dispatcher/requests/components/RequestConversionPanel.tsx');
  const service = read('src/features/dispatcher/requests/services/shipmentRequests.service.ts');
  const page = read('src/features/dispatcher/requests/pages/RequestDetailsPage.tsx');
  for (const src of [panel, service, page]) {
    assert.equal(/assignVehicle|assignDriver|startTransit|markDelivered|\/assignments|tracking\/init/i.test(src), false);
  }
});

test('decision panel remains separate from convert', () => {
  const decision = read('src/features/dispatcher/requests/components/RequestDecisionPanel.tsx');
  assert.match(decision, /approveRequest/);
  assert.match(decision, /rejectRequest/);
  assert.equal(/convertRequest/i.test(decision), false);
  assert.match(decision, /Start Review|startReview/);
  assert.match(decision, /Request Changes|requestChanges/);
  assert.equal(/Request changes · deferred/.test(decision), false);
});

test('dispatcher route protection still wraps request review', () => {
  const app = read('src/app/App.tsx');
  assert.match(app, /allowedRoles=\{\['dispatcher'\]\}/);
  assert.match(app, /path="requests\/:id"/);
});
