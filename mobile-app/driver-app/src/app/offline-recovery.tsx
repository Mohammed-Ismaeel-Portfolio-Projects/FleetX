import React, { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useRouter } from 'expo-router';
import {
  AlertCircle,
  CheckCircle2,
  Clock,
  RefreshCw,
  RotateCcw,
} from 'lucide-react-native';

import {
  BottomActionBar,
  DriverCard,
  DriverHeader,
  DriverScreen,
  PrimaryButton,
  SectionHeader,
  StatusBadge,
} from '@/components/driver';
import { DriverTheme } from '@/components/driver/theme';
import {
  offlineQueueStore,
  type QueuedOfflineEvent,
} from '@/lib/network/offlineQueueStore';
import { syncManager } from '@/lib/network/syncManager';
import { useOfflineSyncStatus } from '@/lib/network/useOfflineSyncStatus';

function formatEventType(type: string): string {
  switch (type) {
    case 'START_TRIP':
      return 'Start Trip';
    case 'ACKNOWLEDGE':
      return 'Acknowledge Trip';
    case 'PHASE_TRANSITION':
      return 'Phase Transition';
    case 'TRACKING_GPS':
      return 'GPS Telemetry';
    case 'POD_SUBMIT':
      return 'Proof of Delivery (POD)';
    case 'COMPLETE':
      return 'Complete Trip';
    case 'REPORT_ISSUE':
      return 'Report Issue';
    case 'FAIL_DELIVERY':
      return 'Fail Delivery';
    case 'AVAILABILITY':
      return 'Duty Availability';
    default:
      return type;
  }
}

function formatTimestamp(isoString: string): string {
  try {
    const date = new Date(isoString);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
  } catch {
    return isoString;
  }
}

export default function OfflineRecoveryScreen() {
  const router = useRouter();
  const syncStatus = useOfflineSyncStatus();
  const [events, setEvents] = useState<QueuedOfflineEvent[]>([]);
  const [loading, setLoading] = useState(true);
  const [retryingId, setRetryingId] = useState<string | null>(null);
  const [retryingAll, setRetryingAll] = useState(false);

  const loadEvents = useCallback(async () => {
    try {
      const all = await offlineQueueStore.getAllEvents();
      setEvents(all);
    } catch {
      setEvents([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadEvents();
    const unsub = offlineQueueStore.onQueueChange(() => {
      void loadEvents();
    });
    return () => unsub();
  }, [loadEvents]);

  const handleRetrySingle = async (eventId: string) => {
    setRetryingId(eventId);
    try {
      await syncManager.retrySingleEvent(eventId);
    } finally {
      setRetryingId(null);
      void loadEvents();
    }
  };

  const handleRetryAll = async () => {
    setRetryingAll(true);
    try {
      await syncManager.retryAllFailedEvents();
    } finally {
      setRetryingAll(false);
      void loadEvents();
    }
  };

  const failedEvents = events.filter((e) => e.status === 'failed');
  const pendingEvents = events.filter((e) => e.status === 'pending' || e.status === 'in_flight');

  return (
    <DriverScreen
      header={
        <DriverHeader
          title="Offline Event Recovery"
          titleTestID="offline-recovery-title"
          onBack={() => router.back()}
        />
      }
      scroll
      padded
      testID="offline-recovery-screen">
      <View style={styles.stack}>
        {/* Header Summary Card */}
        <DriverCard testID="offline-recovery-summary">
          <View style={styles.summaryHead}>
            <View style={styles.summaryTitleRow}>
              <RotateCcw size={20} color={DriverTheme.colors.primary} />
              <Text style={styles.summaryTitle}>Offline Queue Engine</Text>
            </View>
            <StatusBadge
              label={
                syncStatus.uiState === 'syncing'
                  ? 'Syncing...'
                  : !syncStatus.isOnline
                  ? 'Offline'
                  : failedEvents.length > 0
                  ? 'Attention Needed'
                  : 'In Sync'
              }
              tone={
                syncStatus.uiState === 'syncing'
                  ? 'assigned'
                  : !syncStatus.isOnline
                  ? 'ready'
                  : failedEvents.length > 0
                  ? 'danger'
                  : 'available'
              }
            />
          </View>
          <Text style={styles.bodyText}>
            {failedEvents.length > 0
              ? `${failedEvents.length} actions failed automatic retry and require manual sync.`
              : pendingEvents.length > 0
              ? `${pendingEvents.length} actions queued waiting for synchronization.`
              : 'All offline actions are in sync with the server.'}
          </Text>
          {(failedEvents.length > 0 || pendingEvents.length > 0) && (
            <View style={styles.actionRow}>
              <PrimaryButton
                testID="retry-all-button"
                label={retryingAll ? 'Syncing Queue...' : 'Retry All Unsent Actions'}
                onPress={() => void handleRetryAll()}
                disabled={retryingAll || syncStatus.uiState === 'syncing'}
                accessibilityLabel="Retry all unsent actions"
              />
            </View>
          )}
        </DriverCard>

        <SectionHeader label={`Queued Events (${events.length})`} />

        {loading ? (
          <DriverCard>
            <ActivityIndicator size="small" color={DriverTheme.colors.primary} />
            <Text style={styles.metaText}>Loading queue items...</Text>
          </DriverCard>
        ) : events.length === 0 ? (
          <DriverCard testID="recovery-empty">
            <View style={styles.emptyWrap}>
              <CheckCircle2 size={36} color="#10B981" />
              <Text style={styles.emptyTitle}>No Failed Events</Text>
              <Text style={styles.emptyMeta}>
                All offline actions have been successfully synchronized with the backend.
              </Text>
            </View>
          </DriverCard>
        ) : (
          events.map((item) => {
            const isFailed = item.status === 'failed';
            const isInFlight = item.status === 'in_flight';
            const isRetryingThis = retryingId === item.id;

            return (
              <DriverCard key={item.id} testID={`recovery-event-${item.id}`}>
                <View style={styles.cardHeader}>
                  <View style={styles.typeRow}>
                    <Text style={styles.eventTitle}>{formatEventType(item.type)}</Text>
                    <Text style={styles.seqText}>#{item.sequenceNumber}</Text>
                  </View>
                  <StatusBadge
                    label={
                      isFailed
                        ? 'Failed'
                        : isInFlight
                        ? 'In Flight'
                        : item.status === 'synced'
                        ? 'Synced'
                        : 'Pending'
                    }
                    tone={
                      isFailed
                        ? 'danger'
                        : isInFlight
                        ? 'assigned'
                        : item.status === 'synced'
                        ? 'available'
                        : 'neutral'
                    }
                  />
                </View>

                <View style={styles.metaGrid}>
                  <View style={styles.metaRow}>
                    <Clock size={14} color={DriverTheme.colors.textMuted} />
                    <Text style={styles.metaText}>Created: {formatTimestamp(item.createdAt)}</Text>
                  </View>
                  <Text style={styles.metaText}>
                    Assignment: {String(item.assignmentId)}
                  </Text>
                  <Text style={styles.metaText}>
                    Attempts: {item.retryCount} / 5
                  </Text>
                </View>

                {item.lastError ? (
                  <View style={styles.errorBox}>
                    <AlertCircle size={14} color="#F87171" />
                    <Text style={styles.errorText}>{item.lastError}</Text>
                  </View>
                ) : null}

                {(isFailed || item.status === 'pending') && (
                  <View style={styles.cardFooter}>
                    <Pressable
                      testID={`retry-single-${item.id}`}
                      style={[styles.retrySingleBtn, isRetryingThis && styles.retrySingleBtnDisabled]}
                      onPress={() => void handleRetrySingle(item.id)}
                      disabled={isRetryingThis || isInFlight}
                      accessibilityRole="button"
                      accessibilityLabel={`Retry ${formatEventType(item.type)}`}>
                      {isRetryingThis ? (
                        <ActivityIndicator size="small" color="#FFFFFF" />
                      ) : (
                        <RefreshCw size={14} color="#FFFFFF" />
                      )}
                      <Text style={styles.retrySingleText}>
                        {isRetryingThis ? 'Retrying...' : 'Retry Action'}
                      </Text>
                    </Pressable>
                  </View>
                )}
              </DriverCard>
            );
          })
        )}
      </View>
    </DriverScreen>
  );
}

const styles = StyleSheet.create({
  stack: {
    gap: DriverTheme.spacing.md,
  },
  summaryHead: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  summaryTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  summaryTitle: {
    ...DriverTheme.typography.cardTitle,
    color: DriverTheme.colors.textPrimary,
  },
  bodyText: {
    ...DriverTheme.typography.body,
    color: DriverTheme.colors.textSecondary,
    marginBottom: 12,
  },
  actionRow: {
    marginTop: 4,
  },
  emptyWrap: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 24,
    gap: 8,
  },
  emptyTitle: {
    ...DriverTheme.typography.cardTitle,
    color: DriverTheme.colors.textPrimary,
    marginTop: 4,
  },
  emptyMeta: {
    ...DriverTheme.typography.body,
    color: DriverTheme.colors.textMuted,
    textAlign: 'center',
  },
  cardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  typeRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  eventTitle: {
    ...DriverTheme.typography.cardTitle,
    color: DriverTheme.colors.textPrimary,
    fontSize: 15,
  },
  seqText: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.primary,
    fontWeight: '700',
  },
  metaGrid: {
    gap: 4,
    marginBottom: 8,
  },
  metaRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  metaText: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.textSecondary,
  },
  errorBox: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#2A0F0F',
    borderWidth: 1,
    borderColor: '#7F1D1D',
    padding: 8,
    borderRadius: 8,
    marginTop: 4,
    marginBottom: 8,
  },
  errorText: {
    ...DriverTheme.typography.caption,
    color: '#FCA5A5',
    flex: 1,
  },
  cardFooter: {
    marginTop: 6,
    alignItems: 'flex-end',
  },
  retrySingleBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: DriverTheme.colors.primary,
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 8,
  },
  retrySingleBtnDisabled: {
    opacity: 0.6,
  },
  retrySingleText: {
    color: '#FFFFFF',
    fontWeight: '700',
    fontSize: 13,
  },
});
