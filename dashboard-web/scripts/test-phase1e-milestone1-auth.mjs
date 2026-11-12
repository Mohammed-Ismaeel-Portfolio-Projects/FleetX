/**
 * Phase 1E Milestone 1 Verification Script.
 * Verifies DashboardUser company context hydration and isPartnerCarrier flag mapping.
 */
import { strict as assert } from 'node:assert';
import { test } from 'node:test';
import {
  mapMeToDashboardUser,
  isPartnerCarrierCompanyType,
} from '../src/core/auth/sessionLifecycle.ts';

test('1. FleetX Internal Admin has companyId null and isPartnerCarrier false', () => {
  const mePayload = {
    id: 1,
    name: 'FleetX System Admin',
    company_id: null,
    role: { name: 'admin' },
    company: null,
  };

  const user = mapMeToDashboardUser(mePayload);
  assert.ok(user);
  assert.equal(user.id, 1);
  assert.equal(user.name, 'FleetX System Admin');
  assert.equal(user.role, 'admin');
  assert.equal(user.companyId, null);
  assert.equal(user.companyName, null);
  assert.equal(user.companyType, null);
  assert.equal(user.isPartnerCarrier, false);
});

test('2. FleetX Internal Dispatcher has isPartnerCarrier false', () => {
  const mePayload = {
    id: 2,
    name: 'Internal Ops Dispatcher',
    company_id: 'internal-company-uuid',
    role: { name: 'dispatcher' },
    company: {
      id: 'internal-company-uuid',
      company_name: 'FleetX Core Logistics',
      company_type: 'internal',
      is_active: true,
    },
  };

  const user = mapMeToDashboardUser(mePayload);
  assert.ok(user);
  assert.equal(user.role, 'dispatcher');
  assert.equal(user.companyId, 'internal-company-uuid');
  assert.equal(user.companyName, 'FleetX Core Logistics');
  assert.equal(user.companyType, 'internal');
  assert.equal(user.isPartnerCarrier, false);
});

test('3. Partner Carrier Dispatcher hydrates company context and sets isPartnerCarrier true', () => {
  const mePayload = {
    id: 10,
    name: 'Partner Ops Dispatcher',
    company_id: 'partner-carrier-uuid-999',
    role: { name: 'dispatcher' },
    company: {
      id: 'partner-carrier-uuid-999',
      company_name: 'FastFreight Express',
      company_type: 'partner_carrier',
      is_active: true,
    },
  };

  const user = mapMeToDashboardUser(mePayload);
  assert.ok(user);
  assert.equal(user.role, 'dispatcher');
  assert.equal(user.companyId, 'partner-carrier-uuid-999');
  assert.equal(user.companyName, 'FastFreight Express');
  assert.equal(user.companyType, 'partner_carrier');
  assert.equal(user.isPartnerCarrier, true);
});

test('4. Hybrid Carrier Dispatcher sets isPartnerCarrier true', () => {
  const mePayload = {
    id: 12,
    name: 'Hybrid Dispatcher',
    company_id: 'hybrid-company-uuid',
    role: { name: 'dispatcher' },
    company: {
      id: 'hybrid-company-uuid',
      company_name: 'OmniTrans Logistics',
      company_type: 'hybrid',
      is_active: true,
    },
  };

  const user = mapMeToDashboardUser(mePayload);
  assert.ok(user);
  assert.equal(user.companyType, 'hybrid');
  assert.equal(user.isPartnerCarrier, true);
});

test('5. Client User returns null (unauthorized for operations dashboard)', () => {
  const mePayload = {
    id: 50,
    name: 'B2B Client User',
    company_id: 'client-company-uuid',
    role: { name: 'client' },
    company: {
      id: 'client-company-uuid',
      company_name: 'Acme Supermarket Chain',
      company_type: 'client',
      is_active: true,
    },
  };

  const user = mapMeToDashboardUser(mePayload);
  assert.equal(user, null);
});

test('6. Company Type helper isPartnerCarrierCompanyType handles edge cases', () => {
  assert.equal(isPartnerCarrierCompanyType('partner_carrier'), true);
  assert.equal(isPartnerCarrierCompanyType('hybrid'), true);
  assert.equal(isPartnerCarrierCompanyType('internal'), false);
  assert.equal(isPartnerCarrierCompanyType('client'), false);
  assert.equal(isPartnerCarrierCompanyType(null), false);
  assert.equal(isPartnerCarrierCompanyType(undefined), false);
});
