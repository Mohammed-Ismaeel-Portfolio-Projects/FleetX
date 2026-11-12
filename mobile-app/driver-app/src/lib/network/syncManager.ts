import NetInfo from '@react-native-community/netinfo';
import { offlineQueueStore, type QueuedOfflineEvent } from './offlineQueueStore';
import { acknowledgeAssignment, startAssignment, transitionExecutionPhase, verifyAssignment, submitTracking, submitPod, completeAssignment, reportIssue, failDelivery } from '@/features/driverApi/work';
import { updateDriverAvailability } from '@/features/driverApi/availability';
import { refreshAccessTokenSingleFlight } from '../auth/authRefresh';
import { DriverWorkApiError } from '@/features/driverApi/workTypes';
import { cleanupPodEvidenceFiles } from '@/features/pod/podStorageHelper';

export type SyncState = 'idle' | 'syncing' | 'paused' | 'error' | 'success';

export type SyncUIState = 'idle' | 'offline' | 'syncing' | 'success' | 'failed';

export interface OfflineSyncStatus {
  isOnline: boolean;
  uiState: SyncUIState;
  pendingCount: number;
  inFlightCount: number;
  failedCount: number;
  syncedCount: number;
  totalCount: number;
  completedCount: number;
  totalInSession: number;
  currentlyProcessing?: string | null;
  lastError?: string | null;
  lastSyncAt?: string | null;
}

/** Legacy compat interface */
export interface SyncProgress {
  state: SyncState;
  totalPending: number;
  currentlyProcessing?: string | null;
  lastSyncAt?: string | null;
  lastError?: string | null;
}

type SyncStatusListener = (status: OfflineSyncStatus) => void;

class SyncManager {
  private isSyncing = false;
  private isOnline = true;
  private listeners: Set<SyncStatusListener> = new Set();
  private lastSyncAt: string | null = null;
  private lastError: string | null = null;
  private unsubscribeNetInfo: (() => void) | null = null;
  private unsubscribeQueueChange: (() => void) | null = null;
  private successTimer: ReturnType<typeof setTimeout> | null = null;
  private showSuccessState = false;

  private totalInSession = 0;
  private completedCount = 0;

  init() {
    if (!this.unsubscribeNetInfo) {
      this.unsubscribeNetInfo = NetInfo.addEventListener((state) => {
        const reachable = state.isInternetReachable;
        const online = state.isConnected !== false && reachable !== false;
        const wasOffline = !this.isOnline;
        this.isOnline = online;

        this.emitStatus();

        if (wasOffline && online) {
          console.log('[SyncManager] Network restored — triggering automatic queue flush.');
          void this.flushQueue();
        }
      });
    }

    if (!this.unsubscribeQueueChange) {
      this.unsubscribeQueueChange = offlineQueueStore.onQueueChange(() => {
        this.emitStatus();
      });
    }
  }

  destroy() {
    if (this.unsubscribeNetInfo) {
      this.unsubscribeNetInfo();
      this.unsubscribeNetInfo = null;
    }
    if (this.unsubscribeQueueChange) {
      this.unsubscribeQueueChange();
      this.unsubscribeQueueChange = null;
    }
    if (this.successTimer) {
      clearTimeout(this.successTimer);
      this.successTimer = null;
    }
  }

  subscribe(listener: (progress: SyncProgress) => void): () => void {
    const wrapper: SyncStatusListener = (status) => {
      listener({
        state: status.uiState === 'syncing' ? 'syncing' : !status.isOnline ? 'paused' : status.lastError ? 'error' : 'idle',
        totalPending: status.pendingCount + status.inFlightCount,
        currentlyProcessing: status.currentlyProcessing,
        lastSyncAt: status.lastSyncAt,
        lastError: status.lastError,
      });
    };
    return this.subscribeStatus(wrapper);
  }

  subscribeStatus(listener: SyncStatusListener): () => void {
    this.init();
    this.listeners.add(listener);
    this.emitStatus();
    return () => {
      this.listeners.delete(listener);
    };
  }

  private emitStatus(currentlyProcessing?: string | null) {
    offlineQueueStore.getQueueStats().then((stats) => {
      const pendingCount = stats.pending;
      const inFlightCount = stats.inFlight;
      const failedCount = stats.failed;
      const syncedCount = stats.synced;
      const totalCount = stats.total;

      let uiState: SyncUIState = 'idle';
      if (this.isSyncing) {
        uiState = 'syncing';
      } else if (!this.isOnline) {
        uiState = 'offline';
      } else if (failedCount > 0) {
        uiState = 'failed';
      } else if (this.showSuccessState) {
        uiState = 'success';
      } else if (pendingCount + inFlightCount > 0) {
        uiState = 'offline';
      }

      const status: OfflineSyncStatus = {
        isOnline: this.isOnline,
        uiState,
        pendingCount,
        inFlightCount,
        failedCount,
        syncedCount,
        totalCount,
        completedCount: this.completedCount,
        totalInSession: this.totalInSession,
        currentlyProcessing,
        lastError: this.lastError,
        lastSyncAt: this.lastSyncAt,
      };

      this.listeners.forEach((fn) => {
        try {
          fn(status);
        } catch (e) {
          console.warn('SyncManager subscriber error:', e);
        }
      });
    }).catch(() => {});
  }

  async flushQueue(): Promise<void> {
    if (this.isSyncing || !this.isOnline) return;

    const pendingEvents = await offlineQueueStore.getPendingEvents();
    if (pendingEvents.length === 0) return;

    this.isSyncing = true;
    this.lastError = null;
    this.totalInSession = pendingEvents.length;
    this.completedCount = 0;
    this.showSuccessState = false;
    if (this.successTimer) {
      clearTimeout(this.successTimer);
      this.successTimer = null;
    }
    this.emitStatus();

    try {
      for (const event of pendingEvents) {
        if (!this.isOnline) {
          console.log('[SyncManager] Network lost during flush — pausing sync.');
          break;
        }

        await offlineQueueStore.updateEventStatus(event.id, 'in_flight');
        this.emitStatus(event.type);

        try {
          await this.processSingleEvent(event);
          await offlineQueueStore.updateEventStatus(event.id, 'synced');
          await offlineQueueStore.removeEvent(event.id);
          this.completedCount++;
          this.lastSyncAt = new Date().toISOString();
          this.emitStatus(event.type);
        } catch (err: any) {
          const is401 = err instanceof DriverWorkApiError && err.statusCode === 401;
          if (is401) {
            console.log('[SyncManager] 401 Auth error during sync — attempting token refresh.');
            try {
              await refreshAccessTokenSingleFlight();
              // Retry event once after refresh
              await this.processSingleEvent(event);
              await offlineQueueStore.updateEventStatus(event.id, 'synced');
              await offlineQueueStore.removeEvent(event.id);
              this.completedCount++;
              this.lastSyncAt = new Date().toISOString();
              this.emitStatus(event.type);
              continue;
            } catch (authErr) {
              console.warn('[SyncManager] Auth refresh failed during sync:', authErr);
              this.lastError = 'Authentication expired while offline. Please log in.';
              await offlineQueueStore.incrementRetryCount(event.id, 'Auth expired');
              break;
            }
          }

          const errMsg = err?.message || 'Sync failed';
          this.lastError = errMsg;
          await offlineQueueStore.incrementRetryCount(event.id, errMsg);

          // If conflict (409) or cancelled (404), halt queue to prevent cascade errors
          if (err instanceof DriverWorkApiError && (err.statusCode === 409 || err.statusCode === 404)) {
            console.warn(`[SyncManager] Halt queue due to HTTP ${err.statusCode} on event ${event.id}`);
            break;
          }
        }
      }
    } finally {
      this.isSyncing = false;
      const stats = await offlineQueueStore.getQueueStats();
      if (stats.pending === 0 && stats.inFlight === 0 && stats.failed === 0 && this.completedCount > 0) {
        this.showSuccessState = true;
        this.emitStatus();
        this.successTimer = setTimeout(() => {
          this.showSuccessState = false;
          this.emitStatus();
        }, 3500);
      } else {
        this.emitStatus();
      }
    }
  }

  async retrySingleEvent(eventId: string): Promise<void> {
    await offlineQueueStore.resetEventForRetry(eventId);
    await this.flushQueue();
  }

  async retryAllFailedEvents(): Promise<void> {
    const allEvents = await offlineQueueStore.getAllEvents();
    const failedEvents = allEvents.filter((e) => e.status === 'failed');
    for (const e of failedEvents) {
      await offlineQueueStore.resetEventForRetry(e.id);
    }
    await this.flushQueue();
  }

  private async processSingleEvent(event: QueuedOfflineEvent): Promise<void> {
    const { type, assignmentId, payload } = event;

    switch (type) {
      case 'TRACKING_GPS':
        await submitTracking(assignmentId, payload);
        break;
      case 'ACKNOWLEDGE':
        await acknowledgeAssignment(assignmentId);
        break;
      case 'START_TRIP':
        await startAssignment(assignmentId);
        break;
      case 'PHASE_TRANSITION':
        await transitionExecutionPhase(assignmentId, payload.command);
        break;
      case 'VERIFY':
        await verifyAssignment(assignmentId, payload.event, payload.challenge);
        break;
      case 'POD_SUBMIT':
        await submitPod(assignmentId, payload);
        await cleanupPodEvidenceFiles(payload.photo?.uri, payload.signature?.uri);
        break;
      case 'COMPLETE':
        await completeAssignment(assignmentId);
        break;
      case 'REPORT_ISSUE':
        await reportIssue(assignmentId, payload.type, payload.description);
        break;
      case 'FAIL_DELIVERY':
        await failDelivery(assignmentId, payload.type, payload.description);
        break;
      case 'AVAILABILITY':
        await updateDriverAvailability(payload);
        break;
      default:
        console.warn(`[SyncManager] Unknown event type: ${type}`);
    }
  }
}

export const syncManager = new SyncManager();
