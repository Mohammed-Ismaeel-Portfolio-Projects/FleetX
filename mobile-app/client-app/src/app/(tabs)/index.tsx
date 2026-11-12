// @ts-nocheck — lucide icon props vs RN ColorValue (same pattern as other product screens)
import React, { useCallback, useRef, useState } from 'react';
import {
  ActivityIndicator,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { useFocusEffect, useRouter } from 'expo-router';
import { Bell, ChevronRight, CircleAlert, FileText, MessageSquare, Truck } from 'lucide-react-native';
import { useTheme } from '@/hooks/use-theme';
import { GradientBackground } from '@/components/product';
import { useAuth } from '@/lib/auth/AuthContext';
import {
  getClientNotificationsUnreadCount,
  listClientNotifications,
  formatNotificationTypeLabel,
} from '@/features/notifications/notificationsApi';
import type { ClientNotification } from '@/features/notifications/types';
import { formatPlaceSummary, listClientShipments } from '@/features/shipments/shipmentsApi';
import { customerFacingShipmentStatus } from '@/features/shipments/shipmentWorkspace';
import type { ClientShipment } from '@/features/shipments/types';
import { listClientShipmentRequests } from '@/features/requests/requestsApi';
import type { ClientShipmentRequest } from '@/features/requests/types';
import { formatRequestStatusLabel } from '@/features/requests/requestStatus';
import {
  formatHomeDate,
  hasActionableRequest,
  homeEmptyHubCopy,
  selectActiveShipments,
  selectPendingRequests,
  shipmentWorkspaceIdForConvertedRequest,
} from '@/features/home/homeOverview';

type SectionStatus = 'idle' | 'loading' | 'ready' | 'error';

/**
 * C3.3 Client Home — operational overview from production client APIs.
 * Read-only. No Engine stages, no request workflow, no fake KPIs.
 * C3.13: request sequencing + notification preview recovery.
 */
export default function HomeDashboard() {
  const theme = useTheme();
  const router = useRouter();
  const { user, identity, identityLinked, roleOk } = useAuth();

  const displayName = user?.name?.split(' ')[0] || 'Client';
  const fullName = user?.name || 'Client';
  const companyName = identity?.company?.company_name ?? null;
  const companyCode = identity?.company?.company_code ?? null;
  const roleName = user?.role?.name ?? identity?.role ?? null;
  const canLoad = roleOk && identityLinked;

  const [unreadCount, setUnreadCount] = useState<number | null>(null);

  const [shipmentsStatus, setShipmentsStatus] = useState<SectionStatus>('idle');
  const [shipments, setShipments] = useState<ClientShipment[]>([]);
  const [shipmentsError, setShipmentsError] = useState<string | null>(null);

  const [requestsStatus, setRequestsStatus] = useState<SectionStatus>('idle');
  const [requests, setRequests] = useState<ClientShipmentRequest[]>([]);
  const [requestsError, setRequestsError] = useState<string | null>(null);

  const [notesStatus, setNotesStatus] = useState<SectionStatus>('idle');
  const [recentNotes, setRecentNotes] = useState<ClientNotification[]>([]);
  const [notesError, setNotesError] = useState<string | null>(null);

  /** C3.13 — supersede stale Home loads (focus / Retry races). */
  const requestSeqRef = useRef(0);
  const notesInFlightRef = useRef(false);

  const loadUnread = useCallback(async (requestSeq: number) => {
    if (!canLoad) {
      if (requestSeq !== requestSeqRef.current) return;
      setUnreadCount(null);
      return;
    }
    try {
      const count = await getClientNotificationsUnreadCount();
      if (requestSeq !== requestSeqRef.current) return;
      setUnreadCount(count);
    } catch {
      if (requestSeq !== requestSeqRef.current) return;
      setUnreadCount(null);
    }
  }, [canLoad]);

  const loadShipments = useCallback(async (requestSeq?: number) => {
    const seq = requestSeq ?? ++requestSeqRef.current;
    if (!canLoad) {
      if (seq !== requestSeqRef.current) return;
      setShipments([]);
      setShipmentsStatus('idle');
      setShipmentsError(null);
      return;
    }
    setShipmentsStatus((prev) => (prev === 'ready' ? prev : 'loading'));
    try {
      const result = await listClientShipments({
        page: 1,
        per_page: 20,
        sort_by: 'updated_at',
        sort_direction: 'desc',
      });
      if (seq !== requestSeqRef.current) return;
      setShipments(result.shipments);
      setShipmentsError(null);
      setShipmentsStatus('ready');
    } catch (e) {
      if (seq !== requestSeqRef.current) return;
      setShipmentsError(e instanceof Error ? e.message : 'Could not load shipments.');
      setShipmentsStatus('error');
    }
  }, [canLoad]);

  const loadRequests = useCallback(async (requestSeq?: number) => {
    const seq = requestSeq ?? ++requestSeqRef.current;
    if (!canLoad) {
      if (seq !== requestSeqRef.current) return;
      setRequests([]);
      setRequestsStatus('idle');
      setRequestsError(null);
      return;
    }
    setRequestsStatus((prev) => (prev === 'ready' ? prev : 'loading'));
    try {
      const result = await listClientShipmentRequests({ page: 1, per_page: 8 });
      if (seq !== requestSeqRef.current) return;
      setRequests(result.requests);
      setRequestsError(null);
      setRequestsStatus('ready');
    } catch (e) {
      if (seq !== requestSeqRef.current) return;
      setRequestsError(e instanceof Error ? e.message : 'Could not load requests.');
      setRequestsStatus('error');
    }
  }, [canLoad]);

  const loadRecentNotes = useCallback(async (requestSeq?: number) => {
    const seq = requestSeq ?? ++requestSeqRef.current;
    if (!canLoad) {
      if (seq !== requestSeqRef.current) return;
      setRecentNotes([]);
      setNotesStatus('idle');
      setNotesError(null);
      return;
    }
    notesInFlightRef.current = true;
    setNotesStatus((prev) => (prev === 'ready' ? prev : 'loading'));
    try {
      const result = await listClientNotifications({ page: 1, per_page: 3 });
      if (seq !== requestSeqRef.current) return;
      setRecentNotes(result.notifications);
      setNotesError(null);
      setNotesStatus('ready');
    } catch (e) {
      if (seq !== requestSeqRef.current) return;
      setRecentNotes([]);
      setNotesError(e instanceof Error ? e.message : 'Could not load recent notifications.');
      setNotesStatus('error');
    } finally {
      if (seq === requestSeqRef.current) {
        notesInFlightRef.current = false;
      }
    }
  }, [canLoad]);

  const loadAll = useCallback(async () => {
    const requestSeq = ++requestSeqRef.current;
    await Promise.allSettled([
      loadUnread(requestSeq),
      loadShipments(requestSeq),
      loadRequests(requestSeq),
      loadRecentNotes(requestSeq),
    ]);
  }, [loadUnread, loadShipments, loadRequests, loadRecentNotes]);

  useFocusEffect(
    useCallback(() => {
      void loadAll();
    }, [loadAll])
  );

  const retryRecentNotes = () => {
    void loadRecentNotes();
  };

  const openMessages = () => router.push('/messages');
  const openNotifications = () => router.push('/notifications');
  const openShipments = () => router.push('/(tabs)/shipments');
  const openRequests = () => router.push('/(tabs)/requests');
  const openShipment = (id: string) => router.push(`/shipment-details/${id}`);
  const openRequest = (id: string) => router.push(`/request-details/${id}`);

  const activeShipments = selectActiveShipments(shipments, 3);
  const pendingRequests = selectPendingRequests(requests, 3);
  const emptyHubCopy = homeEmptyHubCopy(shipments.length > 0);
  const showAttention =
    canLoad &&
    ((unreadCount != null && unreadCount > 0) || hasActionableRequest(requests));
  const showEmptyHub =
    canLoad &&
    shipmentsStatus === 'ready' &&
    requestsStatus === 'ready' &&
    activeShipments.length === 0 &&
    pendingRequests.length === 0;

  return (
    <GradientBackground>
      <View style={styles.headerRow} testID="home-title">
        <View style={styles.profileSection}>
          <View style={[styles.avatarBorder, { borderColor: theme.primary }]}>
            <View style={[styles.avatarContainer, { backgroundColor: theme.backgroundSelected }]}>
              <Text style={[styles.avatarText, { color: theme.text }]}>
                {(displayName[0] || 'C').toUpperCase()}
              </Text>
            </View>
          </View>
          <View style={styles.profileInfo} testID="home-session">
            <Text style={[styles.greetingText, { color: theme.text }]} testID="home-greeting">
              Welcome, {displayName}
            </Text>
            <Text
              style={[styles.greetingSubtitle, { color: theme.textSecondary }]}
              testID="home-identity">
              {identityLinked && companyName ? companyName : 'Company linking unavailable'}
            </Text>
            {identityLinked && companyCode ? (
              <Text style={[styles.companyCode, { color: theme.textSecondary }]} testID="home-company-code">
                {companyCode}
              </Text>
            ) : null}
          </View>
        </View>

        <View style={styles.headerIcons}>
          <TouchableOpacity
            testID="home-notifications-header"
            style={[
              styles.headerIconBtn,
              { backgroundColor: theme.backgroundElement, borderColor: theme.border, borderWidth: 1.5 },
            ]}
            activeOpacity={0.7}
            onPress={openNotifications}
            disabled={!roleOk}
            accessibilityRole="button"
            accessibilityLabel={
              unreadCount && unreadCount > 0
                ? `Notifications, ${unreadCount} unread`
                : 'Notifications'
            }>
            <Bell size={18} color={theme.text} />
            {unreadCount != null && unreadCount > 0 ? (
              <View
                testID="home-notifications-unread"
                style={[styles.unreadDot, { backgroundColor: theme.primary }]}
                accessibilityElementsHidden
                importantForAccessibility="no"
              />
            ) : null}
          </TouchableOpacity>
          <TouchableOpacity
            testID="home-messages-header"
            style={[styles.headerIconBtn, { backgroundColor: theme.primary }]}
            activeOpacity={0.7}
            onPress={openMessages}
            disabled={!roleOk || !identityLinked}
            accessibilityRole="button"
            accessibilityLabel="Open messages">
            <MessageSquare size={18} color="#FFFFFF" />
          </TouchableOpacity>
        </View>
      </View>

      {!roleOk ? (
        <View
          testID="home-role-banner"
          style={[
            styles.warnBanner,
            { backgroundColor: 'rgba(239, 68, 68, 0.12)', borderColor: 'rgba(239, 68, 68, 0.45)' },
          ]}>
          <Text style={styles.warnTitleDanger}>Wrong account type</Text>
          <Text style={[styles.warnBody, { color: theme.textSecondary }]}>
            This app is for FleetX client accounts. Signed-in role: {roleName || 'unavailable'}.
          </Text>
        </View>
      ) : null}

      {!identityLinked ? (
        <View
          testID="home-unlinked-banner"
          style={[
            styles.warnBanner,
            { backgroundColor: 'rgba(251, 191, 36, 0.12)', borderColor: 'rgba(251, 191, 36, 0.45)' },
          ]}>
          <Text style={styles.warnTitle}>Company not linked</Text>
          <Text style={[styles.warnBody, { color: theme.textSecondary }]}>
            You are signed in as {fullName}, but your account is not linked to a company.
            Shipments, requests, and messaging are unavailable. Self-service company linking is
            not available in this app.
            {identity?.unlink_reason ? ` (${identity.unlink_reason})` : ''}
          </Text>
        </View>
      ) : (
        <View
          testID="home-linked-status"
          style={[
            styles.linkedChip,
            { backgroundColor: theme.backgroundElement, borderColor: theme.border },
          ]}>
          <Text style={[styles.linkedChipText, { color: theme.textSecondary }]}>
            Account linked · {companyName || 'Company'}
          </Text>
        </View>
      )}

      {canLoad && showAttention ? (
        <View
          testID="home-attention"
          style={[styles.card, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}>
          <View style={styles.cardHeaderRow}>
            <CircleAlert size={18} color={theme.primary} />
            <Text style={[styles.cardTitle, { color: theme.text }]}>Needs attention</Text>
          </View>
          {hasActionableRequest(requests) ? (
            <TouchableOpacity
              testID="home-attention-requests"
              style={styles.inlineCta}
              onPress={openRequests}
              accessibilityRole="button"
              accessibilityLabel="A shipment request needs your response. Open requests.">
              <Text style={[styles.inlineCtaText, { color: theme.primary }]}>
                A shipment request needs your response
              </Text>
              <ChevronRight size={16} color={theme.primary} />
            </TouchableOpacity>
          ) : null}
          {unreadCount != null && unreadCount > 0 ? (
            <TouchableOpacity
              testID="home-attention-notifications"
              style={styles.inlineCta}
              onPress={openNotifications}
              accessibilityRole="button"
              accessibilityLabel={`${unreadCount} unread notifications. Open notifications.`}>
              <Text style={[styles.inlineCtaText, { color: theme.primary }]}>
                {unreadCount === 1 ? '1 unread notification' : `${unreadCount} unread notifications`}
              </Text>
              <ChevronRight size={16} color={theme.primary} />
            </TouchableOpacity>
          ) : null}
        </View>
      ) : null}

      {canLoad && !showEmptyHub ? (
        <View
          testID="home-active-shipments"
          style={[styles.card, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}>
          <View style={styles.cardHeaderRow}>
            <Truck size={18} color={theme.primary} />
            <Text style={[styles.cardTitle, { color: theme.text }]}>Active shipments</Text>
            <TouchableOpacity
              testID="home-shipments-view-all"
              onPress={openShipments}
              accessibilityRole="button"
              accessibilityLabel="View all shipments"
              hitSlop={8}>
              <Text style={[styles.headerLink, { color: theme.primary }]}>View all</Text>
            </TouchableOpacity>
          </View>

          {shipmentsStatus === 'loading' ? (
            <View testID="home-shipments-loading" style={styles.sectionState}>
              <ActivityIndicator color={theme.primary} />
            </View>
          ) : null}

          {shipmentsStatus === 'error' ? (
            <View testID="home-shipments-error" style={styles.sectionState}>
              <Text style={[styles.sectionErrorTitle, { color: theme.text }]}>Could not load shipments</Text>
              <Text style={[styles.cardBody, { color: theme.textSecondary }]}>
                {shipmentsError || 'Could not load shipments.'}
              </Text>
              <TouchableOpacity
                testID="home-shipments-retry"
                onPress={() => void loadShipments()}
                style={styles.inlineCta}
                accessibilityRole="button"
                accessibilityLabel="Retry loading shipments">
                <Text style={[styles.inlineCtaText, { color: theme.primary }]}>Retry</Text>
              </TouchableOpacity>
            </View>
          ) : null}

          {shipmentsStatus === 'ready' && activeShipments.length === 0 ? (
            <Text testID="home-shipments-empty" style={[styles.cardBody, { color: theme.textSecondary }]}>
              {emptyHubCopy.title}.
            </Text>
          ) : null}

          {activeShipments.map((item) => {
            const origin = formatPlaceSummary(item.pickupCompany, item.pickupCity, item.pickupCountry);
            const destination = formatPlaceSummary(
              item.deliveryCompany,
              item.deliveryCity,
              item.deliveryCountry
            );
            const statusRaw = item.status?.name ?? null;
            const statusLabel = customerFacingShipmentStatus(statusRaw);
            const number = (item.shipmentNumber && String(item.shipmentNumber).trim()) || 'Shipment';
            const date =
              formatHomeDate(item.plannedDeliveryDate) || formatHomeDate(item.plannedPickupDate);
            return (
              <TouchableOpacity
                key={item.id}
                testID={`home-shipment-${item.id}`}
                style={[styles.rowCard, { borderColor: theme.border }]}
                onPress={() => openShipment(item.id)}
                accessibilityRole="button"
                accessibilityLabel={`Open shipment ${number}. Status ${statusLabel}. ${origin} to ${destination}.`}>
                <View style={styles.rowTop}>
                  <Text style={[styles.rowTitle, { color: theme.text }]} numberOfLines={1}>
                    {number}
                  </Text>
                  <View style={[styles.statusPill, { backgroundColor: 'rgba(30, 117, 255, 0.14)' }]}>
                    <Text style={[styles.statusPillText, { color: theme.primary }]} numberOfLines={1}>
                      {statusLabel}
                    </Text>
                  </View>
                </View>
                <Text style={[styles.routeText, { color: theme.textSecondary }]} numberOfLines={2}>
                  {origin}
                  <Text style={{ color: theme.textSecondary }}> → </Text>
                  {destination}
                </Text>
                {date ? (
                  <Text style={[styles.metaText, { color: theme.textSecondary }]}>
                    {item.plannedDeliveryDate ? `Planned delivery ${date}` : `Planned pickup ${date}`}
                  </Text>
                ) : null}
              </TouchableOpacity>
            );
          })}
        </View>
      ) : null}

      {canLoad && !showEmptyHub ? (
        <View
          testID="home-requests-summary"
          style={[styles.card, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}>
          <View style={styles.cardHeaderRow}>
            <FileText size={18} color={theme.primary} />
            <Text style={[styles.cardTitle, { color: theme.text }]}>Requests</Text>
            <TouchableOpacity
              testID="home-requests-view-all"
              onPress={openRequests}
              accessibilityRole="button"
              accessibilityLabel="View requests"
              hitSlop={8}>
              <Text style={[styles.headerLink, { color: theme.primary }]}>View Requests</Text>
            </TouchableOpacity>
          </View>

          {requestsStatus === 'loading' ? (
            <View testID="home-requests-loading" style={styles.sectionState}>
              <ActivityIndicator color={theme.primary} />
            </View>
          ) : null}

          {requestsStatus === 'error' ? (
            <View testID="home-requests-error" style={styles.sectionState}>
              <Text style={[styles.sectionErrorTitle, { color: theme.text }]}>Could not load requests</Text>
              <Text style={[styles.cardBody, { color: theme.textSecondary }]}>
                {requestsError || 'Could not load requests.'}
              </Text>
              <TouchableOpacity
                testID="home-requests-retry"
                onPress={() => void loadRequests()}
                style={styles.inlineCta}
                accessibilityRole="button"
                accessibilityLabel="Retry loading requests">
                <Text style={[styles.inlineCtaText, { color: theme.primary }]}>Retry</Text>
              </TouchableOpacity>
            </View>
          ) : null}

          {requestsStatus === 'ready' && pendingRequests.length === 0 ? (
            <Text testID="home-requests-empty" style={[styles.cardBody, { color: theme.textSecondary }]}>
              No pending shipment requests.
            </Text>
          ) : null}

          {pendingRequests.map((item) => {
            const origin = formatPlaceSummary(item.pickupCompany, item.pickupCity, item.pickupCountry);
            const destination = formatPlaceSummary(
              item.deliveryCompany,
              item.deliveryCity,
              item.deliveryCountry
            );
            const statusRaw = item.status?.name ?? null;
            const statusLabel = formatRequestStatusLabel(statusRaw);
            const number = (item.requestNumber && String(item.requestNumber).trim()) || 'Request';
            return (
              <TouchableOpacity
                key={item.id}
                testID={`home-request-${item.id}`}
                style={[styles.rowCard, { borderColor: theme.border }]}
                onPress={() => {
                  const linkedId = shipmentWorkspaceIdForConvertedRequest(item);
                  if (linkedId) openShipment(linkedId);
                  else openRequest(item.id);
                }}
                accessibilityRole="button"
                accessibilityLabel={`Open request ${number}. Status ${statusLabel}.`}>
                <View style={styles.rowTop}>
                  <Text style={[styles.rowTitle, { color: theme.text }]} numberOfLines={1}>
                    {number}
                  </Text>
                  <View style={[styles.statusPill, { backgroundColor: 'rgba(30, 117, 255, 0.14)' }]}>
                    <Text style={[styles.statusPillText, { color: theme.primary }]} numberOfLines={1}>
                      {statusLabel}
                    </Text>
                  </View>
                </View>
                <Text style={[styles.routeText, { color: theme.textSecondary }]} numberOfLines={2}>
                  {origin}
                  <Text style={{ color: theme.textSecondary }}> → </Text>
                  {destination}
                </Text>
              </TouchableOpacity>
            );
          })}
        </View>
      ) : null}

      {canLoad && notesStatus === 'error' ? (
        <View
          testID="home-notifications-error"
          style={[styles.card, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}>
          <View style={styles.cardHeaderRow}>
            <Bell size={18} color={theme.primary} />
            <Text style={[styles.cardTitle, { color: theme.text }]}>Recent notifications</Text>
            <TouchableOpacity
              onPress={openNotifications}
              accessibilityRole="button"
              accessibilityLabel="Open notifications inbox"
              hitSlop={8}>
              <Text style={[styles.headerLink, { color: theme.primary }]}>Inbox</Text>
            </TouchableOpacity>
          </View>
          <View style={styles.sectionState}>
            <Text style={[styles.sectionErrorTitle, { color: theme.text }]}>
              Could not load recent notifications
            </Text>
            <Text style={[styles.cardBody, { color: theme.textSecondary }]}>
              {notesError || 'Could not load recent notifications.'}
            </Text>
            <TouchableOpacity
              testID="home-notifications-retry"
              onPress={retryRecentNotes}
              style={styles.inlineCta}
              accessibilityRole="button"
              accessibilityLabel="Retry loading recent notifications"
              accessibilityHint="Attempts to reload the notification preview">
              <Text style={[styles.inlineCtaText, { color: theme.primary }]}>Retry</Text>
            </TouchableOpacity>
          </View>
        </View>
      ) : null}

      {canLoad && (notesStatus === 'ready' || notesStatus === 'loading') && recentNotes.length > 0 ? (
        <View
          testID="home-notifications-preview"
          style={[styles.card, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}>
          <View style={styles.cardHeaderRow}>
            <Bell size={18} color={theme.primary} />
            <Text style={[styles.cardTitle, { color: theme.text }]}>Recent notifications</Text>
            <TouchableOpacity
              onPress={openNotifications}
              accessibilityRole="button"
              accessibilityLabel="Open notifications"
              hitSlop={8}>
              <Text style={[styles.headerLink, { color: theme.primary }]}>Inbox</Text>
            </TouchableOpacity>
          </View>
          {recentNotes.map((item) => (
            <TouchableOpacity
              key={item.id}
              testID={`home-notification-${item.id}`}
              style={[styles.rowCard, { borderColor: theme.border }]}
              onPress={openNotifications}
              accessibilityRole="button"
              accessibilityLabel={`${item.title}. Opens notification inbox.`}>
              <Text style={[styles.rowTitle, { color: theme.text }]} numberOfLines={1}>
                {item.title || 'Notification'}
              </Text>
              <Text style={[styles.metaText, { color: theme.textSecondary }]} numberOfLines={1}>
                {formatNotificationTypeLabel(item.type)}
                {!item.isRead ? ' · Unread' : ''}
              </Text>
            </TouchableOpacity>
          ))}
        </View>
      ) : null}

      {showEmptyHub ? (
        <View
          testID="home-empty"
          style={[styles.card, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}>
          <Text style={[styles.emptyTitle, { color: theme.text }]} testID="home-empty-title">
            {emptyHubCopy.title}
          </Text>
          <Text
            style={[styles.cardBody, { color: theme.textSecondary }]}
            testID="home-empty-body">
            {emptyHubCopy.body}
          </Text>
          <TouchableOpacity
            testID="home-empty-requests-cta"
            style={[styles.primaryBtn, { backgroundColor: theme.primary }]}
            onPress={openRequests}
            accessibilityRole="button"
            accessibilityLabel="Go to shipment requests">
            <FileText size={16} color="#FFFFFF" />
            <Text style={styles.primaryBtnText}>Go to Requests</Text>
          </TouchableOpacity>
        </View>
      ) : null}

      <View
        testID="home-messages-card"
        style={[
          styles.card,
          { backgroundColor: theme.backgroundElement, borderColor: theme.border },
        ]}>
        <View style={styles.cardHeaderRow}>
          <MessageSquare size={18} color={theme.primary} />
          <Text style={[styles.cardTitle, { color: theme.text }]}>Messages</Text>
        </View>
        <Text style={[styles.cardBody, { color: theme.textSecondary }]}>
          Contact FleetX Support on authorized conversations for your company.
        </Text>
        <TouchableOpacity
          testID="home-open-messages"
          style={[
            styles.primaryBtn,
            { backgroundColor: theme.primary, opacity: !roleOk || !identityLinked ? 0.45 : 1 },
          ]}
          activeOpacity={0.8}
          disabled={!roleOk || !identityLinked}
          onPress={openMessages}
          accessibilityRole="button"
          accessibilityLabel="Open messages">
          <MessageSquare size={16} color="#FFFFFF" />
          <Text style={styles.primaryBtnText}>
            {!roleOk
              ? 'Messaging unavailable — role mismatch'
              : !identityLinked
                ? 'Messaging unavailable — company not linked'
                : 'Open messages'}
          </Text>
        </TouchableOpacity>
      </View>
    </GradientBackground>
  );
}

const styles = StyleSheet.create({
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: 8,
    marginBottom: 16,
  },
  headerIcons: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  unreadDot: {
    position: 'absolute',
    top: 8,
    right: 8,
    width: 8,
    height: 8,
    borderRadius: 4,
    borderWidth: 1.5,
    borderColor: '#111A2E',
  },
  profileSection: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
    paddingRight: 12,
  },
  avatarBorder: {
    width: 48,
    height: 48,
    borderRadius: 24,
    borderWidth: 1.5,
    justifyContent: 'center',
    alignItems: 'center',
  },
  avatarContainer: {
    width: 40,
    height: 40,
    borderRadius: 20,
    justifyContent: 'center',
    alignItems: 'center',
  },
  avatarText: { fontSize: 16, fontWeight: '700' },
  profileInfo: { marginLeft: 12, flex: 1 },
  greetingText: { fontSize: 18, fontWeight: '700' },
  greetingSubtitle: { fontSize: 13, fontWeight: '500', marginTop: 2 },
  companyCode: { fontSize: 12, marginTop: 2 },
  headerIconBtn: {
    width: 44,
    height: 44,
    borderRadius: 12,
    justifyContent: 'center',
    alignItems: 'center',
    position: 'relative',
  },
  warnBanner: {
    borderWidth: 1,
    borderRadius: 14,
    padding: 14,
    marginBottom: 14,
  },
  warnTitle: { color: '#FBBF24', fontSize: 14, fontWeight: '700', marginBottom: 4 },
  warnTitleDanger: { color: '#F87171', fontSize: 14, fontWeight: '700', marginBottom: 4 },
  warnBody: { fontSize: 12, lineHeight: 17 },
  linkedChip: {
    alignSelf: 'flex-start',
    borderWidth: 1,
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 6,
    marginBottom: 14,
  },
  linkedChipText: { fontSize: 12, fontWeight: '600' },
  card: {
    borderWidth: 1.5,
    borderRadius: 16,
    padding: 16,
    marginBottom: 14,
  },
  cardHeaderRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 10,
  },
  cardTitle: { fontSize: 15, fontWeight: '700', flex: 1 },
  headerLink: { fontSize: 13, fontWeight: '700' },
  cardBody: { fontSize: 13, lineHeight: 18 },
  sectionErrorTitle: { fontSize: 14, fontWeight: '700', marginBottom: 4 },
  sectionState: { paddingVertical: 8 },
  rowCard: {
    borderWidth: 1,
    borderRadius: 12,
    padding: 12,
    marginTop: 8,
    minHeight: 44,
  },
  rowTop: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 4,
  },
  rowTitle: { fontSize: 14, fontWeight: '700', flex: 1 },
  statusPill: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 999,
    maxWidth: 140,
  },
  statusPillText: { fontSize: 11, fontWeight: '700' },
  routeText: { fontSize: 12, lineHeight: 17 },
  metaText: { fontSize: 11, marginTop: 4 },
  inlineCta: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 10,
    minHeight: 44,
  },
  inlineCtaText: { fontSize: 13, fontWeight: '700', marginRight: 4, flex: 1 },
  primaryBtn: {
    marginTop: 14,
    minHeight: 48,
    borderRadius: 12,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingHorizontal: 14,
  },
  primaryBtnText: { color: '#FFFFFF', fontSize: 14, fontWeight: '700', textAlign: 'center' },
  emptyTitle: { fontSize: 16, fontWeight: '700', marginBottom: 8 },
  actionsRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 16,
    gap: 8,
  },
  actionCard: {
    flex: 1,
    borderWidth: 1.5,
    borderRadius: 14,
    padding: 12,
    minHeight: 72,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
  },
  actionLabel: { fontSize: 12, fontWeight: '700' },
});
