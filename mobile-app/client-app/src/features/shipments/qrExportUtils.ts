import { Platform, Share } from 'react-native';
import { cacheDirectory, documentDirectory, writeAsStringAsync, EncodingType } from 'expo-file-system/legacy';

/**
 * Format a deterministic filename for exported QR code PNG.
 * Example: FLeetX-SHP-2026-0089-QR.png
 */
export function buildQrFilename(shipmentNumber?: string | null): string {
  if (!shipmentNumber || typeof shipmentNumber !== 'string') {
    return 'FLeetX-QR.png';
  }
  const sanitized = shipmentNumber.trim().replace(/[^a-zA-Z0-9_-]/g, '_');
  return sanitized ? `FLeetX-${sanitized}-QR.png` : 'FLeetX-QR.png';
}

export interface ExportQrResult {
  success: boolean;
  message: string;
}

/**
 * Export the QR code base64 image data to a PNG file.
 * Handles Web browser native download and Mobile platform native save/share.
 */
export async function exportQrCodeImage(
  base64Data: string,
  shipmentNumber?: string | null
): Promise<ExportQrResult> {
  if (!base64Data || typeof base64Data !== 'string') {
    return { success: false, message: 'Unable to export QR code: image data missing.' };
  }

  const cleanBase64 = base64Data.includes(',')
    ? base64Data.split(',')[1]
    : base64Data;

  const filename = buildQrFilename(shipmentNumber);

  try {
    if (Platform.OS === 'web') {
      if (typeof document !== 'undefined') {
        const link = document.createElement('a');
        link.href = `data:image/png;base64,${cleanBase64}`;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        return { success: true, message: 'QR code downloaded.' };
      }
      return { success: false, message: 'Web environment unavailable for download.' };
    }

    const dir = cacheDirectory || documentDirectory || '';
    const fileUri = dir.endsWith('/') ? `${dir}${filename}` : `${dir}/${filename}`;

    await writeAsStringAsync(fileUri, cleanBase64, {
      encoding: EncodingType.Base64,
    });

    try {
      const Sharing = await import('expo-sharing');
      if (await Sharing.isAvailableAsync()) {
        await Sharing.shareAsync(fileUri, {
          mimeType: 'image/png',
          dialogTitle: 'Save QR Code',
          UTI: 'public.png',
        });
        return { success: true, message: 'QR code saved successfully.' };
      }
    } catch {
      // Fallback to React Native Share
    }

    await Share.share(
      Platform.OS === 'ios'
        ? { url: fileUri }
        : { url: fileUri, title: filename }
    );

    return { success: true, message: 'QR code saved successfully.' };
  } catch (err) {
    console.error('[qrExportUtils] Save/Download error:', err);
    return { success: false, message: 'Unable to save the QR code. Please try again.' };
  }
}
