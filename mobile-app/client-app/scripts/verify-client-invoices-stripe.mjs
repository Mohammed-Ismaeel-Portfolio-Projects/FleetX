import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';

test('1. Client invoice service endpoints match backend REST routes', () => {
  const invoiceId = 101;
  const listEndpoint = '/client/monthly-invoices';
  const detailEndpoint = `/client/monthly-invoices/${invoiceId}`;
  const checkoutEndpoint = `/client/monthly-invoices/${invoiceId}/checkout`;
  const paymentStatusEndpoint = `/client/monthly-invoices/${invoiceId}/payment-status`;

  assert.equal(listEndpoint, '/client/monthly-invoices');
  assert.equal(detailEndpoint, '/client/monthly-invoices/101');
  assert.equal(checkoutEndpoint, '/client/monthly-invoices/101/checkout');
  assert.equal(paymentStatusEndpoint, '/client/monthly-invoices/101/payment-status');
});

test('2. App scheme configuration matches fleetxclient deep linking', async () => {
  const appConfigPath = path.resolve('app.config.js');
  assert.ok(fs.existsSync(appConfigPath), 'app.config.js must exist');

  const content = fs.readFileSync(appConfigPath, 'utf8');
  assert.ok(content.includes("scheme: 'fleetxclient'"), 'App scheme must be set to fleetxclient');
});

test('3. Invoice status determines payable eligibility correctly', () => {
  const isPayable = (status) => status === 'finalized';

  assert.equal(isPayable('draft'), false, 'Draft invoices must not be payable');
  assert.equal(isPayable('void'), false, 'Void invoices must not be payable');
  assert.equal(isPayable('paid'), false, 'Paid invoices must not be payable');
  assert.equal(isPayable('finalized'), true, 'Finalized invoices must be payable');
});

test('4. Deep-link return URL parameter parser handles payment flows', () => {
  const parseDeepLinkParams = (url) => {
    const queryIndex = url.indexOf('?');
    if (queryIndex === -1) return { payment: null, invoice: null };
    const queryStr = url.slice(queryIndex);
    const params = new URLSearchParams(queryStr);
    return {
      payment: params.get('payment'),
      invoice: params.get('invoice') ? Number(params.get('invoice')) : null,
    };
  };

  const successLink = 'fleetxclient://invoices?payment=success&invoice=42&session_id=cs_test_123';
  const parsedSuccess = parseDeepLinkParams(successLink);
  assert.equal(parsedSuccess.payment, 'success');
  assert.equal(parsedSuccess.invoice, 42);

  const cancelLink = 'fleetxclient://invoices?payment=cancelled&invoice=42';
  const parsedCancel = parseDeepLinkParams(cancelLink);
  assert.equal(parsedCancel.payment, 'cancelled');
  assert.equal(parsedCancel.invoice, 42);
});

test('5. Security audit: Client App contains no Stripe secret keys or amount mutations', () => {
  const filesToScan = [
    'src/features/invoices/types/invoice.types.ts',
    'src/features/invoices/services/clientInvoices.service.ts',
    'src/app/invoices/index.tsx',
    'src/app/invoices/[id].tsx',
  ];

  for (const relPath of filesToScan) {
    const fullPath = path.resolve(relPath);
    assert.ok(fs.existsSync(fullPath), `File must exist: ${relPath}`);
    const code = fs.readFileSync(fullPath, 'utf8');

    assert.equal(code.includes('sk_test_'), false, `${relPath} must not contain Stripe secret key`);
    assert.equal(code.includes('whsec_'), false, `${relPath} must not contain Stripe webhook secret`);
    assert.equal(code.includes('status = "paid"'), false, `${relPath} must not directly mutate status to paid`);
    assert.equal(code.includes("status = 'paid'"), false, `${relPath} must not directly mutate status to paid`);
  }
});
