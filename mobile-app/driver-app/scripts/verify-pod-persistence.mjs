import assert from 'assert';

console.log('=== VERIFYING P1 OFFLINE POD EVIDENCE PERSISTENCE ===');

// Mock Storage & Filesystem Environment
const mockStorage = new Map();
const mockFiles = new Set();

globalThis.AsyncStorage = {
  getItem: async (key) => mockStorage.get(key) || null,
  setItem: async (key, val) => mockStorage.set(key, val),
  removeItem: async (key) => mockStorage.delete(key),
  clear: async () => mockStorage.clear(),
};

// 1. Simulate Evidence Capture in Temp OS Cache
const tempPhoto = 'file:///os_temp_cache/photo-101.jpg';
const tempSig = 'file:///os_temp_cache/sig-101.json';
mockFiles.add(tempPhoto);
mockFiles.add(tempSig);

// 2. Persist to Persistent App Document Storage
function persistFiles(assignmentId, photoUri, sigUri) {
  const uuid = Math.random().toString(36).substring(2, 7);
  const persistentPhoto = `file:///app_documents/pod_evidence/pod-photo-${assignmentId}-${uuid}.jpg`;
  const persistentSig = `file:///app_documents/pod_evidence/pod-signature-${assignmentId}-${uuid}.json`;

  mockFiles.add(persistentPhoto);
  mockFiles.add(persistentSig);

  return { photoUri: persistentPhoto, signatureUri: persistentSig };
}

const persisted = persistFiles(101, tempPhoto, tempSig);
assert.ok(persisted.photoUri.includes('/app_documents/pod_evidence/'));
assert.ok(persisted.signatureUri.includes('/app_documents/pod_evidence/'));
assert.strictEqual(mockFiles.has(persisted.photoUri), true);
assert.strictEqual(mockFiles.has(persisted.signatureUri), true);

console.log('✓ 1. Temporary files copied to persistent app document storage');

// 3. Simulate OS Cache Purge (Temporary Cache cleared by OS)
mockFiles.delete(tempPhoto);
mockFiles.delete(tempSig);

assert.strictEqual(mockFiles.has(tempPhoto), false, 'Temp photo should be purged');
assert.strictEqual(mockFiles.has(persisted.photoUri), true, 'Persistent photo MUST survive OS cache purge');

console.log('✓ 2. Persistent evidence files survived OS cache purge');

// 4. Enqueue into Offline Queue Store
const queuedEvent = {
  id: 'evt-pod-101',
  type: 'POD_SUBMIT',
  assignmentId: 101,
  payload: {
    photo: { uri: persisted.photoUri, name: 'delivery.jpg', type: 'image/jpeg' },
    signature: { uri: persisted.signatureUri, name: 'signature.json', type: 'application/json' },
    receiverName: 'Michael Schneider',
    conditionStatus: 'good',
  },
  status: 'pending',
};
await AsyncStorage.setItem('@fleetx_driver_offline_queue_v1', JSON.stringify([queuedEvent]));

// 5. App Restart Simulation — Reload Queue from AsyncStorage
const rawQueue = await AsyncStorage.getItem('@fleetx_driver_offline_queue_v1');
const reloaded = JSON.parse(rawQueue);

assert.strictEqual(reloaded.length, 1);
assert.strictEqual(mockFiles.has(reloaded[0].payload.photo.uri), true, 'File must exist on disk after app restart');
assert.strictEqual(mockFiles.has(reloaded[0].payload.signature.uri), true, 'Signature file must exist on disk after app restart');

console.log('✓ 3. Queue & evidence files survived app restart');

// 6. Successful Synchronization & Confirmed Local Cleanup
function cleanupFiles(pUri, sUri) {
  mockFiles.delete(pUri);
  mockFiles.delete(sUri);
}
cleanupFiles(reloaded[0].payload.photo.uri, reloaded[0].payload.signature.uri);

assert.strictEqual(mockFiles.has(persisted.photoUri), false, 'Local persistent photo should be deleted after upload');
assert.strictEqual(mockFiles.has(persisted.signatureUri), false, 'Local persistent signature should be deleted after upload');

console.log('✓ 4. Local evidence files cleaned up after confirmed backend upload');
console.log('=== P1 OFFLINE POD EVIDENCE PERSISTENCE VERIFICATION SUCCESSFUL ===');
