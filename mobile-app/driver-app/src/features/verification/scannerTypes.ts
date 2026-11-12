/**
 * Testable QR scanner surface (D5).
 * Real camera lives in ExpoCameraQrScanner; tests can inject a mock.
 */

export type BarcodeScanResult = {
  data: string;
};

export type QrScannerViewProps = {
  active: boolean;
  onScanned: (result: BarcodeScanResult) => void;
  testID?: string;
};
