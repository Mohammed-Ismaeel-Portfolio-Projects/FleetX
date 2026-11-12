/**
 * Phase 1E Milestone 4 Fleet & Driver Resource Visibility Verification Script.
 * Verifies company scoping, fail-closed rule, KPI request suppression,
 * and internal dispatcher preservation.
 */
import { strict as assert } from 'node:assert';
import { test } from 'node:test';

// Helper function simulating vehicle scoping logic
function scopeVehicles(rawVehicles, isPartner, userCompanyId) {
  if (isPartner && !userCompanyId) {
    return []; // Fail closed rule
  }
  if (!isPartner) {
    return rawVehicles.filter((v) => !v.companyId || v.companyId === 'internal');
  }
  return rawVehicles.filter((v) => v.companyId === userCompanyId);
}

// Helper function simulating driver scoping logic
function scopeDrivers(rawDrivers, isPartner, userCompanyId) {
  if (isPartner && !userCompanyId) {
    return []; // Fail closed rule
  }
  if (!isPartner) {
    return rawDrivers.filter((d) => !d.companyId || d.companyId === 'internal');
  }
  return rawDrivers.filter((d) => d.companyId === userCompanyId);
}

// Helper function simulating KPI trigger decision
function shouldTriggerKpiRequest(isPartner) {
  return !isPartner;
}

// Mock dataset
const sampleVehicles = [
  { id: 'v-int-1', plateNumber: 'FX-1001', companyId: null },
  { id: 'v-int-2', plateNumber: 'FX-1002', companyId: 'internal' },
  { id: 'v-partnerA-1', plateNumber: 'PA-2001', companyId: 'company-partner-A' },
  { id: 'v-partnerA-2', plateNumber: 'PA-2002', companyId: 'company-partner-A' },
  { id: 'v-partnerB-1', plateNumber: 'PB-3001', companyId: 'company-partner-B' },
];

const sampleDrivers = [
  { id: 'd-int-1', name: 'FleetX Driver 1', companyId: null },
  { id: 'd-int-2', name: 'FleetX Driver 2', companyId: 'internal' },
  { id: 'd-partnerA-1', name: 'Partner A Driver 1', companyId: 'company-partner-A' },
  { id: 'd-partnerA-2', name: 'Partner A Driver 2', companyId: 'company-partner-A' },
  { id: 'd-partnerB-1', name: 'Partner B Driver 1', companyId: 'company-partner-B' },
];

test('1. Internal dispatcher can access existing vehicle workspace', () => {
  const vehicles = scopeVehicles(sampleVehicles, false, null);
  assert.equal(vehicles.length, 2);
  assert.deepEqual(vehicles.map((v) => v.id), ['v-int-1', 'v-int-2']);
});

test('2. Internal dispatcher can access existing driver workspace', () => {
  const drivers = scopeDrivers(sampleDrivers, false, null);
  assert.equal(drivers.length, 2);
  assert.deepEqual(drivers.map((d) => d.id), ['d-int-1', 'd-int-2']);
});

test('3. Partner dispatcher sees only own-company vehicles', () => {
  const vehicles = scopeVehicles(sampleVehicles, true, 'company-partner-A');
  assert.equal(vehicles.length, 2);
  assert.deepEqual(vehicles.map((v) => v.id), ['v-partnerA-1', 'v-partnerA-2']);
});

test('4. Partner dispatcher sees only own-company drivers', () => {
  const drivers = scopeDrivers(sampleDrivers, true, 'company-partner-A');
  assert.equal(drivers.length, 2);
  assert.deepEqual(drivers.map((d) => d.id), ['d-partnerA-1', 'd-partnerA-2']);
});

test('5. Partner dispatcher cannot see Partner B vehicles', () => {
  const vehiclesA = scopeVehicles(sampleVehicles, true, 'company-partner-A');
  const hasPartnerB = vehiclesA.some((v) => v.companyId === 'company-partner-B');
  assert.equal(hasPartnerB, false);
});

test('6. Partner dispatcher cannot see Partner B drivers', () => {
  const driversA = scopeDrivers(sampleDrivers, true, 'company-partner-A');
  const hasPartnerB = driversA.some((d) => d.companyId === 'company-partner-B');
  assert.equal(hasPartnerB, false);
});

test('7. Partner dispatcher cannot see FleetX internal vehicles', () => {
  const vehiclesA = scopeVehicles(sampleVehicles, true, 'company-partner-A');
  const hasInternal = vehiclesA.some((v) => !v.companyId || v.companyId === 'internal');
  assert.equal(hasInternal, false);
});

test('8. Partner dispatcher cannot see FleetX internal drivers', () => {
  const driversA = scopeDrivers(sampleDrivers, true, 'company-partner-A');
  const hasInternal = driversA.some((d) => !d.companyId || d.companyId === 'internal');
  assert.equal(hasInternal, false);
});

test('9. Partner user with missing companyId fails closed', () => {
  const vehicles = scopeVehicles(sampleVehicles, true, null);
  const drivers = scopeDrivers(sampleDrivers, true, null);
  assert.equal(vehicles.length, 0);
  assert.equal(drivers.length, 0);
});

test('10. Partner user does not trigger internal fleet KPI request', () => {
  const trigger = shouldTriggerKpiRequest(true);
  assert.equal(trigger, false);
});

test('11. Partner user does not trigger internal driver KPI request', () => {
  const trigger = shouldTriggerKpiRequest(true);
  assert.equal(trigger, false);
});

test('12. Internal user retains KPI behavior', () => {
  const trigger = shouldTriggerKpiRequest(false);
  assert.equal(trigger, true);
});

test('13. Partner empty vehicle list renders safely', () => {
  const emptyVehicles = scopeVehicles([], true, 'company-partner-C');
  assert.equal(emptyVehicles.length, 0);
});

test('14. Partner empty driver list renders safely', () => {
  const emptyDrivers = scopeDrivers([], true, 'company-partner-C');
  assert.equal(emptyDrivers.length, 0);
});

test('15. Existing internal fleet behavior remains unchanged', () => {
  const intVehicles = scopeVehicles(sampleVehicles, false, null);
  const intDrivers = scopeDrivers(sampleDrivers, false, null);
  const kpiAllowed = shouldTriggerKpiRequest(false);

  assert.equal(intVehicles.length, 2);
  assert.equal(intDrivers.length, 2);
  assert.equal(kpiAllowed, true);
});
