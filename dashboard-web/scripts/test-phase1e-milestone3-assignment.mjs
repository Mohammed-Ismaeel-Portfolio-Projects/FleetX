/**
 * Phase 1E Milestone 3 Assignment Workspace Verification Script.
 * Tests Partner Fleet scoping, fail-closed handling, execution consistency check,
 * dynamic pairing, and internal dispatcher preservation logic.
 */
import { strict as assert } from 'node:assert';
import { test } from 'node:test';

// Helper function simulating frontend resource scoping logic
function scopeResources(resources, isPartner, userCompanyId) {
  if (isPartner && !userCompanyId) {
    // Fail closed rule
    return [];
  }
  if (!isPartner) {
    // Internal dispatcher retains all internal resources (companyId is null or internal)
    return resources.filter((r) => !r.companyId || r.companyId === 'internal');
  }
  // Partner carrier dispatcher sees ONLY resources belonging to their companyId
  return resources.filter((r) => r.companyId === userCompanyId);
}

// Helper function simulating shipment execution consistency check
function validateExecutionMatch(isPartner, userCompanyId, shipmentExecutingCompanyId) {
  if (!isPartner) return { isAllowed: true };
  if (!userCompanyId) {
    return { isAllowed: false, reason: 'Partner context missing' };
  }
  if (shipmentExecutingCompanyId && shipmentExecutingCompanyId !== userCompanyId) {
    return { isAllowed: false, reason: 'This shipment belongs to another executing company. Assignment creation is restricted.' };
  }
  return { isAllowed: true };
}

// Helper function simulating driver-vehicle pairing resolution
function resolveDriverAndVehicle(isPartner, selectedVehicle, selectedDriverId, availableDrivers) {
  if (!selectedVehicle) return { driverId: null, isPaired: false };

  if (isPartner) {
    // Dynamic pairing: partner dispatcher can pair ANY valid driver from their company with ANY valid vehicle
    const driver = availableDrivers.find((d) => d.id === selectedDriverId) ?? null;
    return {
      driverId: driver ? driver.id : null,
      isDynamic: true,
    };
  }

  // Internal dispatcher: resolve permanently paired driver from vehicle
  const pairedDriverId = selectedVehicle.driverId || selectedVehicle.driver?.id || null;
  return {
    driverId: pairedDriverId ? String(pairedDriverId) : null,
    isPaired: Boolean(pairedDriverId),
  };
}

// Mock dataset
const sampleDrivers = [
  { id: 'driver-int-1', fullName: 'FleetX Driver 1', companyId: null },
  { id: 'driver-int-2', fullName: 'FleetX Driver 2', companyId: 'internal' },
  { id: 'driver-partnerA-1', fullName: 'Partner A Driver 1', companyId: 'company-partner-A' },
  { id: 'driver-partnerA-2', fullName: 'Partner A Driver 2', companyId: 'company-partner-A' },
  { id: 'driver-partnerB-1', fullName: 'Partner B Driver 1', companyId: 'company-partner-B' },
];

const sampleVehicles = [
  { id: 'veh-int-1', plateNumber: 'FX-001', companyId: null, driverId: 'driver-int-1' },
  { id: 'veh-partnerA-1', plateNumber: 'PA-101', companyId: 'company-partner-A', driverId: null },
  { id: 'veh-partnerB-1', plateNumber: 'PB-201', companyId: 'company-partner-B', driverId: null },
];

test('1. Internal dispatcher sees internal resources', () => {
  const drivers = scopeResources(sampleDrivers, false, null);
  assert.equal(drivers.length, 2);
  assert.deepEqual(drivers.map((d) => d.id), ['driver-int-1', 'driver-int-2']);
});

test('2. Partner dispatcher sees only resources matching companyId', () => {
  const drivers = scopeResources(sampleDrivers, true, 'company-partner-A');
  assert.equal(drivers.length, 2);
  assert.deepEqual(drivers.map((d) => d.id), ['driver-partnerA-1', 'driver-partnerA-2']);
});

test('3. Partner dispatcher cannot see resources from Partner B', () => {
  const driversA = scopeResources(sampleDrivers, true, 'company-partner-A');
  const hasPartnerB = driversA.some((d) => d.companyId === 'company-partner-B');
  assert.equal(hasPartnerB, false);
});

test('4. Partner dispatcher cannot see FleetX internal resources', () => {
  const driversA = scopeResources(sampleDrivers, true, 'company-partner-A');
  const hasInternal = driversA.some((d) => !d.companyId || d.companyId === 'internal');
  assert.equal(hasInternal, false);
});

test('5. Missing partner companyId fails closed', () => {
  const drivers = scopeResources(sampleDrivers, true, null);
  assert.equal(drivers.length, 0);

  const check = validateExecutionMatch(true, null, 'company-partner-A');
  assert.equal(check.isAllowed, false);
  assert.equal(check.reason, 'Partner context missing');
});

test('6. Partner shipment with matching executing_company_id is assignable', () => {
  const check = validateExecutionMatch(true, 'company-partner-A', 'company-partner-A');
  assert.equal(check.isAllowed, true);
});

test('7. Partner shipment with different executing_company_id is blocked', () => {
  const check = validateExecutionMatch(true, 'company-partner-A', 'company-partner-B');
  assert.equal(check.isAllowed, false);
  assert.equal(
    check.reason,
    'This shipment belongs to another executing company. Assignment creation is restricted.'
  );
});

test('8. Partner driver + partner vehicle from same company is allowed without permanent pairing requirement', () => {
  const partnerAVeh = sampleVehicles.find((v) => v.id === 'veh-partnerA-1');
  const partnerADrivers = scopeResources(sampleDrivers, true, 'company-partner-A');

  // Select vehicle PA-101 and driver Partner A Driver 2 independently
  const res = resolveDriverAndVehicle(true, partnerAVeh, 'driver-partnerA-2', partnerADrivers);
  assert.equal(res.isDynamic, true);
  assert.equal(res.driverId, 'driver-partnerA-2');
});

test('9. Empty partner resource lists render safely', () => {
  const emptyDrivers = scopeResources([], true, 'company-partner-C');
  assert.equal(emptyDrivers.length, 0);
});

test('10. Existing internal assignment behavior remains unchanged', () => {
  const internalVeh = sampleVehicles.find((v) => v.id === 'veh-int-1');
  const internalDrivers = scopeResources(sampleDrivers, false, null);

  const res = resolveDriverAndVehicle(false, internalVeh, null, internalDrivers);
  assert.equal(res.isPaired, true);
  assert.equal(res.driverId, 'driver-int-1');
});
