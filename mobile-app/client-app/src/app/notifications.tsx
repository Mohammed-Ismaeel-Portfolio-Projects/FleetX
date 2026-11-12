// @ts-nocheck — lucide icon props vs RN ColorValue
/**
 * C2.8-D2 / C2.9-C3.8 — Client Notifications inbox.
 * Contract: GET/PATCH/POST /api/v1/client/notifications* only.
 * Destinations come only from resolveClientNotificationDestination.
 */
import { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { Redirect, useFocusEffect, useRouter } from 'expo-router';
import { Bell, ChevronLeft } from 'lucide-react-native';

import { GradientBackground } from '@/components/product';
import { UnavailableDomainScreen } from '@/components/unavailable-domain';
import { useTheme } from '@/hooks/use-theme';
import { useAuth } from '@/lib/auth/AuthContext';
import {
  ClientNotificationsApiError,
  formatNotificationCreatedAt,
  formatNotificationTypeLabel,
  getClientNotificationsUnreadCount,
  listClientNotifications,
  markAllClientNotificationsRead,
  markClientNotificationRead,
} from '@/features/notifications/notificationsApi';
import {
  resolveClientNotificationDestination,
  type ClientNotificationDestination,
} from '@/features/notifications/notificationDestination';
import type { ClientNotification } from '@/features/notifications/types';

const PAGE_SIZE = 15;

function NotificationCard({
  item,
  marking,
  onMarkRead,
  onOpenDestination,
}: {
  item: ClientNotification;
  marking: boolean;
  onMarkRead: (id: number) => void;
  onOpenDestination: (item: ClientNotification, destination: ClientNotificationDestination) => void;
}) {
  const theme = useTheme();
  const typeLabel = formatNotificationTypeLabel(item.type);
  const createdAt = formatNotificationCreatedAt(item.createdAt);
  const title = (item.title && String(item.title).trim()) || 'Notification';
  const message = (item.message && String(item.message).trim()) || '';
  const unread = !item.isRead;
  const destination = resolveClientNotificationDestination(item);
  const hasAction = destination.navigable;

  const a11yParts = [
    unread ? 'Unread notification' : 'Read notification',
    title,
    message || null,
    typeLabel ? `Type ${typeLabel}` : null,
    createdAt ? `Received ${createdAt}` : null,
    hasAction ? destination.ctaLabel : 'No further action available',
  ].filter(Boolean);

  const body = (
    <>
      <View style={styles.cardTop}>
        <View style={styles.titleBlock}>
          <View style={styles.titleRow}>
            {unread ? (
              <View
                testID={`notification-unread-${item.id}`}
                style={[styles.unreadDot, { backgroundColor: theme.primary }]}
                accessibilityElementsHidden
                importantForAccessibility="no"
              />
            ) : null}
            <Text
              testID={`notification-title-${item.id}`}
              style={[styles.cardTitle, { color: theme.text, fontWeight: unread ? '800' : '700' }]}
              numberOfLines={2}>
              {title}
            </Text>
          </View>
          <Text
            testID={`notification-read-label-${item.id}`}
            style={[styles.readLabel, { color: theme.textSecondary }]}
            accessibilityRole="text">
            {unread ? 'Unread' : 'Read'}
          </Text>
        </View>
        <View
          testID={`notification-type-${item.id}`}
          style={[styles.typePill, { backgroundColor: 'rgba(30, 117, 255, 0.12)' }]}
          accessibilityRole="text"
          accessibilityLabel={`Type ${typeLabel}`}>
          <Text style={[styles.typePillText, { color: theme.primary }]} numberOfLines={1}>
            {typeLabel}
          </Text>
        </View>
      </View>

      {item.type ? (
        <View
          testID={`notification-type-raw-${item.id}`}
          collapsable={false}
          style={styles.typeRawHidden}
          accessibilityElementsHidden
          importantForAccessibility="no"
        />
      ) : null}

      {message ? (
        <Text
          testID={`notification-message-${item.id}`}
          style={[styles.message, { color: theme.text }]}
          numberOfLines={4}>
          {message}
        </Text>
      ) : null}

      {createdAt ? (
        <Text
          testID={`notification-created-${item.id}`}
          style={[styles.createdAt, { color: theme.textSecondary }]}>
          {createdAt}
        </Text>
      ) : null}

      {hasAction ? (
        <Pressable
          testID={`notification-destination-${item.id}`}
          onPress={() => onOpenDestination(item, destination)}
          disabled={marking}
          hitSlop={8}
          accessibilityRole="button"
          accessibilityLabel={destination.ctaLabel}
          accessibilityHint="Opens the related customer screen"
          style={({ pressed }) => [
            styles.destinationCta,
            { opacity: pressed || marking ? 0.7 : 1 },
          ]}>
          <Text style={[styles.destinationCtaText, { color: theme.primary }]}>
            {destination.ctaLabel}
          </Text>
        </Pressable>
      ) : null}
    </>
  );

  if (unread) {
    return (
      <Pressable
        testID={`notification-item-${item.id}`}
        style={({ pressed }) => [
          styles.card,
          {
            backgroundColor: theme.backgroundElement,
            borderColor: theme.primary,
            opacity: pressed || marking ? 0.9 : 1,
            minHeight: 72,
          },
        ]}
        onPress={() => {
          if (!marking) onMarkRead(item.id);
        }}
        disabled={marking}
        accessibilityRole="button"
        accessibilityState={{ disabled: marking, busy: marking }}
        accessibilityLabel={a11yParts.join('. ')}
        accessibilityHint="Marks this notification as read">
        {body}
        {marking ? (
          <View style={styles.cardBusy} testID={`notification-mark-read-${item.id}`}>
            <ActivityIndicator color={theme.primary} size="small" />
            <Text style={[styles.cardBusyText, { color: theme.textSecondary }]}>Marking read…</Text>
          </View>
        ) : (
          <View testID={`notification-mark-read-${item.id}`} collapsable={false} />
        )}
      </Pressable>
    );
  }

  // Already read — destination CTA still uses the resolver; no invented links.
  return (
    <View
      testID={`notification-item-${item.id}`}
      style={[
        styles.card,
        {
          backgroundColor: theme.backgroundElement,
          borderColor: theme.border,
          minHeight: 72,
        },
      ]}
      accessibilityRole="summary"
      accessibilityLabel={a11yParts.join('. ')}>
      {body}
    </View>
  );
}

export default function NotificationsScreen() {
  const theme = useTheme();
  const router = useRouter();
  const { ready, isAuthenticated, roleOk, identityLinked } = useAuth();

  const [items, setItems] = useState<ClientNotification[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [unreadCount, setUnreadCount] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [loadingMore, setLoadingMore] = useState(false);
  const [markingAll, setMarkingAll] = useState(false);
  const [markingId, setMarkingId] = useState<number | null>(null);
  const [mutationError, setMutationError] = useState<string | null>(null);
  const [failedMarkId, setFailedMarkId] = useState<number | null>(null);

  const [error, setError] = useState<string | null>(null);
  const [errorStatus, setErrorStatus] = useState<number | undefined>(undefined);
  const [errorSource, setErrorSource] = useState<'initial' | 'refresh' | 'append' | null>(null);
  const [errorIsNetwork, setErrorIsNetwork] = useState(false);
  const [appendFailedPage, setAppendFailedPage] = useState<number | null>(null);

  const inFlightRef = useRef(false);
  const pageRef = useRef(1);
  const lastPageRef = useRef(1);
  const hasItemsRef = useRef(false);
  const requestSeqRef = useRef(0);
  const unreadSeqRef = useRef(0);
  const appendFailedPageRef = useRef<number | null>(null);
  const itemsRef = useRef<ClientNotification[]>([]);
  const markInFlightRef = useRef<Set<number>>(new Set());
  const markSeqByIdRef = useRef<Record<number, number>>({});
  const markAllInFlightRef = useRef(false);
  const markAllSeqRef = useRef(0);
  const mountedRef = useRef(true);

  itemsRef.current = items;

  useEffect(() => {
    mountedRef.current = true;
    return () => {
      mountedRef.current = false;
      // Invalidate in-flight list/unread/mutations on unmount.
      requestSeqRef.current += 1;
      unreadSeqRef.current += 1;
      markAllSeqRef.current += 1;
    };
  }, []);

  const clearErrors = useCallback(() => {
    setError(null);
    setErrorStatus(undefined);
    setErrorSource(null);
    setErrorIsNetwork(false);
    setAppendFailedPage(null);
    appendFailedPageRef.current = null;
  }, []);

  const applyUnreadCount = useCallback((count: number, seq: number) => {
    if (!mountedRef.current) return;
    if (seq !== unreadSeqRef.current) return;
    const safe = Number.isFinite(count) ? Math.max(0, Math.floor(count)) : 0;
    setUnreadCount(safe);
  }, []);

  const refreshUnreadCount = useCallback(async () => {
    if (!roleOk || !identityLinked) return;
    const seq = ++unreadSeqRef.current;
    try {
      const count = await getClientNotificationsUnreadCount();
      applyUnreadCount(count, seq);
    } catch {
      // Non-fatal: do not invent counts; leave last known server-aligned value.
    }
  }, [roleOk, identityLinked, applyUnreadCount]);

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

      // Refresh/replace invalidates in-flight append and discards stale responses via seq.
      if (mode === 'refresh' || mode === 'replace') {
        if (inFlightRef.current) {
          requestSeqRef.current += 1;
          inFlightRef.current = false;
        }
        appendFailedPageRef.current = null;
        setAppendFailedPage(null);
      } else if (inFlightRef.current) {
        return;
      }

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

      // Never wipe known-good rows before a successful refresh response.
      if (mode === 'replace') {
        clearErrors();
        setMutationError(null);
        setFailedMarkId(null);
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
        const result = await listClientNotifications({
          page: pageToLoad,
          per_page: PAGE_SIZE,
        });

        if (!mountedRef.current || requestSeq !== requestSeqRef.current) return;

        pageRef.current = result.meta.currentPage;
        lastPageRef.current = result.meta.lastPage;
        setPage(result.meta.currentPage);
        setLastPage(result.meta.lastPage);
        setTotal(result.meta.total);
        clearErrors();

        if (mode === 'append') {
          setItems((prev) => {
            const seen = new Set(prev.map((n) => n.id));
            const next = [...prev];
            for (const row of result.notifications) {
              if (!seen.has(row.id)) next.push(row);
            }
            hasItemsRef.current = next.length > 0;
            return next;
          });
        } else {
          // replace + refresh: swap with server page 1; restart pagination from page 1.
          hasItemsRef.current = result.notifications.length > 0;
          setItems(result.notifications);
        }

        void refreshUnreadCount();
      } catch (e) {
        if (!mountedRef.current || requestSeq !== requestSeqRef.current) return;

        const err = e as ClientNotificationsApiError;

        // 401: AuthContext / interceptor — do not reinterpret as empty inbox.
        if (err.statusCode === 401) {
          holdLoadingForSession = !hasItemsRef.current;
          return;
        }

        const message = err.message || 'Failed to load notifications';
        setError(message);
        setErrorStatus(err.statusCode);
        setErrorIsNetwork(Boolean(err.isNetwork));

        if (mode === 'append') {
          setErrorSource('append');
          appendFailedPageRef.current = pageToLoad;
          setAppendFailedPage(pageToLoad);
        } else if (mode === 'refresh') {
          // Preserve existing rows on refresh failure.
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
    [roleOk, identityLinked, clearErrors, refreshUnreadCount]
  );

  useFocusEffect(
    useCallback(() => {
      // Re-entry: refresh in place when rows exist; otherwise full replace. No polling.
      void loadPage(1, hasItemsRef.current ? 'refresh' : 'replace');
    }, [loadPage])
  );

  const handleMarkRead = useCallback(
    async (id: number) => {
      if (markInFlightRef.current.has(id)) return;
      const target = itemsRef.current.find((n) => n.id === id);
      // Already-read must not trigger PATCH.
      if (!target || target.isRead) return;

      markInFlightRef.current.add(id);
      const mySeq = (markSeqByIdRef.current[id] ?? 0) + 1;
      markSeqByIdRef.current[id] = mySeq;
      setMarkingId(id);
      setMutationError(null);
      setFailedMarkId(null);

      try {
        const updated = await markClientNotificationRead(id);
        if (!mountedRef.current) return;
        if (markSeqByIdRef.current[id] !== mySeq) return;
        // Stale/wrong payload must never mutate another row.
        if (Number(updated.id) !== Number(id)) return;

        setItems((prev) =>
          prev.map((n) =>
            n.id === id
              ? {
                  ...n,
                  ...updated,
                  id,
                  isRead: true,
                  action: updated.action ?? n.action,
                }
              : n
          )
        );
        setUnreadCount((prev) => (prev == null ? prev : Math.max(0, prev - 1)));
        void refreshUnreadCount();
      } catch (e) {
        if (!mountedRef.current) return;
        if (markSeqByIdRef.current[id] !== mySeq) return;
        const err = e as ClientNotificationsApiError;
        if (err.statusCode === 401) return;
        // Keep server-consistent unread state; do not flip isRead locally.
        setMutationError(err.message || 'Could not mark as read');
        setFailedMarkId(id);
      } finally {
        markInFlightRef.current.delete(id);
        if (mountedRef.current) {
          setMarkingId((current) => (current === id ? null : current));
        }
      }
    },
    [refreshUnreadCount]
  );

  const handleOpenDestination = useCallback(
    (item: ClientNotification, destination: ClientNotificationDestination) => {
      if (!destination.navigable) return;
      if (!item.isRead) {
        void handleMarkRead(item.id);
      }
      router.push(destination.href);
    },
    [handleMarkRead, router]
  );

  const handleMarkAllRead = useCallback(async () => {
    if (markAllInFlightRef.current) return;
    if (unreadCount != null && unreadCount <= 0) return;

    markAllInFlightRef.current = true;
    const mySeq = ++markAllSeqRef.current;
    setMarkingAll(true);
    setMutationError(null);
    setFailedMarkId(null);

    try {
      await markAllClientNotificationsRead();
      if (!mountedRef.current || mySeq !== markAllSeqRef.current) return;

      setItems((prev) => prev.map((n) => ({ ...n, isRead: true })));
      applyUnreadCount(0, ++unreadSeqRef.current);
      void refreshUnreadCount();
    } catch (e) {
      if (!mountedRef.current || mySeq !== markAllSeqRef.current) return;
      const err = e as ClientNotificationsApiError;
      if (err.statusCode === 401) return;
      setMutationError(err.message || 'Could not mark all as read');
    } finally {
      if (mySeq === markAllSeqRef.current) {
        markAllInFlightRef.current = false;
        if (mountedRef.current) setMarkingAll(false);
      }
    }
  }, [unreadCount, refreshUnreadCount, applyUnreadCount]);

  const goBack = () => {
    if (router.canGoBack()) {
      router.back();
      return;
    }
    router.replace('/(tabs)');
  };

  if (ready && !isAuthenticated) {
    return <Redirect href="/login" />;
  }

  if (!roleOk) {
    return (
      <UnavailableDomainScreen
        title="Notifications"
        testID="notifications-forbidden-role"
        icon={Bell}
        showBack
        explanation="Notifications are only available for FleetX client accounts. Your signed-in role cannot access this inbox."
      />
    );
  }

  if (!identityLinked) {
    return (
      <UnavailableDomainScreen
        title="Notifications"
        testID="notifications-unlinked"
        icon={Bell}
        showBack
        explanation="Your account is not linked to a company. Client notifications cannot be loaded until company linking is available."
      />
    );
  }

  const showRefreshError = Boolean(error && errorSource === 'refresh' && items.length > 0);
  const showAppendError = Boolean(error && errorSource === 'append' && items.length > 0);
  const canRetryForbidden = errorStatus !== 403;
  const showInitialError = Boolean(error && errorSource === 'initial' && items.length === 0);
  const showInitialLoading = Boolean(loading && items.length === 0 && !error);
  const isForbidden = errorStatus === 403;
  const showMarkAllAction = markingAll || (unreadCount != null && unreadCount > 0);

  const unreadSubtitle =
    unreadCount == null
      ? total === 1
        ? '1 notification'
        : `${total} notifications`
      : unreadCount === 0
        ? total === 1
          ? '1 notification · all read'
          : `${total} notifications · all read`
        : unreadCount === 1
          ? '1 unread'
          : `${unreadCount} unread`;

  return (
    <GradientBackground scrollable={false}>
      <View style={styles.screenBody} testID="notifications-screen">
        {/* 1–3. Title, unread summary, mark-all */}
        <View style={styles.headerRow}>
          <TouchableOpacity
            testID="notifications-back"
            style={[styles.backBtn, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}
            onPress={goBack}
            activeOpacity={0.7}
            accessibilityRole="button"
            accessibilityLabel="Go back"
            hitSlop={8}>
            <ChevronLeft size={20} color={theme.text} />
          </TouchableOpacity>
          <View style={styles.headerTextBlock}>
            <Text style={[styles.title, { color: theme.text }]} testID="notifications-title">
              Notifications
            </Text>
            <Text
              style={[styles.subtitle, { color: theme.textSecondary }]}
              testID="notifications-subtitle"
              accessibilityLabel={unreadSubtitle}>
              {unreadSubtitle}
            </Text>
          </View>
          {showMarkAllAction ? (
            <Pressable
              testID="notifications-mark-all-read"
              onPress={() => void handleMarkAllRead()}
              disabled={markingAll || (unreadCount != null && unreadCount <= 0)}
              hitSlop={8}
              accessibilityRole="button"
              accessibilityLabel="Mark all notifications as read"
              accessibilityState={{
                disabled: markingAll || (unreadCount != null && unreadCount <= 0),
                busy: markingAll,
              }}
              style={[styles.markAllHit, { minWidth: 44, minHeight: 44 }]}>
              {markingAll ? (
                <ActivityIndicator color={theme.primary} size="small" />
              ) : (
                <Text style={{ color: theme.primary, fontWeight: '700', fontSize: 13 }}>
                  Mark all read
                </Text>
              )}
            </Pressable>
          ) : (
            <View style={styles.markAllPlaceholder} />
          )}
        </View>

        {mutationError ? (
          <View
            style={[styles.inlineError, { borderColor: 'rgba(239, 68, 68, 0.35)' }]}
            testID="notifications-mutation-error"
            accessibilityRole="alert">
            <Text style={[styles.inlineErrorText, { color: theme.textSecondary }]}>
              {mutationError}
            </Text>
            {failedMarkId != null ? (
              <Pressable
                testID="notifications-mark-read-retry"
                onPress={() => void handleMarkRead(failedMarkId)}
                hitSlop={8}
                accessibilityRole="button"
                accessibilityLabel="Retry mark as read"
                style={styles.inlineRetryHit}>
                <Text style={{ color: theme.primary, fontWeight: '700' }}>Retry</Text>
              </Pressable>
            ) : null}
          </View>
        ) : null}

        {showRefreshError ? (
          <View
            style={[styles.inlineError, { borderColor: 'rgba(239, 68, 68, 0.35)' }]}
            testID="notifications-inline-error">
            <Text style={[styles.inlineErrorText, { color: theme.textSecondary }]}>
              {errorIsNetwork
                ? 'Could not refresh — check your connection.'
                : errorStatus === 403
                  ? 'Access unavailable for refresh.'
                  : error}
            </Text>
            {canRetryForbidden ? (
              <Pressable
                testID="notifications-retry"
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
          <View
            style={styles.center}
            testID="notifications-loading"
            accessibilityLabel="Loading notifications"
            accessibilityRole="progressbar">
            <ActivityIndicator color={theme.primary} />
            <Text style={[styles.loadingText, { color: theme.textSecondary }]}>
              Loading notifications…
            </Text>
          </View>
        ) : showInitialError ? (
          <View style={styles.centerPad} testID="notifications-error">
            {isForbidden ? (
              <View testID="notifications-forbidden" style={styles.forbiddenMarker} />
            ) : null}
            <Text style={[styles.errorTitle, { color: theme.text }]}>
              {isForbidden
                ? 'Access unavailable'
                : errorIsNetwork
                  ? 'Connection problem'
                  : 'Could not load notifications'}
            </Text>
            <Text style={[styles.errorBody, { color: theme.textSecondary }]}>
              {isForbidden
                ? 'You do not have access to notifications for this account.'
                : error}
            </Text>
            {!isForbidden ? (
              <Pressable
                testID="notifications-retry"
                style={[styles.retryBtn, { backgroundColor: theme.primary }]}
                onPress={() => void loadPage(1, 'replace')}
                accessibilityRole="button"
                accessibilityLabel="Retry loading notifications">
                <Text style={styles.retryText}>Retry</Text>
              </Pressable>
            ) : null}
          </View>
        ) : (
          <FlatList
            testID="notifications-list"
            style={styles.listFlex}
            data={items}
            keyExtractor={(item) => String(item.id)}
            contentContainerStyle={items.length === 0 ? styles.emptyList : styles.list}
            refreshControl={
              <RefreshControl
                testID="notifications-refresh"
                refreshing={refreshing}
                onRefresh={() => void loadPage(1, 'refresh')}
                tintColor={theme.primary}
                enabled={!loading}
                accessibilityLabel="Pull to refresh notifications"
              />
            }
            ListEmptyComponent={
              !loading && !error ? (
                <View style={styles.centerPad} testID="notifications-empty">
                  <Text style={[styles.emptyTitle, { color: theme.text }]}>No notifications yet</Text>
                  <Text style={[styles.emptyBody, { color: theme.textSecondary }]}>
                    When FleetX sends updates for your account, they will appear here. This is an
                    empty inbox, not an unavailable feature.
                  </Text>
                </View>
              ) : null
            }
            renderItem={({ item }) => (
              <NotificationCard
                item={item}
                marking={markingId === item.id}
                onMarkRead={(nid) => void handleMarkRead(nid)}
                onOpenDestination={handleOpenDestination}
              />
            )}
            onEndReachedThreshold={0.35}
            onEndReached={() => {
              if (inFlightRef.current || loadingMore || refreshing || loading) return;
              if (appendFailedPageRef.current != null) return;
              if (pageRef.current >= lastPageRef.current) return;
              void loadPage(pageRef.current + 1, 'append');
            }}
            ListFooterComponent={
              loadingMore ? (
                <View style={styles.footerLoading} testID="notifications-loading-more">
                  <ActivityIndicator color={theme.primary} />
                </View>
              ) : showAppendError ? (
                <View style={styles.footerError} testID="notifications-load-more-error">
                  <Text style={[styles.footerErrorText, { color: theme.textSecondary }]}>
                    {errorIsNetwork
                      ? 'Could not load more — check your connection.'
                      : 'Could not load more notifications.'}
                  </Text>
                  {canRetryForbidden && appendFailedPage != null ? (
                    <Pressable
                      testID="notifications-load-more-retry"
                      style={[styles.footerRetryBtn, { borderColor: theme.primary, minHeight: 44 }]}
                      onPress={() => void loadPage(appendFailedPage, 'append')}
                      accessibilityRole="button"
                      accessibilityLabel="Retry loading more notifications">
                      <Text style={{ color: theme.primary, fontWeight: '700', fontSize: 13 }}>
                        Retry
                      </Text>
                    </Pressable>
                  ) : null}
                </View>
              ) : page > 0 && lastPage > 0 && items.length > 0 ? (
                <Text
                  style={[styles.pageHint, { color: theme.textSecondary }]}
                  testID="notifications-page-hint">
                  Page {page} of {lastPage}
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
  screenBody: {
    flex: 1,
    paddingHorizontal: 16,
    paddingTop: 8,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    marginBottom: 12,
  },
  backBtn: {
    width: 44,
    height: 44,
    borderRadius: 12,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerTextBlock: {
    flex: 1,
    minWidth: 0,
  },
  title: {
    fontSize: 22,
    fontWeight: '800',
  },
  subtitle: {
    marginTop: 2,
    fontSize: 13,
  },
  markAllHit: {
    minHeight: 44,
    justifyContent: 'center',
    alignItems: 'flex-end',
    paddingHorizontal: 4,
  },
  markAllPlaceholder: {
    width: 8,
  },
  inlineError: {
    borderWidth: 1,
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
    marginBottom: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  inlineErrorText: {
    flex: 1,
    fontSize: 13,
    lineHeight: 18,
  },
  inlineRetryHit: {
    paddingVertical: 8,
    paddingHorizontal: 8,
    minHeight: 44,
    justifyContent: 'center',
  },
  center: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
  },
  centerPad: {
    flexGrow: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 16,
    paddingVertical: 32,
    gap: 8,
  },
  loadingText: {
    fontSize: 14,
  },
  errorTitle: {
    fontSize: 18,
    fontWeight: '800',
    textAlign: 'center',
  },
  errorBody: {
    fontSize: 14,
    lineHeight: 20,
    textAlign: 'center',
    marginBottom: 8,
  },
  retryBtn: {
    marginTop: 8,
    paddingHorizontal: 18,
    paddingVertical: 12,
    borderRadius: 12,
    minWidth: 120,
    minHeight: 44,
    alignItems: 'center',
    justifyContent: 'center',
  },
  retryText: {
    color: '#FFFFFF',
    fontWeight: '700',
    fontSize: 14,
  },
  forbiddenMarker: {
    width: 1,
    height: 1,
    opacity: 0,
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: '800',
    textAlign: 'center',
  },
  emptyBody: {
    fontSize: 14,
    lineHeight: 20,
    textAlign: 'center',
  },
  listFlex: {
    flex: 1,
  },
  list: {
    paddingBottom: 24,
    gap: 10,
  },
  emptyList: {
    flexGrow: 1,
  },
  card: {
    borderWidth: 1,
    borderRadius: 14,
    padding: 14,
    marginBottom: 10,
  },
  cardTop: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 10,
  },
  titleBlock: {
    flex: 1,
    minWidth: 0,
  },
  titleRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 8,
  },
  unreadDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    marginTop: 6,
  },
  cardTitle: {
    flex: 1,
    fontSize: 16,
    lineHeight: 22,
  },
  readLabel: {
    marginTop: 4,
    fontSize: 12,
    fontWeight: '600',
  },
  typePill: {
    maxWidth: 120,
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  typePillText: {
    fontSize: 11,
    fontWeight: '700',
  },
  typeRawHidden: {
    width: 0,
    height: 0,
    overflow: 'hidden',
    opacity: 0,
  },
  destinationCta: {
    alignSelf: 'flex-start',
    marginTop: 10,
    minHeight: 44,
    minWidth: 44,
    justifyContent: 'center',
  },
  destinationCtaText: {
    fontSize: 14,
    fontWeight: '700',
  },
  message: {
    marginTop: 8,
    fontSize: 14,
    lineHeight: 20,
  },
  createdAt: {
    marginTop: 10,
    fontSize: 12,
  },
  cardBusy: {
    marginTop: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  cardBusyText: {
    fontSize: 12,
  },
  footerLoading: {
    paddingVertical: 16,
    alignItems: 'center',
  },
  footerError: {
    paddingVertical: 14,
    alignItems: 'center',
    gap: 8,
  },
  footerErrorText: {
    fontSize: 13,
    textAlign: 'center',
  },
  footerRetryBtn: {
    borderWidth: 1,
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 10,
    justifyContent: 'center',
  },
  pageHint: {
    textAlign: 'center',
    fontSize: 12,
    paddingVertical: 12,
  },
});
