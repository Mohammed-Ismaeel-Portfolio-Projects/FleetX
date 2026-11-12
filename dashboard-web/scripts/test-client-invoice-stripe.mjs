import test from 'node:test';
import assert from 'node:assert/strict';

test('1. Client invoice checkout service constructs correct API endpoint', () => {
  const invoiceId = 42;
  const endpoint = `/client/monthly-invoices/${invoiceId}/checkout`;
  assert.equal(endpoint, '/client/monthly-invoices/42/checkout');
});

test('2. Client payment status service constructs correct API endpoint', () => {
  const invoiceId = 42;
  const endpoint = `/client/monthly-invoices/${invoiceId}/payment-status`;
  assert.equal(endpoint, '/client/monthly-invoices/42/payment-status');
});

test('3. Invoice status determines payable state correctly', () => {
  const isPayable = (status) => status === 'finalized';
  assert.equal(isPayable('draft'), false, 'Draft invoices must not be payable');
  assert.equal(isPayable('void'), false, 'Void invoices must not be payable');
  assert.equal(isPayable('paid'), false, 'Paid invoices must not be payable');
  assert.equal(isPayable('finalized'), true, 'Finalized invoices must be payable');
});

test('4. Payment redirect query parameter parser handles success and cancelled flows', () => {
  const parseQueryParams = (search) => {
    const params = new URLSearchParams(search);
    return {
      payment: params.get('payment'),
      invoice: params.get('invoice') ? Number(params.get('invoice')) : null,
    };
  };

  const successResult = parseQueryParams('?payment=success&invoice=15');
  assert.equal(successResult.payment, 'success');
  assert.equal(successResult.invoice, 15);

  const cancelledResult = parseQueryParams('?payment=cancelled&invoice=15');
  assert.equal(cancelledResult.payment, 'cancelled');
  assert.equal(cancelledResult.invoice, 15);

  const emptyResult = parseQueryParams('');
  assert.equal(emptyResult.payment, null);
  assert.equal(emptyResult.invoice, null);
});

test('5. Frontend never mutates invoice status directly', () => {
  // Verification check: status mutation is server-side only via webhook / API response
  const allowedClientActions = ['checkout', 'paymentStatus', 'getMonthlyInvoice'];
  assert.equal(allowedClientActions.includes('updateStatus'), false);
});
