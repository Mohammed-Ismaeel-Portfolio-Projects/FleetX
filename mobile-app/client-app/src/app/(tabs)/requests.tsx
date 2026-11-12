// @ts-nocheck — lucide icon props vs RN ColorValue
import { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useFocusEffect, useRouter } from 'expo-router';
import { FileText, Plus } from 'lucide-react-native';

import { GradientBackground } from '@/components/product';
import { UnavailableDomainScreen } from '@/components/unavailable-domain';
import { useTheme } from '@/hooks/use-theme';
import { useAuth } from '@/lib/auth/AuthContext';
import { formatPlaceSummary } from '@/features/shipments/shipmentsApi';
import {
  ClientShipmentRequestsApiError,
  listClientShipmentRequests,
} from '@/features/requests/requestsApi';
import {
  CLIENT_REQUEST_STATUS_FILTERS,
  formatRequestStatusLabel,
  isConvertedStatus,
  linkedShipmentId,
} from '@/features/requests/requestStatus';
import type { ClientShipmentRequest } from '@/features/requests/types';

const PAGE_SIZE = 15;

function formatDisplayDate(value: string | null | undefined): string | null {
  if (value == null) return null;
  const trimmed = String(value).trim();
  if (!trimmed) return null;
  const isoDate = trimmed.match(/^(\d{4}-\d{2}-\d{2})/);
  return isoDate ? isoDate[1] : trimmed;
}

function cargoSummary(item: ClientShipmentRequest): string | null {
  const type = item.cargoType && String(item.cargoType).trim();
  if (type) return type;
  const desc = item.cargoDescription && String(item.cargoDescription).trim();
  if (desc) return desc.length > 80 ? `${desc.slice(0, 77)}…` : desc;
  return null;
}

function RequestCard({ item }: { item: ClientShipmentRequest }) {
  const theme = useTheme();
  const router = useRouter();
  const number = (item.requestNumber && String(item.requestNumber).trim()) || 'Request';
  const origin = formatPlaceSummary(item.pickupCompany, item.pickupCity, item.pickupCountry);
  const destination = formatPlaceSummary(
    item.deliveryCompany,
    item.deliveryCity,
    item.deliveryCountry
  );
  const statusRaw = item.status?.name ?? null;
  const converted = isConvertedStatus(statusRaw);
  const shipmentId = linkedShipmentId(item.linkedShipment);
  const pickup = formatDisplayDate(item.requestedPickupDate);
  const updated = formatDisplayDate(item.updatedAt);
  const cargo = cargoSummary(item);

  const openDetails = () => {
    if (!item.id) return;
    router.push(`/request-details/${item.id}`);
  };

  const openShipment = () => {
    if (!shipmentId) return;
    router.push(`/shipment-details/${shipmentId}`);
  };

  return (
    <Pressable
      testID={`request-card-${item.id}`}
      style={({ pressed }) => [
        styles.card,
        {
          backgroundColor: theme.backgroundElement,
          borderColor: theme.border,
          opacity: pressed ? 0.92 : 1,
        },
      ]}
      onPress={openDetails}
      accessibilityRole="button"
      accessibilityLabel={`Open request ${number}. Status ${statusRaw || 'unavailable'}. ${origin} to ${destination}.`}>
      <View style={styles.cardTop}>
        <Text
          testID={`request-number-${item.id}`}
          style={[styles.requestNumber, { color: theme.text }]}
          numberOfLines={1}>
          {number}
        </Text>
        <View
          style={[styles.statusPill, { backgroundColor: 'rgba(30, 117, 255, 0.15)' }]}
          accessibilityRole="text"
          accessibilityLabel={statusRaw ? `Status ${formatRequestStatusLabel(statusRaw)}` : 'Status unavailable'}>
          <Text style={[styles.statusPillText, { color: theme.primary }]} numberOfLines={1}>
            {formatRequestStatusLabel(statusRaw)}
          </Text>
        </View>
      </View>
      <Text style={[styles.routeText, { color: theme.text }]} numberOfLines={2}>
        {origin}
        <Text style={{ color: theme.textSecondary }}> → </Text>
        {destination}
      </Text>
      {cargo ? (
        <Text style={[styles.metaText, { color: theme.textSecondary }]} numberOfLines={1}>
          {cargo}
        </Text>
      ) : null}
      {pickup ? (
        <Text style={[styles.metaText, { color: theme.textSecondary }]}>Requested pickup {pickup}</Text>
      ) : null}
      {updated ? (
        <Text style={[styles.metaText, { color: theme.textSecondary }]}>Updated {updated}</Text>
      ) : null}
      {converted ? (
        <View style={styles.convertedRow}>
          <Text
            testID={`request-converted-${item.id}`}
            style={[styles.metaText, { color: theme.textSecondary, flex: 1 }]}>
            Converted · Shipment created
          </Text>
          {shipmentId ? (
            <Pressable
              testID={`request-open-shipment-${item.id}`}
              onPress={(event) => {
                event?.stopPropagation?.();
                openShipment();
              }}
              hitSlop={8}
              accessibilityRole="button"
              accessibilityLabel={`Open shipment for ${number}`}>
              <Text style={[styles.openShipmentText, { color: theme.primary }]}>Open Shipment</Text>
            </Pressable>
          ) : null}
        </View>
      ) : null}
    </Pressable>
  );
}

/**
 * C3.4 / C5.4 Requests Center — GET /api/v1/client/shipment-requests.
 * List supports page/per_page + optional status filter (no request search).
 */
export default function RequestsScreen() {
  const theme = useTheme();
  const router = useRouter();
  const { roleOk, identityLinked } = useAuth();

  const [items, setItems] = useState<ClientShipmentRequest[]>([]);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [loadingMore, setLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [errorStatus, setErrorStatus] = useState<number | undefined>(undefined);
  const [errorSource, setErrorSource] = useState<'initial' | 'refresh' | 'append' | null>(null);
  const [errorIsNetwork, setErrorIsNetwork] = useState(false);
  const [appendFailedPage, setAppendFailedPage] = useState<number | null>(null);
  /** Exact shipment_order status.name, or null = no status filter. */
  const [statusFilter, setStatusFilter] = useState<string | null>(null);

  const inFlightRef = useRef(false);
  const pageRef = useRef(1);
  const lastPageRef = useRef(1);
  const hasItemsRef = useRef(false);
  const requestSeqRef = useRef(0);
  const appendFailedPageRef = useRef<number | null>(null);
  const statusFilterRef = useRef<string | null>(null);
  const skipNextCriteriaReloadRef = useRef(true);

  statusFilterRef.current = statusFilter;
  const filtersActive = statusFilter != null;

  const clearErrors = useCallback(() => {
    setError(null);
    setErrorStatus(undefined);
    setErrorSource(null);
    setErrorIsNetwork(false);
    setAppendFailedPage(null);
    appendFailedPageRef.current = null;
  }, []);

  const loadPage = useCallback(
    async (pageToLoad: number, mode: 'replace' | 'append' | 'refresh') => {
      if (!roleOk || !identityLinked) {
        setItems([]);
        setLoading(false);
        setRefreshing(false);
        setLoadingMore(false);
        inFlightRef.current = false;
        return;
      }

      if (inFlightRef.current) return;

      if (mode === 'append') {
        const retryPage = appendFailedPageRef.current;
        const isAppendRetry = retryPage != null && pageToLoad === retryPage;
        if (!isAppendRetry) {
          if (pageToLoad !== pageRef.current + 1) return;
          if (pageRef.current >= lastPageRef.current) return;
        }
      }

      inFlightRef.current = true;
      const requestSeq = ++requestSeqRef.current;
      let holdLoadingForSession = false;

      if (mode === 'refresh') setRefreshing(true);
      else if (mode === 'append') setLoadingMore(true);
      else setLoading(true);

      if (mode === 'replace') clearErrors();
      else {
        setError(null);
        setErrorStatus(undefined);
        setErrorSource(null);
        setErrorIsNetwork(false);
        if (mode === 'append') {
          appendFailedPageRef.current = null;
          setAppendFailedPage(null);
        }
      }

      try {
        const status = statusFilterRef.current;
        const result = await listClientShipmentRequests({
          page: pageToLoad,
          per_page: PAGE_SIZE,
          ...(status ? { status } : {}),
        });

        if (requestSeq !== requestSeqRef.current) return;

        pageRef.current = result.meta.currentPage;
        lastPageRef.current = result.meta.lastPage;
        setLastPage(result.meta.lastPage);
        setTotal(result.meta.total);
        clearErrors();

        if (mode === 'append') {
          setItems((prev) => {
            const seen = new Set(prev.map((s) => s.id));
            const next = [...prev];
            for (const row of result.requests) {
              if (!seen.has(row.id)) next.push(row);
            }
            hasItemsRef.current = next.length > 0;
            return next;
          });
        } else {
          hasItemsRef.current = result.requests.length > 0;
          setItems(result.requests);
        }
      } catch (e) {
        if (requestSeq !== requestSeqRef.current) return;
        const err = e as ClientShipmentRequestsApiError;
        if (err.statusCode === 401) {
          holdLoadingForSession = !hasItemsRef.current;
          return;
        }
        const message = err.message || 'Failed to load shipment requests';
        setError(message);
        setErrorStatus(err.statusCode);
        setErrorIsNetwork(Boolean(err.isNetwork));
        if (mode === 'append') {
          setErrorSource('append');
          appendFailedPageRef.current = pageToLoad;
          setAppendFailedPage(pageToLoad);
        } else if (mode === 'refresh') {
          setErrorSource('refresh');
        } else {
          setErrorSource('initial');
          hasItemsRef.current = false;
          setItems([]);
          setTotal(0);
          pageRef.current = 1;
          lastPageRef.current = 1;
        }
      } finally {
        if (requestSeq === requestSeqRef.current) {
          setRefreshing(false);
          setLoadingMore(false);
          setLoading(holdLoadingForSession ? true : false);
          inFlightRef.current = false;
        }
      }
    },
    [roleOk, identityLinked, clearErrors]
  );

  useEffect(() => {
    if (skipNextCriteriaReloadRef.current) {
      skipNextCriteriaReloadRef.current = false;
      return;
    }
    if (!roleOk || !identityLinked) return;
    requestSeqRef.current += 1;
    inFlightRef.current = false;
    pageRef.current = 1;
    lastPageRef.current = 1;
    appendFailedPageRef.current = null;
    setAppendFailedPage(null);
    void loadPage(1, 'replace');
  }, [statusFilter, roleOk, identityLinked, loadPage]);

  useFocusEffect(
    useCallback(() => {
      void loadPage(1, hasItemsRef.current ? 'refresh' : 'replace');
    }, [loadPage])
  );

  const openCreate = () => router.push('/request-form');
  const clearFilters = useCallback(() => setStatusFilter(null), []);

  if (!roleOk) {
    return (
      <UnavailableDomainScreen
        title="Requests"
        testID="requests-forbidden-role"
        icon={FileText}
        explanation="Shipment requests are only available for FleetX client accounts."
      />
    );
  }

  if (!identityLinked) {
    return (
      <UnavailableDomainScreen
        title="Requests"
        testID="requests-unlinked"
        icon={FileText}
        explanation="Your account is not linked to a company. Company-scoped shipment requests cannot be loaded. Self-service company linking is not available in this app."
      />
    );
  }

  const showRefreshError = Boolean(error && errorSource === 'refresh' && items.length > 0);
  const showAppendError = Boolean(error && errorSource === 'append' && items.length > 0);
  const canRetryForbidden = errorStatus !== 403;
  const showInitialError = Boolean(error && errorSource === 'initial' && items.length === 0);
  const showInitialLoading = Boolean(loading && items.length === 0 && !error);
  const isForbidden = errorStatus === 403;

  const filtersBlock = (
    <View style={styles.filtersBlock} testID="request-filters">
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={styles.statusChipsRow}
        testID="request-status-filters">
        <Pressable
          testID="request-status-all"
          onPress={() => setStatusFilter(null)}
          style={[
            styles.statusChip,
            {
              borderColor: theme.border,
              backgroundColor: statusFilter == null ? theme.primary : theme.backgroundElement,
            },
          ]}
          accessibilityRole="button"
          accessibilityState={{ selected: statusFilter == null }}
          accessibilityLabel="All request statuses">
          <Text
            style={[
              styles.statusChipText,
              { color: statusFilter == null ? '#FFFFFF' : theme.text },
            ]}>
            All
          </Text>
        </Pressable>
        {CLIENT_REQUEST_STATUS_FILTERS.map((name) => {
          const selected = statusFilter === name;
          const chipLabel = formatRequestStatusLabel(name);
          return (
            <Pressable
              key={name}
              testID={`request-status-filter-${name}`}
              onPress={() => setStatusFilter(name)}
              style={[
                styles.statusChip,
                {
                  borderColor: theme.border,
                  backgroundColor: selected ? theme.primary : theme.backgroundElement,
                },
              ]}
              accessibilityRole="button"
              accessibilityState={{ selected }}
              accessibilityLabel={`Filter requests by status ${chipLabel}`}>
              <Text
                style={[styles.statusChipText, { color: selected ? '#FFFFFF' : theme.text }]}
                numberOfLines={1}>
                {chipLabel}
              </Text>
            </Pressable>
          );
        })}
      </ScrollView>

      {filtersActive ? (
        <View style={styles.activeFiltersRow} testID="request-active-filters">
          <Text style={[styles.activeFiltersText, { color: theme.textSecondary }]}>
            Status {formatRequestStatusLabel(statusFilter)}
          </Text>
          <Pressable
            testID="request-filters-reset"
            onPress={clearFilters}
            style={styles.resetFiltersHit}
            accessibilityRole="button"
            accessibilityLabel="Clear request status filter">
            <Text style={{ color: theme.primary, fontWeight: '700', fontSize: 13 }}>
              Clear filter
            </Text>
          </Pressable>
        </View>
      ) : null}
    </View>
  );

  return (
    <GradientBackground scrollable={false}>
      <View style={styles.screenBody} testID="requests-screen">
        <View style={styles.header}>
          <View style={styles.headerText}>
            <Text style={[styles.title, { color: theme.text }]} testID="requests-title">
              Requests
            </Text>
            <Text style={[styles.subtitle, { color: theme.textSecondary }]} testID="requests-subtitle">
              {total === 1 ? '1 shipment request' : `${total} shipment requests`}
            </Text>
          </View>
          <Pressable
            testID="requests-create"
            onPress={openCreate}
            style={[styles.createBtn, { backgroundColor: theme.primary }]}
            accessibilityRole="button"
            accessibilityLabel="Create shipment request">
            <Plus size={18} color="#FFFFFF" />
            <Text style={styles.createBtnText}>New</Text>
          </Pressable>
        </View>

        {filtersBlock}

        {showRefreshError ? (
          <View
            style={[styles.inlineError, { borderColor: 'rgba(239, 68, 68, 0.35)' }]}
            testID="requests-inline-error">
            <Text style={[styles.inlineErrorText, { color: theme.textSecondary }]}>
              {errorIsNetwork ? 'Could not refresh — check your connection.' : error}
            </Text>
            {canRetryForbidden ? (
              <Pressable
                testID="requests-retry"
                onPress={() => void loadPage(1, 'refresh')}
                hitSlop={8}
                accessibilityRole="button"
                accessibilityLabel="Retry refresh">
                <Text style={{ color: theme.primary, fontWeight: '700' }}>Retry</Text>
              </Pressable>
            ) : null}
          </View>
        ) : null}

        {showInitialLoading ? (
          <View style={styles.center} testID="requests-loading">
            <ActivityIndicator color={theme.primary} />
            <Text style={[styles.loadingText, { color: theme.textSecondary }]}>
              Loading requests…
            </Text>
          </View>
        ) : showInitialError ? (
          <View style={styles.centerPad} testID="requests-error">
            {isForbidden ? <View testID="requests-forbidden" /> : null}
            <Text style={[styles.errorTitle, { color: theme.text }]}>
              {isForbidden
                ? 'Access unavailable'
                : errorIsNetwork
                  ? 'Connection problem'
                  : 'Could not load requests'}
            </Text>
            <Text style={[styles.errorBody, { color: theme.textSecondary }]}>{error}</Text>
            {!isForbidden ? (
              <Pressable
                testID="requests-retry"
                style={[styles.retryBtn, { backgroundColor: theme.primary }]}
                onPress={() => void loadPage(1, 'replace')}
                accessibilityRole="button"
                accessibilityLabel="Retry loading requests">
                <Text style={styles.retryText}>Retry</Text>
              </Pressable>
            ) : null}
          </View>
        ) : (
          <FlatList
            testID="requests-list"
            style={styles.listFlex}
            data={items}
            keyExtractor={(item) => item.id}
            contentContainerStyle={items.length === 0 ? styles.emptyList : styles.list}
            refreshControl={
              <RefreshControl
                testID="requests-refresh"
                refreshing={refreshing}
                onRefresh={() => void loadPage(1, 'refresh')}
                tintColor={theme.primary}
                enabled={!loading && !loadingMore}
              />
            }
            ListEmptyComponent={
              !loading && !error ? (
                <View
                  style={styles.centerPad}
                  testID={filtersActive ? 'requests-empty-filtered' : 'requests-empty'}>
                  <Text style={[styles.emptyTitle, { color: theme.text }]}>
                    {filtersActive
                      ? 'No requests match your current filter'
                      : 'No shipment requests yet'}
                  </Text>
                  <Text style={[styles.emptyBody, { color: theme.textSecondary }]}>
                    {filtersActive
                      ? 'Try another status or clear the filter to see more requests.'
                      : 'Create a shipment request to ask FleetX to arrange transportation. You can save a draft and submit when you are ready.'}
                  </Text>
                  {filtersActive ? (
                    <Pressable
                      testID="requests-empty-clear-filters"
                      style={[styles.retryBtn, { backgroundColor: theme.primary }]}
                      onPress={clearFilters}
                      accessibilityRole="button"
                      accessibilityLabel="Clear request status filter">
                      <Text style={styles.retryText}>Clear filter</Text>
                    </Pressable>
                  ) : (
                    <Pressable
                      testID="requests-empty-create"
                      style={[styles.retryBtn, { backgroundColor: theme.primary }]}
                      onPress={openCreate}
                      accessibilityRole="button"
                      accessibilityLabel="Create shipment request">
                      <Text style={styles.retryText}>Create Shipment Request</Text>
                    </Pressable>
                  )}
                </View>
              ) : null
            }
            renderItem={({ item }) => <RequestCard item={item} />}
            onEndReachedThreshold={0.35}
            onEndReached={() => {
              if (inFlightRef.current || loadingMore || refreshing || loading) return;
              if (appendFailedPageRef.current != null) return;
              if (pageRef.current >= lastPageRef.current) return;
              void loadPage(pageRef.current + 1, 'append');
            }}
            ListFooterComponent={
              loadingMore ? (
                <View style={styles.footerLoading} testID="requests-loading-more">
                  <ActivityIndicator color={theme.primary} />
                </View>
              ) : showAppendError ? (
                <View style={styles.footerError} testID="requests-load-more-error">
                  <Text style={[styles.footerErrorText, { color: theme.textSecondary }]}>
                    {errorIsNetwork
                      ? 'Could not load more — check your connection.'
                      : 'Could not load more requests.'}
                  </Text>
                  {canRetryForbidden && appendFailedPage != null ? (
                    <Pressable
                      testID="requests-load-more-retry"
                      onPress={() => void loadPage(appendFailedPage, 'append')}
                      accessibilityRole="button"
                      accessibilityLabel="Retry loading more requests">
                      <Text style={{ color: theme.primary, fontWeight: '700' }}>Retry</Text>
                    </Pressable>
                  ) : null}
                </View>
              ) : lastPage > 1 && items.length > 0 ? (
                <Text style={[styles.footerHint, { color: theme.textSecondary }]}>
                  Showing {items.length} of {total}
                </Text>
              ) : null
            }
          />
        )}
      </View>
    </GradientBackground>
  );
}

const styles = StyleSheet.create({
  screenBody: { flex: 1 },
  listFlex: { flex: 1 },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 8,
    marginBottom: 12,
    gap: 12,
  },
  headerText: { flex: 1 },
  title: { fontSize: 22, fontWeight: '700' },
  subtitle: { fontSize: 13, marginTop: 4, fontWeight: '500' },
  createBtn: {
    minHeight: 44,
    paddingHorizontal: 14,
    borderRadius: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  createBtnText: { color: '#FFFFFF', fontWeight: '700', fontSize: 14 },
  filtersBlock: {
    marginBottom: 10,
    gap: 10,
  },
  statusChipsRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 2,
  },
  statusChip: {
    borderWidth: 1.5,
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 8,
    minHeight: 40,
    justifyContent: 'center',
  },
  statusChipText: {
    fontSize: 12,
    fontWeight: '700',
  },
  activeFiltersRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
  },
  activeFiltersText: {
    flex: 1,
    fontSize: 12,
    lineHeight: 16,
    fontWeight: '500',
  },
  resetFiltersHit: {
    minHeight: 40,
    justifyContent: 'center',
    paddingHorizontal: 2,
  },
  card: {
    borderWidth: 1.5,
    borderRadius: 16,
    padding: 14,
    marginBottom: 10,
    minHeight: 44,
  },
  cardTop: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 6,
  },
  requestNumber: { fontSize: 15, fontWeight: '700', flex: 1 },
  statusPill: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 999,
    maxWidth: 150,
  },
  statusPillText: { fontSize: 11, fontWeight: '700' },
  routeText: { fontSize: 13, lineHeight: 18, marginBottom: 4 },
  metaText: { fontSize: 12, marginTop: 2 },
  convertedRow: {
    marginTop: 6,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    minHeight: 44,
  },
  openShipmentText: { fontSize: 13, fontWeight: '700' },
  list: { paddingBottom: 24 },
  emptyList: { flexGrow: 1, justifyContent: 'center', paddingBottom: 24 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 12 },
  centerPad: { paddingVertical: 28, alignItems: 'center', paddingHorizontal: 8 },
  loadingText: { fontSize: 13 },
  errorTitle: { fontSize: 16, fontWeight: '700', marginBottom: 8, textAlign: 'center' },
  errorBody: { fontSize: 13, lineHeight: 18, textAlign: 'center', marginBottom: 16 },
  emptyTitle: { fontSize: 16, fontWeight: '700', marginBottom: 8, textAlign: 'center' },
  emptyBody: { fontSize: 13, lineHeight: 18, textAlign: 'center', marginBottom: 16 },
  retryBtn: {
    minHeight: 48,
    paddingHorizontal: 18,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  retryText: { color: '#FFFFFF', fontWeight: '700', fontSize: 14 },
  inlineError: {
    borderWidth: 1,
    borderRadius: 12,
    padding: 12,
    marginBottom: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  inlineErrorText: { flex: 1, fontSize: 12, lineHeight: 17 },
  footerLoading: { paddingVertical: 16, alignItems: 'center' },
  footerError: { paddingVertical: 12, alignItems: 'center', gap: 8 },
  footerErrorText: { fontSize: 12, textAlign: 'center' },
  footerHint: { fontSize: 12, textAlign: 'center', paddingVertical: 12 },
});
