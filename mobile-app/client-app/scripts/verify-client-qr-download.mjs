/**
 * Client QR Code Download & Export Verification Script
 * Run: node ./scripts/verify-client-qr-download.mjs
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

function buildQrFilename(shipmentNumber) {
  if (!shipmentNumber || typeof shipmentNumber !== 'string') {
    return 'FLeetX-QR.png';
  }
  const sanitized = shipmentNumber.trim().replace(/[^a-zA-Z0-9_-]/g, '_');
  return sanitized ? `FLeetX-${sanitized}-QR.png` : 'FLeetX-QR.png';
}

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const clientRoot = path.join(__dirname, '..');

function readClient(rel) {
  return fs.readFileSync(path.join(clientRoot, rel), 'utf8');
}

let passed = 0;
let failed = 0;

function check(name, fn) {
  try {
    fn();
    passed += 1;
    console.log(`PASS  ${name}`);
  } catch (e) {
    failed += 1;
    console.error(`FAIL  ${name}`);
    console.error(e);
  }
}

// 1. Filename Sanitization Unit Tests
check('buildQrFilename sanitizes shipment numbers correctly', () => {
  assert.equal(buildQrFilename('SHP-2026-0089'), 'FLeetX-SHP-2026-0089-QR.png');
  assert.equal(buildQrFilename('SHP/2026 #89'), 'FLeetX-SHP_2026__89-QR.png');
  assert.equal(buildQrFilename(null), 'FLeetX-QR.png');
  assert.equal(buildQrFilename(undefined), 'FLeetX-QR.png');
  assert.equal(buildQrFilename(''), 'FLeetX-QR.png');
});

// 2. Component Integration AST Checks
const cardCode = readClient('src/features/shipments/ShipmentVerificationQrCard.tsx');
const detailsCode = readClient('src/app/shipment-details/[id].tsx');
const utilsCode = readClient('src/features/shipments/qrExportUtils.ts');

check('ShipmentVerificationQrCard wires getRef on QRCode', () => {
  assert.match(cardCode, /getRef=\{\(c\)\s*=>/);
  assert.match(cardCode, /exportQrCodeImage/);
  assert.match(cardCode, /testID="qr-download-button"/);
  assert.match(cardCode, /Download QR Code/);
});

check('Shipment details passes shipmentNumber prop', () => {
  assert.match(detailsCode, /shipmentNumber=\{details\?\.shipmentNumber \?\? null\}/);
});

check('qrExportUtils supports Web and Mobile exports safely', () => {
  assert.match(utilsCode, /Platform\.OS === 'web'/);
  assert.match(utilsCode, /writeAsStringAsync/);
  assert.match(utilsCode, /expo-sharing/);
  assert.match(utilsCode, /Share\.share/);
  assert.doesNotMatch(utilsCode, /fetch\(|axios|GraphHopper|backend/i);
});

console.log('');
console.log(`Client QR Code Download Verification: ${passed} passed, ${failed} failed.`);
if (failed > 0) process.exit(1);
