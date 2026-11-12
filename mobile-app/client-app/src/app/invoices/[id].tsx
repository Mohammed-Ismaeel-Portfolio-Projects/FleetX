// @ts-nocheck
import React, { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Pressable,
  SafeAreaView,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import * as Linking from 'expo-linking';
import {
  ArrowLeft,
  Calendar,
  CheckCircle2,
  CreditCard,
  DollarSign,
  FileText,
  List,
  Lock,
  RefreshCw,
  ShieldCheck,
} from 'lucide-react-native';

import { GradientBackground } from '@/components/product';
import { useTheme } from '@/hooks/use-theme';
import { clientInvoicesService } from '@/features/invoices/services/clientInvoices.service';
import type { MonthlyInvoiceDetail, MonthlyInvoiceStatus } from '@/features/invoices/types/invoice.types';

WebBrowser.maybeCompleteAuthSession();

function formatCurrency(amount: number, currency: string): string {
  try {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: currency || 'USD',
    }).format(amount);
  } catch {
    return `${currency} ${amount.toFixed(2)}`;
  }
}

function StatusBadge({ status }: { status: MonthlyInvoiceStatus }) {
  const getBadgeStyle = () => {
    switch (status) {
      case 'paid':
        return { bg: 'rgba(6, 182, 212, 0.15)', border: 'rgba(6, 182, 212, 0.3)', text: '#22D3EE', label: 'PAID' };
      case 'finalized':
        return { bg: 'rgba(16, 185, 129, 0.15)', border: 'rgba(16, 185, 129, 0.3)', text: '#34D399', label: 'FINALIZED' };
      case 'draft':
        return { bg: 'rgba(245, 158, 11, 0.15)', border: 'rgba(245, 158, 11, 0.3)', text: '#FBBF24', label: 'DRAFT' };
      case 'void':
        return { bg: 'rgba(100, 116, 139, 0.15)', border: 'rgba(100, 116, 139, 0.3)', text: '#94A3B8', label: 'VOID' };
      default:
        return { bg: 'rgba(255, 255, 255, 0.1)', border: 'rgba(255, 255, 255, 0.2)', text: '#94A3B8', label: String(status).toUpperCase() };
    }
  };

  const style = getBadgeStyle();

  return (
    <View style={[styles.badge, { backgroundColor: style.bg, borderColor: style.border }]}>
      <Text style={[styles.badgeText, { color: style.text }]}>{style.label}</Text>
    </View>
  );
}

export default function InvoiceDetailsScreen() {
  const theme = useTheme();
  const router = useRouter();
  const { id } = useLocalSearchParams<{ id: string }>();

  const [invoice, setInvoice] = useState<MonthlyInvoiceDetail | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isCheckoutLoading, setIsCheckoutLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [statusMessage, setStatusMessage] = useState<string | null>(null);

  const fetchInvoiceDetails = useCallback(async () => {
    if (!id) return;
    setIsLoading(true);
    setError(null);
    try {
      const details = await clientInvoicesService.getInvoice(id);
      setInvoice(details);
    } catch (err: any) {
      setError(err?.response?.data?.message || err?.message || 'Failed to load invoice details.');
    } finally {
      setIsLoading(false);
    }
  }, [id]);

  useEffect(() => {
    void fetchInvoiceDetails();
  }, [fetchInvoiceDetails]);

  const verifyAuthoritativePaymentStatus = async () => {
    if (!id) return;
    try {
      const paymentStatus = await clientInvoicesService.getPaymentStatus(id);
      if (paymentStatus.status === 'paid') {
        setStatusMessage('Payment confirmed! Invoice is marked PAID.');
        setInvoice((prev) => (prev ? { ...prev, status: 'paid', paid_at: paymentStatus.paid_at } : prev));
      } else {
        setStatusMessage('Payment submitted. Processing webhook confirmation…');
        // Re-check once after 3 seconds to catch backend webhook processing
        setTimeout(async () => {
          try {
            const recheck = await clientInvoicesService.getPaymentStatus(id);
            if (recheck.status === 'paid') {
              setStatusMessage('Payment confirmed! Invoice is marked PAID.');
              setInvoice((prev) => (prev ? { ...prev, status: 'paid', paid_at: recheck.paid_at } : prev));
            }
          } catch {}
        }, 3000);
      }
    } catch {
      void fetchInvoiceDetails();
    }
  };

  const handlePayWithStripe = async () => {
    if (!invoice || invoice.status !== 'finalized' || isCheckoutLoading) {
      return;
    }

    setIsCheckoutLoading(true);
    setStatusMessage(null);

    try {
      const returnUrl = Linking.createURL('invoices');
      const checkoutRes = await clientInvoicesService.createCheckoutSession(invoice.id, returnUrl);

      if (!checkoutRes.checkout_url) {
        throw new Error('No checkout URL returned from server.');
      }

      // Open in-app browser session listening for returnUrl (fleetxclient://invoices)
      const browserResult = await WebBrowser.openAuthSessionAsync(checkoutRes.checkout_url, returnUrl);

      if (browserResult.type === 'success' && browserResult.url) {
        const parsed = Linking.parse(browserResult.url);
        const paymentResult = parsed.queryParams?.payment;

        if (paymentResult === 'success') {
          void verifyAuthoritativePaymentStatus();
        } else if (paymentResult === 'cancelled') {
          setStatusMessage('Checkout session was cancelled.');
        }
      } else {
        // Fallback status check in case browser was closed after payment
        void verifyAuthoritativePaymentStatus();
      }
    } catch (err: any) {
      const msg = err?.response?.data?.message || err?.message || 'Failed to create Stripe Checkout session.';
      Alert.alert('Checkout Error', msg);
    } finally {
      setIsCheckoutLoading(false);
    }
  };

  return (
    <GradientBackground>
      <SafeAreaView style={styles.safeArea}>
        {/* Header */}
        <View style={styles.header}>
          <Pressable onPress={() => router.back()} style={styles.backBtn} accessibilityRole="button" accessibilityLabel="Go back">
            <ArrowLeft size={20} color="#94A3B8" />
          </Pressable>
          <View style={styles.headerTextGroup}>
            <Text style={styles.headerTitle}>Invoice #{id}</Text>
            <Text style={styles.headerSubtitle}>Commercial billing breakdown</Text>
          </View>
        </View>

        {isLoading ? (
          <View style={styles.centerWrap}>
            <ActivityIndicator size="large" color="#0250C5" />
            <Text style={styles.loadingText}>Fetching invoice details…</Text>
          </View>
        ) : error || !invoice ? (
          <View style={styles.errorCard}>
            <Text style={styles.errorTitle}>Invoice Unavailable</Text>
            <Text style={styles.errorBody}>{error || 'Invoice not found.'}</Text>
            <Pressable style={styles.retryBtn} onPress={() => void fetchInvoiceDetails()}>
              <RefreshCw size={14} color="#FFFFFF" />
              <Text style={styles.retryText}>Retry</Text>
            </Pressable>
          </View>
        ) : (
          <ScrollView contentContainerStyle={styles.scrollContent}>
            {/* Status Message Banner */}
            {statusMessage ? (
              <View style={styles.statusBanner}>
                <ShieldCheck size={18} color="#38BDF8" />
                <Text style={styles.statusBannerText}>{statusMessage}</Text>
              </View>
            ) : null}

            {/* Main Details Card */}
            <View style={styles.detailsCard}>
              <View style={styles.cardTopRow}>
                <View>
                  <Text style={styles.label}>INVOICE NUMBER</Text>
                  <Text style={styles.valueHighlight}>#{invoice.id}</Text>
                </View>
                <StatusBadge status={invoice.status} />
              </View>

              <View style={styles.divider} />

              <View style={styles.infoGrid}>
                <View style={styles.infoRow}>
                  <Calendar size={16} color="#64748B" />
                  <View>
                    <Text style={styles.label}>BILLING PERIOD</Text>
                    <Text style={styles.value}>
                      {invoice.billing_period?.start ?? '—'} → {invoice.billing_period?.end ?? '—'}
                    </Text>
                  </View>
                </View>

                <View style={styles.infoRow}>
                  <Calendar size={16} color="#64748B" />
                  <View>
                    <Text style={styles.label}>ISSUED AT</Text>
                    <Text style={styles.value}>{invoice.issued_at || '—'}</Text>
                  </View>
                </View>

                {invoice.paid_at ? (
                  <View style={styles.infoRow}>
                    <CheckCircle2 size={16} color="#22D3EE" />
                    <View>
                      <Text style={styles.label}>PAID AT</Text>
                      <Text style={[styles.value, { color: '#22D3EE', fontWeight: '700' }]}>{invoice.paid_at}</Text>
                    </View>
                  </View>
                ) : null}
              </View>

              <View style={styles.divider} />

              {/* Financial Totals */}
              <View style={styles.financialBlock}>
                <View style={styles.finRow}>
                  <Text style={styles.finLabel}>Subtotal</Text>
                  <Text style={styles.finValue}>{formatCurrency(invoice.subtotal, invoice.currency)}</Text>
                </View>
                <View style={styles.finRow}>
                  <Text style={styles.finLabel}>Tax</Text>
                  <Text style={styles.finValue}>{formatCurrency(invoice.tax || 0, invoice.currency)}</Text>
                </View>
                <View style={[styles.finRow, styles.finRowTotal]}>
                  <Text style={styles.totalLabel}>TOTAL AMOUNT</Text>
                  <Text style={styles.totalValue}>{formatCurrency(invoice.total, invoice.currency)}</Text>
                </View>
              </View>
            </View>

            {/* Line Items Section */}
            <View style={styles.sectionCard}>
              <View style={styles.sectionHeader}>
                <List size={16} color="#38BDF8" />
                <Text style={styles.sectionTitle}>Line Items</Text>
              </View>
              {!invoice.line_items || invoice.line_items.length === 0 ? (
                <Text style={styles.noItemsText}>No line item details returned for this invoice.</Text>
              ) : (
                invoice.line_items.map((item) => (
                  <View key={item.id} style={styles.lineItemRow}>
                    <View style={styles.lineItemLeft}>
                      <Text style={styles.lineItemRef}>{item.shipment_reference || `Shipment #${item.shipment_id}`}</Text>
                      <Text style={styles.lineItemDesc}>{item.description || 'Freight Charges'}</Text>
                    </View>
                    <View style={styles.lineItemRight}>
                      <Text style={styles.lineItemTotal}>{formatCurrency(item.line_total, item.currency)}</Text>
                      <Text style={styles.lineItemQty}>Qty: {item.quantity}</Text>
                    </View>
                  </View>
                ))
              )}
            </View>

            {/* Payment Action State */}
            {invoice.status === 'finalized' ? (
              <View style={styles.actionBlock}>
                <Pressable
                  style={({ pressed }) => [styles.payBtn, (pressed || isCheckoutLoading) && styles.payBtnDisabled]}
                  onPress={() => void handlePayWithStripe()}
                  disabled={isCheckoutLoading}
                  accessibilityRole="button"
                  accessibilityLabel="Pay with Stripe Test"
                >
                  {isCheckoutLoading ? (
                    <ActivityIndicator size="small" color="#FFFFFF" />
                  ) : (
                    <CreditCard size={18} color="#FFFFFF" />
                  )}
                  <Text style={styles.payBtnText}>
                    {isCheckoutLoading ? 'Preparing Checkout…' : 'Pay with Stripe (Test)'}
                  </Text>
                </Pressable>
                <Text style={styles.payNotice}>
                  Secure electronic sandbox checkout via Stripe. Official test environment.
                </Text>
              </View>
            ) : invoice.status === 'paid' ? (
              <View style={styles.paidBanner}>
                <CheckCircle2 size={24} color="#22D3EE" />
                <View style={styles.paidBannerTextGroup}>
                  <Text style={styles.paidBannerTitle}>INVOICE PAID</Text>
                  <Text style={styles.paidBannerSubtitle}>
                    This invoice has been successfully settled via Stripe Test Checkout.
                  </Text>
                </View>
              </View>
            ) : (
              <View style={styles.nonPayableBanner}>
                <Lock size={18} color="#64748B" />
                <Text style={styles.nonPayableText}>
                  This invoice status ({invoice.status.toUpperCase()}) is not eligible for checkout.
                </Text>
              </View>
            )}
          </ScrollView>
        )}
      </SafeAreaView>
    </GradientBackground>
  );
}

const styles = StyleSheet.create({
  safeArea: { flex: 1 },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingTop: 12,
    paddingBottom: 16,
    gap: 12,
  },
  backBtn: {
    width: 38,
    height: 38,
    borderRadius: 12,
    backgroundColor: '#0F172A',
    borderWidth: 1,
    borderColor: '#1E293B',
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerTextGroup: { flex: 1 },
  headerTitle: { fontSize: 20, fontWeight: '800', color: '#FFFFFF' },
  headerSubtitle: { fontSize: 12, color: '#94A3B8', marginTop: 2 },
  centerWrap: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 12, padding: 24 },
  loadingText: { fontSize: 13, color: '#94A3B8', fontWeight: '600' },
  errorCard: {
    margin: 16,
    padding: 20,
    borderRadius: 16,
    backgroundColor: 'rgba(239, 68, 68, 0.1)',
    borderWidth: 1,
    borderColor: 'rgba(239, 68, 68, 0.3)',
    alignItems: 'center',
    gap: 8,
  },
  errorTitle: { fontSize: 15, fontWeight: '800', color: '#FCA5A5' },
  errorBody: { fontSize: 13, color: '#F87171', textAlign: 'center' },
  retryBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginTop: 8,
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 10,
    backgroundColor: '#EF4444',
  },
  retryText: { color: '#FFFFFF', fontSize: 13, fontWeight: '700' },
  scrollContent: { paddingHorizontal: 16, paddingBottom: 32, gap: 16 },
  statusBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: 14,
    borderRadius: 12,
    backgroundColor: 'rgba(56, 189, 248, 0.12)',
    borderWidth: 1,
    borderColor: 'rgba(56, 189, 248, 0.3)',
  },
  statusBannerText: { flex: 1, fontSize: 13, color: '#E0F2FE', fontWeight: '600' },
  detailsCard: {
    borderRadius: 16,
    backgroundColor: '#0E1320',
    borderWidth: 1.5,
    borderColor: '#1E293B',
    padding: 18,
  },
  cardTopRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  label: { fontSize: 10, fontWeight: '800', color: '#64748B', letterSpacing: 0.8 },
  valueHighlight: { fontSize: 20, fontWeight: '800', color: '#38BDF8', fontFamily: 'monospace', marginTop: 2 },
  value: { fontSize: 14, color: '#F8FAFC', fontWeight: '600', marginTop: 2 },
  badge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 8,
    borderWidth: 1,
  },
  badgeText: { fontSize: 10, fontWeight: '800', letterSpacing: 0.8 },
  divider: { height: 1, backgroundColor: 'rgba(255, 255, 255, 0.08)', marginVertical: 14 },
  infoGrid: { gap: 12 },
  infoRow: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  financialBlock: { gap: 8 },
  finRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  finRowTotal: { paddingTop: 8, marginTop: 4, borderTopWidth: 1, borderTopColor: 'rgba(255, 255, 255, 0.1)' },
  finLabel: { fontSize: 13, color: '#94A3B8' },
  finValue: { fontSize: 14, color: '#E2E8F0', fontFamily: 'monospace' },
  totalLabel: { fontSize: 12, fontWeight: '800', color: '#38BDF8', letterSpacing: 0.6 },
  totalValue: { fontSize: 20, fontWeight: '800', color: '#FFFFFF', fontFamily: 'monospace' },
  sectionCard: {
    borderRadius: 16,
    backgroundColor: '#0E1320',
    borderWidth: 1.5,
    borderColor: '#1E293B',
    padding: 16,
    gap: 12,
  },
  sectionHeader: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  sectionTitle: { fontSize: 15, fontWeight: '800', color: '#FFFFFF' },
  noItemsText: { fontSize: 13, color: '#64748B', fontStyle: 'italic' },
  lineItemRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 8,
    borderBottomWidth: 1,
    borderBottomColor: 'rgba(255, 255, 255, 0.05)',
  },
  lineItemLeft: { flex: 1, paddingRight: 12 },
  lineItemRef: { fontSize: 14, fontWeight: '700', color: '#E2E8F0' },
  lineItemDesc: { fontSize: 12, color: '#94A3B8', marginTop: 2 },
  lineItemRight: { alignItems: 'flex-end' },
  lineItemTotal: { fontSize: 14, fontWeight: '700', color: '#38BDF8', fontFamily: 'monospace' },
  lineItemQty: { fontSize: 11, color: '#64748B', marginTop: 2 },
  actionBlock: { gap: 8, marginTop: 4 },
  payBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    height: 52,
    borderRadius: 14,
    backgroundColor: '#10B981',
    shadowColor: '#10B981',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 8,
    elevation: 4,
  },
  payBtnDisabled: { opacity: 0.6 },
  payBtnText: { color: '#FFFFFF', fontSize: 15, fontWeight: '800' },
  payNotice: { fontSize: 11, color: '#64748B', textAlign: 'center' },
  paidBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 16,
    borderRadius: 16,
    backgroundColor: 'rgba(6, 182, 212, 0.12)',
    borderWidth: 1.5,
    borderColor: 'rgba(6, 182, 212, 0.3)',
  },
  paidBannerTextGroup: { flex: 1 },
  paidBannerTitle: { fontSize: 14, fontWeight: '800', color: '#22D3EE', letterSpacing: 0.6 },
  paidBannerSubtitle: { fontSize: 12, color: '#E0F2FE', marginTop: 2, lineHeight: 16 },
  nonPayableBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    padding: 14,
    borderRadius: 12,
    backgroundColor: '#0F172A',
    borderWidth: 1,
    borderColor: '#1E293B',
  },
  nonPayableText: { fontSize: 12, color: '#94A3B8' },
});
