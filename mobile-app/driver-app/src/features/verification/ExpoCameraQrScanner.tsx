import { StyleSheet, View } from 'react-native';
import { CameraView } from 'expo-camera';

import type { QrScannerViewProps } from './scannerTypes';

/**
 * Expo Camera QR scanner (SDK 56). Scanning alone is never success — parent submits to API.
 */
export function ExpoCameraQrScanner({ active, onScanned, testID }: QrScannerViewProps) {
  return (
    <View style={styles.fill} testID={testID ?? 'qr-scanner-camera'}>
      <CameraView
        style={StyleSheet.absoluteFill}
        facing="back"
        barcodeScannerSettings={{ barcodeTypes: ['qr'] }}
        onBarcodeScanned={
          active
            ? ({ data }) => {
                if (typeof data === 'string' && data.length > 0) {
                  onScanned({ data });
                }
              }
            : undefined
        }
      />
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, overflow: 'hidden', borderRadius: 12 },
});
