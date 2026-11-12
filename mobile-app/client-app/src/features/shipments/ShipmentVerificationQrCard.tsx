import React, { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';
import QRCode from 'react-native-qrcode-svg';
import { ShieldCheck, RefreshCw, AlertCircle, Clock, Download } from 'lucide-react-native';

import type { ClientVerificationQr } from './types';
import { exportQrCodeImage } from './qrExportUtils';

interface ShipmentVerificationQrCardProps {
  verification: ClientVerificationQr | null;
  shipmentNumber?: string | null;
  loading?: boolean;
  error?: string | null;
  onRetry?: () => void;
  theme: {
    text: string;
    textSecondary: string;
    background: string;
    card: string;
    border: string;
    tint: string;
  };
}

function formatExpiration(expiresAt: string | null): { formatted: string; isExpired: boolean } {
  if (!expiresAt) {
    return { formatted: '', isExpired: false };
  }

  const expDate = new Date(expiresAt);
  if (isNaN(expDate.getTime())) {
    return { formatted: '', isExpired: false };
  }

  const now = new Date();
  const diffMs = expDate.getTime() - now.getTime();
  const isExpired = diffMs <= 0;

  if (isExpired) {
    return { formatted: 'Expired', isExpired: true };
  }

  const minutes = Math.floor(diffMs / 60000);
  const hours = Math.floor(minutes / 60);

  const timeStr = expDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });

  if (minutes < 1) {
    return { formatted: `Expires at ${timeStr} (in < 1 min)`, isExpired: false };
  }

  if (hours > 0) {
    const remMin = minutes % 60;
    return {
      formatted: `Expires at ${timeStr} (in ${hours}h ${remMin}m)`,
      isExpired: false,
    };
  }

  return { formatted: `Expires at ${timeStr} (in ${minutes} min)`, isExpired: false };
}

export function ShipmentVerificationQrCard({
  verification,
  shipmentNumber = null,
  loading = false,
  error = null,
  onRetry,
  theme,
}: ShipmentVerificationQrCardProps) {
  const [expInfo, setExpInfo] = useState(() => formatExpiration(verification?.expiresAt ?? null));
  const [isExporting, setIsExporting] = useState(false);
  const [feedback, setFeedback] = useState<{ success: boolean; message: string } | null>(null);

  const qrSvgRef = useRef<{ toDataURL: (callback: (data: string) => void) => void } | null>(null);

  useEffect(() => {
    setExpInfo(formatExpiration(verification?.expiresAt ?? null));
    if (!verification?.expiresAt) return;

    const interval = setInterval(() => {
      setExpInfo(formatExpiration(verification.expiresAt));
    }, 10000);

    return () => clearInterval(interval);
  }, [verification?.expiresAt]);

  const isPickup = verification?.event === 'pickup_verify';
  const title = isPickup ? 'Pickup Verification' : 'Delivery Verification';
  const subtitle = isPickup
    ? 'Driver has arrived. Show this QR code to confirm cargo pickup.'
    : 'Driver completed delivery. Show this QR code to confirm delivery.';

  const handleDownload = () => {
    if (isExporting || !verification?.qrPayload || !qrSvgRef.current) return;
    setIsExporting(true);
    setFeedback(null);

    try {
      qrSvgRef.current.toDataURL(async (dataUrl: string) => {
        try {
          const res = await exportQrCodeImage(dataUrl, shipmentNumber);
          setFeedback(res);
        } catch {
          setFeedback({
            success: false,
            message: 'Unable to save the QR code. Please try again.',
          });
        } finally {
          setIsExporting(false);
        }
      });
    } catch {
      setFeedback({
        success: false,
        message: 'Unable to process the QR code. Please try again.',
      });
      setIsExporting(false);
    }
  };

  return (
    <View
      testID="client-qr-card"
      style={[
        styles.cardContainer,
        { backgroundColor: theme.card, borderColor: theme.border },
      ]}
    >
      <View style={styles.headerRow}>
        <ShieldCheck size={22} color={theme.tint || '#3B82F6'} style={styles.headerIcon} />
        <View style={styles.headerTextContainer}>
          <View style={styles.titleRow}>
            <Text testID="qr-header-title" style={[styles.headerTitle, { color: theme.text }]}>
              {loading ? 'Verification' : verification ? title : 'Verification'}
            </Text>
            {verification && !expInfo.isExpired ? (
              <View style={[styles.badge, { backgroundColor: 'rgba(59, 130, 246, 0.12)' }]}>
                <Text style={[styles.badgeText, { color: theme.tint || '#3B82F6' }]}>
                  {isPickup ? 'Pickup Ready' : 'Delivery Ready'}
                </Text>
              </View>
            ) : null}
          </View>
          <Text style={[styles.headerSubtitle, { color: theme.textSecondary }]}>
            {loading
              ? 'Checking verification status...'
              : verification
              ? subtitle
              : 'No verification code available yet. The code will appear when the driver reaches the verification stage.'}
          </Text>
        </View>
      </View>

      {loading && (
        <View style={styles.centerBox}>
          <ActivityIndicator size="small" color={theme.tint || '#3B82F6'} />
          <Text style={[styles.loadingText, { color: theme.textSecondary }]}>
            Preparing verification code...
          </Text>
        </View>
      )}

      {!loading && error && (
        <View style={styles.centerBox}>
          <AlertCircle size={24} color="#EF4444" />
          <Text style={[styles.errorText, { color: theme.text }]}>
            Unable to load verification code.
          </Text>
          {onRetry && (
            <Pressable
              testID="qr-retry-button"
              onPress={onRetry}
              style={[styles.retryBtn, { backgroundColor: theme.tint || '#3B82F6' }]}
            >
              <RefreshCw size={14} color="#FFFFFF" style={{ marginRight: 6 }} />
              <Text style={styles.retryBtnText}>Retry</Text>
            </Pressable>
          )}
        </View>
      )}

      {!loading && !error && verification && (
        <View style={styles.qrContentBox}>
          {expInfo.isExpired ? (
            <View style={styles.expiredBox}>
              <AlertCircle size={26} color="#F59E0B" />
              <Text style={[styles.expiredTitle, { color: theme.text }]}>
                Verification code expired
              </Text>
              <Text style={[styles.expiredSubtitle, { color: theme.textSecondary }]}>
                Generate a new secure code to continue verification.
              </Text>
              {onRetry && (
                <Pressable
                  testID="qr-retry-button"
                  onPress={onRetry}
                  style={[styles.retryBtn, { backgroundColor: theme.tint || '#3B82F6', marginTop: 12 }]}
                >
                  <RefreshCw size={14} color="#FFFFFF" style={{ marginRight: 6 }} />
                  <Text style={styles.retryBtnText}>Refresh Code</Text>
                </Pressable>
              )}
            </View>
          ) : (
            <>
              <View style={styles.qrWrapper}>
                <QRCode
                  value={verification.qrPayload}
                  size={180}
                  color="#000000"
                  backgroundColor="#FFFFFF"
                  getRef={(c) => {
                    qrSvgRef.current = c;
                  }}
                />
              </View>

              {expInfo.formatted ? (
                <View style={styles.expiryRow}>
                  <Clock size={13} color={theme.textSecondary} style={{ marginRight: 4 }} />
                  <Text testID="qr-expiry" style={[styles.expiryText, { color: theme.textSecondary }]}>
                    {expInfo.formatted}
                  </Text>
                </View>
              ) : null}

              <Pressable
                testID="qr-download-button"
                onPress={handleDownload}
                disabled={isExporting}
                style={({ pressed }) => [
                  styles.downloadBtn,
                  {
                    backgroundColor: theme.tint || '#3B82F6',
                    opacity: isExporting || pressed ? 0.75 : 1,
                  },
                ]}
                accessibilityRole="button"
                accessibilityLabel="Download QR Code"
              >
                {isExporting ? (
                  <ActivityIndicator size="small" color="#FFFFFF" style={{ marginRight: 8 }} />
                ) : (
                  <Download size={16} color="#FFFFFF" style={{ marginRight: 8 }} />
                )}
                <Text style={styles.downloadBtnText}>
                  {isExporting ? 'Preparing QR Code...' : 'Download QR Code'}
                </Text>
              </Pressable>

              {feedback && (
                <View style={styles.feedbackRow} testID="qr-export-feedback">
                  <Text
                    style={[
                      styles.feedbackText,
                      { color: feedback.success ? '#059669' : '#DC2626' },
                    ]}
                  >
                    {feedback.message}
                  </Text>
                </View>
              )}
            </>
          )}
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  cardContainer: {
    marginHorizontal: 16,
    marginVertical: 10,
    padding: 16,
    borderRadius: 16,
    borderWidth: 1,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
  },
  headerIcon: {
    marginTop: 2,
    marginRight: 10,
  },
  headerTextContainer: {
    flex: 1,
  },
  titleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 2,
  },
  headerTitle: {
    fontSize: 16,
    fontWeight: '700',
  },
  badge: {
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 6,
  },
  badgeText: {
    fontSize: 11,
    fontWeight: '700',
  },
  headerSubtitle: {
    fontSize: 13,
    lineHeight: 18,
    marginTop: 2,
  },
  centerBox: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 20,
  },
  loadingText: {
    marginTop: 8,
    fontSize: 13,
  },
  errorText: {
    marginTop: 6,
    fontSize: 13,
    fontWeight: '500',
  },
  retryBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 10,
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 8,
  },
  retryBtnText: {
    color: '#FFFFFF',
    fontSize: 13,
    fontWeight: '600',
  },
  qrContentBox: {
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 16,
  },
  qrWrapper: {
    padding: 16,
    backgroundColor: '#FFFFFF',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 2,
  },
  expiryRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 12,
  },
  expiryText: {
    fontSize: 12,
    fontWeight: '500',
  },
  expiredBox: {
    alignItems: 'center',
    paddingVertical: 12,
  },
  expiredTitle: {
    fontSize: 14,
    fontWeight: '700',
    marginTop: 6,
  },
  expiredSubtitle: {
    fontSize: 12,
    textAlign: 'center',
    marginTop: 4,
  },
  downloadBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 16,
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 10,
    width: '100%',
    maxWidth: 240,
  },
  downloadBtnText: {
    color: '#FFFFFF',
    fontSize: 14,
    fontWeight: '600',
  },
  feedbackRow: {
    marginTop: 8,
    alignItems: 'center',
  },
  feedbackText: {
    fontSize: 12,
    fontWeight: '600',
    textAlign: 'center',
  },
});
