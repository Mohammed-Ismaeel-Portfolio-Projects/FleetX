// @ts-nocheck
import React, { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  SafeAreaView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useFocusEffect, useRouter } from 'expo-router';
import { ArrowLeft, Calendar, CreditCard, DollarSign, FileText, RefreshCw } from 'lucide-react-native';

import { GradientBackground } from '@/components/product';
import { useTheme } from '@/hooks/use-theme';
import { useAuth } from '@/lib/auth/AuthContext';
import { clientInvoicesService } from '@/features/invoices/services/clientInvoices.service';
import type { MonthlyInvoiceListItem, MonthlyInvoiceStatus } from '@/features/invoices/types/invoice.types';

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

export default function InvoicesListScreen() {
  const theme = useTheme();
  const router = useRouter();
  const { user } = useAuth();

  const [invoices, setInvoices] = useState<MonthlyInvoiceListItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [activeFilter, setActiveFilter] = useState<'all' | 'finalized' | 'paid'>('all');

  const fetchInvoices = useCallback(async (showLoading = true) => {
    if (showLoading) setIsLoading(true);
    setError(null);
    try {
      const collection = await clientInvoicesService.getInvoices({ per_page: 50 });
      setInvoices(collection.data || []);
    } catch (err: any) {
      setError(err?.response?.data?.message || err?.message || 'Failed to load monthly invoices.');
    } finally {
      setIsLoading(false);
      setIsRefreshing(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      void fetchInvoices(true);
    }, [fetchInvoices])
  );

  const handleRefresh = () => {
    setIsRefreshing(true);
    void fetchInvoices(false);
  };

  const filteredInvoices = invoices.filter((item) => {
    if (activeFilter === 'all') return true;
    return item.status === activeFilter;
  });

  return (
    <GradientBackground>
      <SafeAreaView style={styles.safeArea}>
        {/* Header */}
        <View style={styles.header}>
          <Pressable
            onPress={() => router.back()}
            style={styles.backBtn}
            accessibilityRole="button"
            accessibilityLabel="Go back"
          >
            <ArrowLeft size={20} color="#94A3B8" />
          </Pressable>
          <View style={styles.headerTextGroup}>
            <Text style={styles.headerTitle}>Billing &amp; Invoices</Text>
            <Text style={styles.headerSubtitle}>Monthly commercial invoices for your company</Text>
          </View>
        </View>

        {/* Filter Pills */}
        <View style={styles.filterRow}>
          {(['all', 'finalized', 'paid'] as const).map((filterKey) => {
            const isActive = activeFilter === filterKey;
            const label = filterKey === 'all' ? 'All Invoices' : filterKey.toUpperCase();
            return (
              <Pressable
                key={filterKey}
                onPress={() => setActiveFilter(filterKey)}
                style={[
                  styles.filterPill,
                  isActive && styles.filterPillActive,
                ]}
              >
                <Text style={[styles.filterPillText, isActive && styles.filterPillTextActive]}>
                  {label}
                </Text>
              </Pressable>
            );
          })}
        </View>

        {/* Body */}
        {isLoading ? (
          <View style={styles.centerWrap}>
            <ActivityIndicator size="large" color="#0250C5" />
            <Text style={styles.loadingText}>Loading billing history…</Text>
          </View>
        ) : error ? (
          <View style={styles.errorCard}>
            <Text style={styles.errorTitle}>Invoices Unavailable</Text>
            <Text style={styles.errorBody}>{error}</Text>
            <Pressable style={styles.retryBtn} onPress={() => void fetchInvoices(true)}>
              <RefreshCw size={14} color="#FFFFFF" />
              <Text style={styles.retryText}>Retry</Text>
            </Pressable>
          </View>
        ) : (
          <FlatList
            data={filteredInvoices}
            keyExtractor={(item) => String(item.id)}
            contentContainerStyle={styles.listContent}
            refreshControl={
              <RefreshControl refreshing={isRefreshing} onRefresh={handleRefresh} tintColor="#0250C5" />
            }
            ListEmptyComponent={
              <View style={styles.emptyCard}>
                <FileText size={40} color="#475569" />
                <Text style={styles.emptyTitle}>No monthly invoices found</Text>
                <Text style={styles.emptyBody}>
                  {activeFilter === 'all'
                    ? 'No monthly commercial billing records have been generated for your company yet.'
                    : `No invoices matching status "${activeFilter.toUpperCase()}" were found.`}
                </Text>
              </View>
            }
            renderItem={({ item }) => (
              <Pressable
                style={({ pressed }) => [styles.invoiceCard, pressed && styles.invoiceCardPressed]}
                onPress={() => router.push(`/invoices/${item.id}`)}
                accessibilityRole="button"
                accessibilityLabel={`Invoice #${item.id}, Total ${formatCurrency(item.total, item.currency)}`}
              >
                <View style={styles.cardHeader}>
                  <View style={styles.idGroup}>
                    <FileText size={18} color="#38BDF8" />
                    <Text style={styles.invoiceId}>Invoice #{item.id}</Text>
                  </View>
                  <StatusBadge status={item.status} />
                </View>

                <View style={styles.cardBody}>
                  <View style={styles.metaRow}>
                    <Calendar size={14} color="#64748B" />
                    <Text style={styles.metaText}>
                      Period: {item.billing_period?.start ?? '—'} → {item.billing_period?.end ?? '—'}
                    </Text>
                  </View>
                  {item.paid_at ? (
                    <View style={styles.metaRow}>
                      <CreditCard size={14} color="#22D3EE" />
                      <Text style={styles.paidAtText}>Paid At: {item.paid_at}</Text>
                    </View>
                  ) : null}
                </View>

                <View style={styles.cardFooter}>
                  <Text style={styles.totalLabel}>Total Billed</Text>
                  <Text style={styles.totalAmount}>{formatCurrency(item.total, item.currency)}</Text>
                </View>
              </Pressable>
            )}
          />
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
  filterRow: {
    flexDirection: 'row',
    paddingHorizontal: 16,
    marginBottom: 14,
    gap: 8,
  },
  filterPill: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 10,
    backgroundColor: '#0F172A',
    borderWidth: 1,
    borderColor: '#1E293B',
  },
  filterPillActive: {
    backgroundColor: '#0250C5',
    borderColor: '#38BDF8',
  },
  filterPillText: { fontSize: 12, fontWeight: '700', color: '#94A3B8' },
  filterPillTextActive: { color: '#FFFFFF' },
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
  emptyCard: {
    padding: 32,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 12,
    borderRadius: 16,
    backgroundColor: '#0F172A',
    borderWidth: 1,
    borderColor: '#1E293B',
    marginTop: 20,
  },
  emptyTitle: { fontSize: 16, fontWeight: '800', color: '#FFFFFF' },
  emptyBody: { fontSize: 13, color: '#94A3B8', textAlign: 'center', lineHeight: 18 },
  listContent: { paddingHorizontal: 16, paddingBottom: 24, gap: 12 },
  invoiceCard: {
    borderRadius: 16,
    backgroundColor: '#0E1320',
    borderWidth: 1.5,
    borderColor: '#1E293B',
    padding: 16,
  },
  invoiceCardPressed: { opacity: 0.8 },
  cardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 12,
  },
  idGroup: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  invoiceId: { fontSize: 16, fontWeight: '800', color: '#38BDF8', fontFamily: 'monospace' },
  badge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 8,
    borderWidth: 1,
  },
  badgeText: { fontSize: 10, fontWeight: '800', letterSpacing: 0.8 },
  cardBody: { gap: 6, marginBottom: 14 },
  metaRow: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  metaText: { fontSize: 13, color: '#CBD5E1' },
  paidAtText: { fontSize: 13, color: '#22D3EE', fontWeight: '600' },
  cardFooter: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderTopWidth: 1,
    borderTopColor: 'rgba(255, 255, 255, 0.06)',
    paddingTop: 12,
  },
  totalLabel: { fontSize: 12, color: '#94A3B8', textTransform: 'uppercase', letterSpacing: 0.5 },
  totalAmount: { fontSize: 18, fontWeight: '800', color: '#F8FAFC', fontFamily: 'monospace' },
});
