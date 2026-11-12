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
  TextInput,
  View,
} from 'react-native';
import { useFocusEffect, useRouter } from 'expo-router';
import { Truck } from 'lucide-react-native';

import { GradientBackground } from '@/components/product';
import { UnavailableDomainScreen } from '@/components/unavailable-domain';
import { useTheme } from '@/hooks/use-theme';
import { useAuth } from '@/lib/auth/AuthContext';
import {
  ClientShipmentsApiError,
  formatPlaceSummary,
  listClientShipments,
} from '@/features/shipments/shipmentsApi';
import {
  CLIENT_SHIPMENT_PRIORITY_FILTERS,
  CLIENT_SHIPMENT_STATUS_FILTERS,
  customerFacingShipmentStatus,
  formatShipmentPriorityFilterLabel,
  type ClientShipmentPriorityFilter,
} from '@/features/shipments/shipmentWorkspace';
import type { ClientShipment } from '@/features/shipments/types';

const PAGE_SIZE = 15;
const SEARCH_DEBOUNCE_MS = 350;

/**
 * Exact shipment status.name values for API `status` (C3.11).
 * Aligned with SHIPMENT_STATUS / Client lifecycle — not legacy snake_case StatusesSeeder names.
 */
const EXACT_STATUS_FILTERS = CLIENT_SHIPMENT_STATUS_FILTERS;
const EXACT_PRIORITY_FILTERS = CLIENT_SHIPMENT_PRIORITY_FILTERS;

/** Presentation-only date display — does not invent ETA/duration/progress. */
function formatDisplayDate(value: string | null | undefined): string | null {
  if (value == null) return null;
  const trimmed = String(value).trim();
  if (!trimmed) return null;
  // Prefer calendar date portion of ISO timestamps; otherwise show API string as-is.
  const isoDate = trimmed.match(/^(\d{4}-\d{2}-\d{2})/);
  return isoDate ? isoDate[1] : trimmed;
}

/** Presentation-only priority label — does not invent severity semantics. */
function formatPriorityLabel(value: string | null | undefined): string | null {
  if (value == null) return null;
  const trimmed = String(value).trim();
  if (!trimmed) return null;
  return trimmed.charAt(0).toUpperCase() + trimmed.slice(1).toLowerCase();
}

/**
 * C2.6-C5-D5 — Shipment card navigates to /shipment-details/[id].
 * List data only; details screen fetches GET /client/shipments/{id}.
 */
function ShipmentCard({ item }: { item: ClientShipment }) {
  const theme = useTheme();
  const router = useRouter();
  const shipmentNumber = (item.shipmentNumber && String(item.shipmentNumber).trim()) || 'Shipment';
  const origin = formatPlaceSummary(item.pickupCompany, item.pickupCity, item.pickupCountry);
  const destination = formatPlaceSummary(
    item.deliveryCompany,
    item.deliveryCity,
    item.deliveryCountry
  );
  // Backend SoT remains status.name — customer formatter is display-only (C3.6/C3.10).
  const statusRaw = item.status?.name ?? null;
  const statusLabel = customerFacingShipmentStatus(statusRaw);
  const plannedPickup = formatDisplayDate(item.plannedPickupDate);
  const plannedDelivery = formatDisplayDate(item.plannedDeliveryDate);
  const actualPickup = formatDisplayDate(item.actualPickupDate);
  const actualDelivery = formatDisplayDate(item.actualDeliveryDate);
  const priorityLabel = formatPriorityLabel(item.priority);
  const companyName =
    item.customerCompany?.companyName && String(item.customerCompany.companyName).trim()
      ? String(item.customerCompany.companyName).trim()
      : null;

  const hasPlanned = Boolean(plannedPickup || plannedDelivery);
  const hasActual = Boolean(actualPickup || actualDelivery);

  const a11yParts = [
    shipmentNumber,
    statusLabel !== 'Unavailable' ? `Status ${statusLabel}` : 'Status unavailable',
    `Route ${origin} to ${destination}`,
    plannedPickup ? `Planned pickup ${plannedPickup}` : null,
    plannedDelivery ? `Planned delivery ${plannedDelivery}` : null,
    actualPickup ? `Actual pickup ${actualPickup}` : null,
    actualDelivery ? `Actual delivery ${actualDelivery}` : null,
    priorityLabel ? `Priority ${priorityLabel}` : null,
  ].filter(Boolean);

  const openDetails = () => {
    if (!item.id) return;
    router.push(`/shipment-details/${item.id}`);
  };

  return (
    <Pressable
      testID={`shipment-card-${item.id}`}
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
      accessibilityLabel={`Open details for ${a11yParts.join('. ')}`}
      accessibilityHint="Opens shipment details">
      {/* 1. Shipment number + 2. Status */}
      <View style={styles.cardTop}>
        <View style={styles.numberBlock}>
          <Text
            testID={`shipment-number-${item.id}`}
            style={[styles.shipmentNumber, { color: theme.text }]}
            numberOfLines={1}>
            {shipmentNumber}
          </Text>
          {companyName ? (
            <Text
              style={[styles.companyCaption, { color: theme.textSecondary }]}
              numberOfLines={1}
              testID={`shipment-company-${item.id}`}>
              {companyName}
            </Text>
          ) : null}
        </View>
        <View
          testID={`shipment-status-${item.id}`}
          style={[styles.statusPill, { backgroundColor: 'rgba(30, 117, 255, 0.15)' }]}
          accessibilityRole="text"
          accessibilityLabel={
            statusLabel !== 'Unavailable' ? `Status ${statusLabel}` : 'Status unavailable'
          }>
          <Text style={[styles.statusPillText, { color: theme.primary }]} numberOfLines={1}>
            {statusLabel}
          </Text>
      </View>
          </View>

      {/* 3. Origin → destination */}
      <Text
        testID={`shipment-route-${item.id}`}
        style={[styles.routeText, { color: theme.text }]}
        numberOfLines={2}>
        {origin}
        <Text style={[styles.routeArrow, { color: theme.textSecondary }]}> → </Text>
        {destination}
      </Text>

      {/* 4. Relevant dates — planned vs actual clearly labeled */}
      {hasPlanned || hasActual ? (
        <View testID={`shipment-dates-${item.id}`} style={styles.datesBlock}>
          {hasPlanned ? (
            <View style={styles.dateGroup}>
              <Text style={[styles.dateGroupLabel, { color: theme.textSecondary }]}>Planned</Text>
              <View style={styles.metaRow}>
                {plannedPickup ? (
                  <Text style={[styles.metaText, { color: theme.textSecondary }]}>
                    Pickup {plannedPickup}
                  </Text>
                ) : null}
                {plannedDelivery ? (
                  <Text style={[styles.metaText, { color: theme.textSecondary }]}>
                    Delivery {plannedDelivery}
                  </Text>
                ) : null}
            </View>
          </View>
          ) : null}
          {hasActual ? (
            <View style={styles.dateGroup}>
              <Text style={[styles.dateGroupLabel, { color: theme.textSecondary }]}>Actual</Text>
              <View style={styles.metaRow}>
                {actualPickup ? (
                  <Text style={[styles.metaText, { color: theme.textSecondary }]}>
                    Pickup {actualPickup}
                  </Text>
                ) : null}
                {actualDelivery ? (
                  <Text style={[styles.metaText, { color: theme.textSecondary }]}>
                    Delivery {actualDelivery}
                  </Text>
                ) : null}
            </View>
          </View>
          ) : null}
        </View>
      ) : null}

      {/* 5. Priority when API returns it */}
      {priorityLabel ? (
        <Text
          testID={`shipment-priority-${item.id}`}
          style={[styles.priorityText, { color: theme.textSecondary }]}>
          Priority: {priorityLabel}
        </Text>
      ) : null}
    </Pressable>
  );
}

/**
 * Shipments tab — REAL client list (C2.6-C5-A/B/C + C2.6-C5-D7-B search/status).
 * GET /api/v1/client/shipments. No details/create/tracking/Home/Messaging changes.
 */
export default function ShipmentsScreen() {
  const theme = useTheme();
  const { roleOk, identityLinked } = useAuth();

  const [items, setItems] = useState<ClientShipment[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [loadingMore, setLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [errorStatus, setErrorStatus] = useState<number | undefined>(undefined);
  /** Where the latest non-401 failure originated — drives distinct UX. */
  const [errorSource, setErrorSource] = useState<'initial' | 'refresh' | 'append' | null>(null);
  const [errorIsNetwork, setErrorIsNetwork] = useState(false);
  const [appendFailedPage, setAppendFailedPage] = useState<number | null>(null);

  /** Draft search text; API uses debounced appliedSearch only. */
  const [searchInput, setSearchInput] = useState('');
  const [appliedSearch, setAppliedSearch] = useState('');
  /** Exact statuses.name value, or null = no status filter. */
  const [statusFilter, setStatusFilter] = useState<string | null>(null);
  /** Exact priority contract value, or null = no priority filter. */
  const [priorityFilter, setPriorityFilter] = useState<ClientShipmentPriorityFilter | null>(null);

  const inFlightRef = useRef(false);
  const pageRef = useRef(1);
  const lastPageRef = useRef(1);
  const hasItemsRef = useRef(false);
  const requestSeqRef = useRef(0);
  const appendFailedPageRef = useRef<number | null>(null);
  const appliedSearchRef = useRef('');
  const statusFilterRef = useRef<string | null>(null);
  const priorityFilterRef = useRef<ClientShipmentPriorityFilter | null>(null);
  const skipNextCriteriaReloadRef = useRef(true);

  appliedSearchRef.current = appliedSearch;
  statusFilterRef.current = statusFilter;
  priorityFilterRef.current = priorityFilter;

  const filtersActive =
    Boolean(appliedSearch) || statusFilter != null || priorityFilter != null;

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

      // Prevent duplicate concurrent fetches (focus + refresh + endReached races).
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

      // Clear only the error surface being retried; never blank cards on refresh.
      if (mode === 'replace') {
        clearErrors();
      } else if (mode === 'refresh') {
        setError(null);
        setErrorStatus(undefined);
        setErrorSource(null);
        setErrorIsNetwork(false);
      } else if (mode === 'append') {
        appendFailedPageRef.current = null;
        setAppendFailedPage(null);
        setError(null);
        setErrorStatus(undefined);
        setErrorSource(null);
        setErrorIsNetwork(false);
      }

      try {
        const search = appliedSearchRef.current.trim();
        const status = statusFilterRef.current;
        const priority = priorityFilterRef.current;
        const result = await listClientShipments({
          page: pageToLoad,
          per_page: PAGE_SIZE,
          ...(search ? { search } : {}),
          ...(status ? { status } : {}),
          ...(priority ? { priority } : {}),
          sort_by: 'created_at',
          sort_direction: 'desc',
        });

        if (requestSeq !== requestSeqRef.current) return;

        pageRef.current = result.meta.currentPage;
        lastPageRef.current = result.meta.lastPage;
        setPage(result.meta.currentPage);
        setLastPage(result.meta.lastPage);
        setTotal(result.meta.total);
        clearErrors();

        if (mode === 'append') {
          setItems((prev) => {
            const seen = new Set(prev.map((s) => s.id));
            const next = [...prev];
            for (const row of result.shipments) {
              if (!seen.has(row.id)) next.push(row);
            }
            hasItemsRef.current = next.length > 0;
            return next;
          });
        } else {
          // replace + refresh: swap with server page 1; never invent placeholders
          hasItemsRef.current = result.shipments.length > 0;
          setItems(result.shipments);
        }
      } catch (e) {
        if (requestSeq !== requestSeqRef.current) return;

        const err = e as ClientShipmentsApiError;

        // 401: shared API interceptor + AuthContext clear session / redirect — do not reinterpret as empty list.
        if (err.statusCode === 401) {
          holdLoadingForSession = !hasItemsRef.current;
          return;
        }

        const message = err.message || 'Failed to load shipments';
        setError(message);
        setErrorStatus(err.statusCode);
        setErrorIsNetwork(Boolean(err.isNetwork));

        if (mode === 'append') {
          // Preserve already loaded shipments; local load-more error only.
          setErrorSource('append');
          appendFailedPageRef.current = pageToLoad;
          setAppendFailedPage(pageToLoad);
        } else if (mode === 'refresh') {
          // Preserve existing rows on refresh failure; clear only on initial replace.
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

  // Debounce search draft → applied API `search` (avoids per-keystroke requests).
  useEffect(() => {
    const handle = setTimeout(() => {
      setAppliedSearch(searchInput.trim());
    }, SEARCH_DEBOUNCE_MS);
    return () => clearTimeout(handle);
  }, [searchInput]);

  // Criteria change: reset pagination, invalidate in-flight, reload page 1.
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
  }, [appliedSearch, statusFilter, priorityFilter, roleOk, identityLinked, loadPage]);

  useFocusEffect(
    useCallback(() => {
      // Re-focus: refresh in place when list already has rows; otherwise full replace/loading.
      void loadPage(1, hasItemsRef.current ? 'refresh' : 'replace');
    }, [loadPage])
  );

  const clearFilters = useCallback(() => {
    setSearchInput('');
    setAppliedSearch('');
    setStatusFilter(null);
    setPriorityFilter(null);
  }, []);

  if (!roleOk) {
    return (
      <UnavailableDomainScreen
        title="Shipments"
        testID="shipments-forbidden-role"
        icon={Truck}
        explanation="Shipments are only available for FleetX client accounts. Your signed-in role cannot access this list."
      />
    );
  }

  if (!identityLinked) {
    return (
      <UnavailableDomainScreen
        title="Shipments"
        testID="shipments-unlinked"
        icon={Truck}
        explanation="Your account is not linked to a company. Company-scoped shipments cannot be loaded. Self-service company linking is not available in this app."
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
    <View style={styles.filtersBlock} testID="shipment-filters">
      <View
        style={[
          styles.searchRow,
          { backgroundColor: theme.backgroundElement, borderColor: theme.border },
        ]}>
        <TextInput
          testID="shipment-search-input"
          style={[styles.searchInput, { color: theme.text }]}
          value={searchInput}
          onChangeText={setSearchInput}
          placeholder="Search shipments"
          placeholderTextColor={theme.textSecondary}
          autoCapitalize="none"
          autoCorrect={false}
          returnKeyType="search"
          clearButtonMode="never"
          accessibilityLabel="Search shipments"
        />
        {searchInput.length > 0 || appliedSearch.length > 0 ? (
          <Pressable
            testID="shipment-search-clear"
            onPress={() => {
              setSearchInput('');
              setAppliedSearch('');
            }}
            hitSlop={8}
            accessibilityRole="button"
            accessibilityLabel="Clear search"
            style={styles.clearSearchHit}>
            <Text style={{ color: theme.primary, fontWeight: '700', fontSize: 13 }}>Clear</Text>
          </Pressable>
        ) : null}
        </View>

      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={styles.statusChipsRow}
        testID="shipment-status-filters">
        <Pressable
          testID="shipment-status-all"
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
          accessibilityLabel="All statuses">
          <Text
            style={[
              styles.statusChipText,
              { color: statusFilter == null ? '#FFFFFF' : theme.text },
            ]}>
            All
          </Text>
        </Pressable>
        {EXACT_STATUS_FILTERS.map((name) => {
          const selected = statusFilter === name;
          const chipLabel = customerFacingShipmentStatus(name);
          return (
            <Pressable
              key={name}
              testID={`shipment-status-filter-${name}`}
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
              accessibilityLabel={`Filter shipments by status ${chipLabel}`}
              accessibilityHint="Shows only shipments with this status">
              <Text
                style={[styles.statusChipText, { color: selected ? '#FFFFFF' : theme.text }]}
                numberOfLines={1}>
                {chipLabel}
              </Text>
            </Pressable>
          );
        })}
      </ScrollView>

      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={styles.statusChipsRow}
        testID="shipment-priority-filters">
        <Pressable
          testID="shipment-priority-all"
          onPress={() => setPriorityFilter(null)}
          style={[
            styles.statusChip,
            {
              borderColor: theme.border,
              backgroundColor: priorityFilter == null ? theme.primary : theme.backgroundElement,
            },
          ]}
          accessibilityRole="button"
          accessibilityState={{ selected: priorityFilter == null }}
          accessibilityLabel="All priorities">
          <Text
            style={[
              styles.statusChipText,
              { color: priorityFilter == null ? '#FFFFFF' : theme.text },
            ]}>
            All priorities
          </Text>
        </Pressable>
        {EXACT_PRIORITY_FILTERS.map((name) => {
          const selected = priorityFilter === name;
          const chipLabel = formatShipmentPriorityFilterLabel(name);
          return (
            <Pressable
              key={name}
              testID={`shipment-priority-filter-${name}`}
              onPress={() => setPriorityFilter(name)}
              style={[
                styles.statusChip,
                {
                  borderColor: theme.border,
                  backgroundColor: selected ? theme.primary : theme.backgroundElement,
                },
              ]}
              accessibilityRole="button"
              accessibilityState={{ selected }}
              accessibilityLabel={`Filter shipments by priority ${chipLabel}`}
              accessibilityHint="Shows only shipments with this priority">
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
        <View style={styles.activeFiltersRow} testID="shipment-active-filters">
          <Text style={[styles.activeFiltersText, { color: theme.textSecondary }]} numberOfLines={2}>
            {[
              appliedSearch ? `Search “${appliedSearch}”` : null,
              statusFilter ? `Status ${customerFacingShipmentStatus(statusFilter)}` : null,
              priorityFilter ? `Priority ${formatShipmentPriorityFilterLabel(priorityFilter)}` : null,
            ]
              .filter(Boolean)
              .join(' · ')}
          </Text>
          <Pressable
            testID="shipment-filters-reset"
            onPress={clearFilters}
            style={styles.resetFiltersHit}
            accessibilityRole="button"
            accessibilityLabel="Reset search, status, and priority filters">
            <Text style={{ color: theme.primary, fontWeight: '700', fontSize: 13 }}>
              Clear filters
            </Text>
          </Pressable>
        </View>
      ) : null}
        </View>
  );

  return (
    <GradientBackground scrollable={false}>
      <View style={styles.screenBody} testID="shipment-screen">
        <View style={styles.header}>
          <Text style={[styles.title, { color: theme.text }]} testID="shipments-title">
            Shipments
        </Text>
          <Text
            style={[styles.subtitle, { color: theme.textSecondary }]}
            testID="shipments-subtitle">
            {total === 1 ? '1 shipment' : `${total} shipments`} for your company
          </Text>
      </View>

        {filtersBlock}

        {showRefreshError ? (
          <View
            style={[styles.inlineError, { borderColor: 'rgba(239, 68, 68, 0.35)' }]}
            testID="shipment-inline-error">
            <Text style={[styles.inlineErrorText, { color: theme.textSecondary }]}>
              {errorIsNetwork
                ? 'Could not refresh — check your connection.'
                : errorStatus === 403
                  ? 'Access unavailable for refresh.'
                  : error}
            </Text>
            {canRetryForbidden ? (
              <Pressable
                testID="shipment-retry"
                onPress={() => void loadPage(1, 'refresh')}
                hitSlop={8}
                accessibilityRole="button"
                accessibilityLabel="Retry refresh"
                style={styles.inlineRetryHit}>
                <Text style={{ color: theme.primary, fontWeight: '700' }}>Retry</Text>
              </Pressable>
            ) : null}
        </View>
        ) : null}

        {showInitialLoading ? (
          <View style={styles.center} testID="shipment-loading">
            <ActivityIndicator color={theme.primary} />
            <Text style={[styles.loadingText, { color: theme.textSecondary }]}>
              Loading shipments…
            </Text>
          </View>
        ) : showInitialError ? (
          <View style={styles.centerPad} testID="shipment-error">
            {isForbidden ? <View testID="shipment-forbidden" style={styles.forbiddenMarker} /> : null}
            <Text style={[styles.errorTitle, { color: theme.text }]}>
              {isForbidden
                ? 'Access unavailable'
                : errorIsNetwork
                  ? 'Connection problem'
                  : 'Could not load shipments'}
            </Text>
            <Text style={[styles.errorBody, { color: theme.textSecondary }]}>{error}</Text>
            {!isForbidden ? (
              <Pressable
                testID="shipment-retry"
                style={[styles.retryBtn, { backgroundColor: theme.primary }]}
                onPress={() => void loadPage(1, 'replace')}
                accessibilityRole="button"
                accessibilityLabel="Retry loading shipments">
                <Text style={styles.retryText}>Retry</Text>
              </Pressable>
            ) : null}
          </View>
        ) : (
          <FlatList
            testID="shipment-list"
            style={styles.listFlex}
            data={items}
            keyExtractor={(item) => item.id}
            contentContainerStyle={items.length === 0 ? styles.emptyList : styles.list}
            refreshControl={
              <RefreshControl
                testID="shipment-refresh"
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
                  testID={filtersActive ? 'shipment-empty-filtered' : 'shipment-empty'}>
                  <Text style={[styles.emptyTitle, { color: theme.text }]}>
                    {filtersActive ? 'No shipments match your current filters' : 'No shipments yet'}
                  </Text>
                  <Text style={[styles.emptyBody, { color: theme.textSecondary }]}>
                    {filtersActive
                      ? 'Try clearing search, status, or priority filters to see more shipments.'
                      : 'When FleetX creates shipments for your company, they will appear here.'}
                  </Text>
                  {filtersActive ? (
                    <Pressable
                      testID="shipment-empty-clear-filters"
                      style={[styles.retryBtn, { backgroundColor: theme.primary, marginTop: 8 }]}
                      onPress={clearFilters}
                      accessibilityRole="button"
                      accessibilityLabel="Clear shipment filters">
                      <Text style={styles.retryText}>Clear filters</Text>
                    </Pressable>
                  ) : null}
        </View>
              ) : null
            }
            renderItem={({ item }) => <ShipmentCard item={item} />}
            onEndReachedThreshold={0.35}
            onEndReached={() => {
              if (inFlightRef.current || loadingMore || refreshing || loading) return;
              if (appendFailedPageRef.current != null) return;
              if (pageRef.current >= lastPageRef.current) return;
              void loadPage(pageRef.current + 1, 'append');
            }}
            ListFooterComponent={
              loadingMore ? (
                <View style={styles.footerLoading} testID="shipment-loading-more">
                  <ActivityIndicator color={theme.primary} />
      </View>
              ) : showAppendError ? (
                <View style={styles.footerError} testID="shipment-load-more-error">
                  <Text style={[styles.footerErrorText, { color: theme.textSecondary }]}>
                    {errorIsNetwork
                      ? 'Could not load more — check your connection.'
                      : 'Could not load more shipments.'}
                  </Text>
                  {canRetryForbidden && appendFailedPage != null ? (
                    <Pressable
                      testID="shipment-load-more-retry"
                      style={[styles.footerRetryBtn, { borderColor: theme.primary }]}
                      onPress={() => void loadPage(appendFailedPage, 'append')}
                      accessibilityRole="button"
                      accessibilityLabel="Retry loading more shipments">
                      <Text style={{ color: theme.primary, fontWeight: '700', fontSize: 13 }}>
                        Retry
                      </Text>
                    </Pressable>
                  ) : null}
                </View>
              ) : null
            }
          />
        )}
      </View>
    </GradientBackground>
  );
}

const styles = StyleSheet.create({
  screenRoot: {
    flex: 1,
  },
  screenBody: {
    flex: 1,
  },
  listFlex: {
    flex: 1,
  },
  header: {
    marginTop: 8,
    marginBottom: 8,
    paddingHorizontal: 4,
  },
  title: {
    fontSize: 22,
    fontWeight: '700',
  },
  subtitle: {
    fontSize: 13,
    marginTop: 4,
    fontWeight: '500',
  },
  filtersBlock: {
    paddingHorizontal: 4,
    marginBottom: 10,
    gap: 10,
  },
  searchRow: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1.5,
    borderRadius: 12,
    minHeight: 48,
    paddingHorizontal: 12,
    gap: 8,
  },
  searchInput: {
    flex: 1,
    fontSize: 15,
    paddingVertical: 10,
    minHeight: 44,
  },
  clearSearchHit: {
    minHeight: 44,
    minWidth: 44,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 4,
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
  resetFiltersHit: {
    minHeight: 40,
    alignSelf: 'flex-start',
    justifyContent: 'center',
    paddingHorizontal: 2,
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
  inlineError: {
    borderWidth: 1,
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
    marginBottom: 10,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
  },
  inlineErrorText: {
    flex: 1,
    fontSize: 12,
    lineHeight: 16,
  },
  inlineRetryHit: {
    minHeight: 44,
    minWidth: 44,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 4,
  },
  list: {
    paddingBottom: 24,
    gap: 12,
  },
  emptyList: {
    flexGrow: 1,
    justifyContent: 'center',
    paddingBottom: 40,
  },
  center: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 12,
  },
  centerPad: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 24,
  },
  forbiddenMarker: {
    width: 0,
    height: 0,
    overflow: 'hidden',
  },
  loadingText: {
    fontSize: 13,
  },
  errorTitle: {
    fontSize: 17,
    fontWeight: '700',
    marginBottom: 8,
    textAlign: 'center',
  },
  errorBody: {
    fontSize: 13,
    lineHeight: 18,
    textAlign: 'center',
    marginBottom: 16,
  },
  retryBtn: {
    minHeight: 48,
    minWidth: 140,
    paddingHorizontal: 20,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  retryText: {
    color: '#FFFFFF',
    fontWeight: '700',
    fontSize: 14,
  },
  emptyTitle: {
    fontSize: 17,
    fontWeight: '700',
    marginBottom: 8,
    textAlign: 'center',
  },
  emptyBody: {
    fontSize: 13,
    lineHeight: 18,
    textAlign: 'center',
  },
  card: {
    borderWidth: 1.5,
    borderRadius: 16,
    padding: 16,
    marginBottom: 12,
  },
  cardTop: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: 10,
    marginBottom: 4,
  },
  numberBlock: {
    flex: 1,
    minWidth: 0,
  },
  shipmentNumber: {
    fontSize: 17,
    fontWeight: '700',
  },
  companyCaption: {
    fontSize: 12,
    marginTop: 2,
    fontWeight: '500',
  },
  statusPill: {
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 5,
    maxWidth: '48%',
    marginTop: 2,
  },
  statusPillText: {
    fontSize: 11,
    fontWeight: '700',
  },
  routeText: {
    fontSize: 14,
    fontWeight: '600',
    lineHeight: 20,
    marginBottom: 10,
  },
  routeArrow: {
    fontWeight: '500',
  },
  datesBlock: {
    gap: 8,
    marginBottom: 2,
  },
  dateGroup: {
    gap: 2,
  },
  dateGroupLabel: {
    fontSize: 11,
    fontWeight: '700',
    letterSpacing: 0.3,
    textTransform: 'uppercase',
  },
  metaRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  metaText: {
    fontSize: 12,
  },
  priorityText: {
    fontSize: 12,
    marginTop: 8,
    fontWeight: '600',
  },
  footerLoading: {
    paddingVertical: 16,
    alignItems: 'center',
  },
  footerError: {
    paddingVertical: 16,
    paddingHorizontal: 12,
    alignItems: 'center',
    gap: 10,
  },
  footerErrorText: {
    fontSize: 12,
    textAlign: 'center',
    lineHeight: 16,
  },
  footerRetryBtn: {
    minHeight: 44,
    minWidth: 120,
    paddingHorizontal: 16,
    borderRadius: 12,
    borderWidth: 1.5,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
