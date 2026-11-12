/**
 * Phase 1E Milestone 2 Verification Script.
 * Verifies Shipment mapping for Partner Fleet collaboration fields.
 */
import { strict as assert } from 'node:assert';
import { test } from 'node:test';
import { mapApiShipmentToShipment } from '../src/features/dispatcher/shipments/utils/shipmentMapper.ts';

test('1. Internal shipment mapping retains collaborationType internal', () => {
  const apiShipment = {
    id: 'shipment-uuid-1',
    shipment_number: 'SHP-001',
    priority: 'normal',
    status_id: 1,
    pickup_city: 'Idlib',
    pickup_country: 'SY',
    delivery_city: 'Istanbul',
    delivery_country: 'TR',
    customer_company_id: 'client-company-1',
    customer_company: {
      id: 'client-company-1',
      name: 'Client Corp',
      company_code: 'CMP-CLIENT-1',
    },
    executing_company_id: null,
    executing_company: null,
    collaboration_type: 'internal',
  };

  const mapped = mapApiShipmentToShipment(apiShipment);
  assert.equal(mapped.id, 'shipment-uuid-1');
  assert.equal(mapped.shipmentNumber, 'SHP-001');
  assert.equal(mapped.collaborationType, 'internal');
  assert.equal(mapped.executingCompanyId, null);
  assert.equal(mapped.executingCompanyName, null);
});

test('2. Outbound Partner shipment mapping maps executing company & collaborationType outbound_partner', () => {
  const apiShipment = {
    id: 'shipment-uuid-2',
    shipment_number: 'SHP-002',
    priority: 'high',
    status_id: 2,
    pickup_city: 'Aleppo',
    pickup_country: 'SY',
    delivery_city: 'Ankara',
    delivery_country: 'TR',
    customer_company_id: 'client-company-1',
    customer_company: {
      id: 'client-company-1',
      company_name: 'Client Corp',
      company_code: 'CMP-CLIENT-1',
    },
    executing_company_id: 'partner-company-99',
    executing_company: {
      id: 'partner-company-99',
      company_name: 'Al-Farouk Carrier Co',
      company_code: 'CMP-PARTNER-99',
    },
    collaboration_type: 'outbound_partner',
  };

  const mapped = mapApiShipmentToShipment(apiShipment);
  assert.equal(mapped.id, 'shipment-uuid-2');
  assert.equal(mapped.collaborationType, 'outbound_partner');
  assert.equal(mapped.executingCompanyId, 'partner-company-99');
  assert.equal(mapped.executingCompanyName, 'Al-Farouk Carrier Co');
  assert.equal(mapped.executingCompanyCode, 'CMP-PARTNER-99');
});

test('3. Inbound Partner shipment mapping maps collaborationType inbound_partner', () => {
  const apiShipment = {
    id: 'shipment-uuid-3',
    shipment_number: 'SHP-003',
    priority: 'urgent',
    status_id: 3,
    pickup_city: 'Gaziantep',
    pickup_country: 'TR',
    delivery_city: 'Bursa',
    delivery_country: 'TR',
    customer_company_id: 'partner-company-88',
    customer_company: {
      id: 'partner-company-88',
      company_name: 'Partner Customer Co',
      company_code: 'CMP-PARTNER-88',
    },
    executing_company_id: null,
    collaboration_type: 'inbound_partner',
  };

  const mapped = mapApiShipmentToShipment(apiShipment);
  assert.equal(mapped.id, 'shipment-uuid-3');
  assert.equal(mapped.collaborationType, 'inbound_partner');
  assert.equal(mapped.customer, 'Partner Customer Co');
});

test('4. Missing collaboration_type defaults safely to internal', () => {
  const apiShipment = {
    id: 'shipment-uuid-4',
    shipment_number: 'SHP-004',
    priority: 'normal',
    status_id: 1,
  };

  const mapped = mapApiShipmentToShipment(apiShipment);
  assert.equal(mapped.collaborationType, 'internal');
  assert.equal(mapped.executingCompanyId, null);
  assert.equal(mapped.executingCompanyName, null);
});
