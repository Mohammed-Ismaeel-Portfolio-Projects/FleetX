/**
 * Focused behavioral tests for C2.9-C2.3 Dispatcher Request Intake mappers.
 * Mirrors requestMapper.ts contracts without a Vite test runner.
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

function formatRoute(pickupCity, pickupCountry, deliveryCity, deliveryCountry) {
  const origin = [pickupCity, pickupCountry].filter(Boolean).join(', ') || 'Unavailable';
  const destination = [deliveryCity, deliveryCountry].filter(Boolean).join(', ') || 'Unavailable';
  return `${origin} → ${destination}`;
}

function mapApiRequestToListItem(api) {
  return {
    id: api.id,
    requestNumber: api.request_number,
    referenceNumber: api.reference_number ?? null,
    customer: api.customer_company
      ? {
          id: api.customer_company.id,
          companyName: api.customer_company.company_name,
          companyCode: api.customer_company.company_code,
        }
      : null,
    status: api.status?.name || 'Unavailable',
    routeLabel: formatRoute(api.pickup_city, api.pickup_country, api.delivery_city, api.delivery_country),
    priority: api.priority ?? null,
    createdAt: api.created_at ?? null,
  };
}

test('mapper renders request identity customer status and route from API contract', () => {
  const mapped = mapApiRequestToListItem({
    id: 'req-1',
    request_number: 'SR-1001',
    reference_number: 'PO-9',
    customer_company: {
      id: 'co-1',
      company_name: 'Acme Logistics',
      company_code: 'ACME',
    },
    status_id: 2,
    status: { id: 2, name: 'submitted', type: 'shipment_order' },
    pickup_city: 'Warsaw',
    pickup_country: 'PL',
    delivery_city: 'Krakow',
    delivery_country: 'PL',
    priority: 'urgent',
    created_at: '2026-08-12 10:00:00',
  });

  assert.equal(mapped.requestNumber, 'SR-1001');
  assert.equal(mapped.customer.companyName, 'Acme Logistics');
  assert.equal(mapped.status, 'submitted');
  assert.equal(mapped.routeLabel, 'Warsaw, PL → Krakow, PL');
  assert.equal(mapped.priority, 'urgent');
});

test('mapper does not invent missing customer or status fields', () => {
  const mapped = mapApiRequestToListItem({
    id: 'req-2',
    request_number: 'SR-1002',
    status_id: 1,
  });
  assert.equal(mapped.customer, null);
  assert.equal(mapped.status, 'Unavailable');
  assert.equal(mapped.referenceNumber, null);
});

test('request intake list and show paths remain present', () => {
  const service = read('src/features/dispatcher/requests/services/shipmentRequests.service.ts');
  assert.match(service, /\/shipment-requests/);
  assert.match(service, /getRequests/);
  assert.match(service, /getRequestById/);
});

test('Requests page exposes loading empty error filters and detail navigation', () => {
  const page = read('src/features/dispatcher/requests/pages/RequestsPage.tsx');
  assert.match(page, /LoadingState/);
  assert.match(page, /EmptyState/);
  assert.match(page, /ErrorState/);
  assert.match(page, /Request status|statusFilter/);
  assert.match(page, /requested_pickup_date_from|pickupFrom/);
  assert.match(page, /\/dispatcher\/requests\/\$\{request\.id\}/);
  assert.equal(/\/approve|\/reject|\/convert|\.post\(|\.put\(|\.patch\(/i.test(page), false);
});

test('Request detail page remains review foundation with conversion panel handoff', () => {
  const page = read('src/features/dispatcher/requests/pages/RequestDetailsPage.tsx');
  assert.match(page, /Request Review|Review workspace|getRequestById|useShipmentRequestDetail/);
  assert.match(page, /RequestConversionPanel|RequestDecisionPanel/);
  // Page wires panels; it does not inline the convert endpoint path.
  assert.equal(/\/shipment-requests\/\$\{.*\}\/convert/i.test(page), false);
});

test('Dispatcher route protection still wraps requests routes', () => {
  const app = read('src/app/App.tsx');
  assert.match(app, /path="\/dispatcher\/\*"/);
  assert.match(app, /allowedRoles=\{\['dispatcher'\]\}/);
  assert.match(app, /path="requests"/);
  assert.match(app, /path="requests\/:id"/);
  assert.match(app, /RequestsPage/);
  assert.match(app, /RequestDetailsPage/);
});

test('existing Dispatcher pages remain registered', () => {
  const app = read('src/app/App.tsx');
  assert.match(app, /ShipmentsPage/);
  assert.match(app, /ActiveTrackingPage/);
  assert.match(app, /DriverManagementPage/);
  assert.match(app, /FleetManagementPage/);
});
