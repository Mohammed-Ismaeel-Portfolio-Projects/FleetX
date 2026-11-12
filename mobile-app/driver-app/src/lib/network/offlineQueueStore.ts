import AsyncStorage from '@react-native-async-storage/async-storage';

export type OfflineEventType =
  | 'TRACKING_GPS'
  | 'ACKNOWLEDGE'
  | 'START_TRIP'
  | 'PHASE_TRANSITION'
  | 'VERIFY'
  | 'POD_SUBMIT'
  | 'COMPLETE'
  | 'REPORT_ISSUE'
  | 'FAIL_DELIVERY'
  | 'AVAILABILITY';

export type QueuedEventStatus = 'pending' | 'in_flight' | 'synced' | 'failed';

export interface QueuedOfflineEvent<T = any> {
  id: string;
  type: OfflineEventType;
  assignmentId: string | number;
  payload: T;
  createdAt: string;
  sequenceNumber: number;
  status: QueuedEventStatus;
  retryCount: number;
  lastAttemptAt?: string | null;
  lastError?: string | null;
}

export interface QueueStats {
  total: number;
  pending: number;
  inFlight: number;
  failed: number;
  synced: number;
}

const STORAGE_KEY = '@fleetx_driver_offline_queue_v1';
const SEQUENCE_KEY = '@fleetx_driver_offline_sequence_v1';

function generateUUID(): string {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

async function getNextSequenceNumber(): Promise<number> {
  try {
    const currentStr = await AsyncStorage.getItem(SEQUENCE_KEY);
    const current = currentStr ? parseInt(currentStr, 10) : 0;
    const next = current + 1;
    await AsyncStorage.setItem(SEQUENCE_KEY, String(next));
    return next;
  } catch {
    return Date.now();
  }
}

class OfflineQueueStore {
  private queueCache: QueuedOfflineEvent[] | null = null;
  private changeListeners: Set<() => void> = new Set();

  onQueueChange(listener: () => void): () => void {
    this.changeListeners.add(listener);
    return () => {
      this.changeListeners.delete(listener);
    };
  }

  private notifyChange() {
    this.changeListeners.forEach((fn) => {
      try {
        fn();
      } catch (e) {
        console.warn('Queue listener error:', e);
      }
    });
  }

  private async loadQueue(): Promise<QueuedOfflineEvent[]> {
    if (this.queueCache !== null) {
      return this.queueCache;
    }
    try {
      const raw = await AsyncStorage.getItem(STORAGE_KEY);
      if (!raw) {
        this.queueCache = [];
        return [];
      }
      const parsed = JSON.parse(raw);
      this.queueCache = Array.isArray(parsed) ? parsed : [];
      return this.queueCache;
    } catch {
      this.queueCache = [];
      return [];
    }
  }

  private async saveQueue(queue: QueuedOfflineEvent[]): Promise<void> {
    this.queueCache = queue;
    try {
      await AsyncStorage.setItem(STORAGE_KEY, JSON.stringify(queue));
    } catch (e) {
      console.warn('Failed to persist offline queue to AsyncStorage:', e);
    }
    this.notifyChange();
  }

  async enqueueEvent<T = any>(
    type: OfflineEventType,
    assignmentId: number | string,
    payload: T
  ): Promise<QueuedOfflineEvent<T>> {
    const queue = await this.loadQueue();
    const seq = await getNextSequenceNumber();

    const newEvent: QueuedOfflineEvent<T> = {
      id: generateUUID(),
      type,
      assignmentId,
      payload,
      createdAt: new Date().toISOString(),
      sequenceNumber: seq,
      status: 'pending',
      retryCount: 0,
      lastAttemptAt: null,
      lastError: null,
    };

    queue.push(newEvent);
    await this.saveQueue(queue);
    return newEvent;
  }

  async getPendingEvents(): Promise<QueuedOfflineEvent[]> {
    const queue = await this.loadQueue();
    return queue
      .filter((e) => e.status === 'pending' || e.status === 'in_flight')
      .sort((a, b) => a.sequenceNumber - b.sequenceNumber);
  }

  async getAllEvents(): Promise<QueuedOfflineEvent[]> {
    const queue = await this.loadQueue();
    return [...queue].sort((a, b) => a.sequenceNumber - b.sequenceNumber);
  }

  async updateEventStatus(
    eventId: string,
    status: QueuedEventStatus,
    updates?: Partial<QueuedOfflineEvent>
  ): Promise<void> {
    const queue = await this.loadQueue();
    const idx = queue.findIndex((e) => e.id === eventId);
    if (idx !== -1) {
      queue[idx] = {
        ...queue[idx],
        ...updates,
        status,
        lastAttemptAt: new Date().toISOString(),
      };
      await this.saveQueue(queue);
    }
  }

  async incrementRetryCount(eventId: string, errorMsg?: string): Promise<void> {
    const queue = await this.loadQueue();
    const idx = queue.findIndex((e) => e.id === eventId);
    if (idx !== -1) {
      const currentRetry = queue[idx].retryCount || 0;
      queue[idx] = {
        ...queue[idx],
        retryCount: currentRetry + 1,
        lastAttemptAt: new Date().toISOString(),
        lastError: errorMsg || queue[idx].lastError,
        status: currentRetry + 1 >= 5 ? 'failed' : 'pending',
      };
      await this.saveQueue(queue);
    }
  }

  async resetEventForRetry(eventId: string): Promise<void> {
    const queue = await this.loadQueue();
    const idx = queue.findIndex((e) => e.id === eventId);
    if (idx !== -1) {
      queue[idx] = {
        ...queue[idx],
        status: 'pending',
        lastError: null,
      };
      await this.saveQueue(queue);
    }
  }

  async removeEvent(eventId: string): Promise<void> {
    const queue = await this.loadQueue();
    const filtered = queue.filter((e) => e.id !== eventId);
    await this.saveQueue(filtered);
  }

  async clearSyncedEvents(): Promise<void> {
    const queue = await this.loadQueue();
    const remaining = queue.filter((e) => e.status !== 'synced');
    await this.saveQueue(remaining);
  }

  async clearQueue(): Promise<void> {
    await this.saveQueue([]);
  }

  async getQueueStats(): Promise<QueueStats> {
    const queue = await this.loadQueue();
    return {
      total: queue.length,
      pending: queue.filter((e) => e.status === 'pending').length,
      inFlight: queue.filter((e) => e.status === 'in_flight').length,
      failed: queue.filter((e) => e.status === 'failed').length,
      synced: queue.filter((e) => e.status === 'synced').length,
    };
  }
}

export const offlineQueueStore = new OfflineQueueStore();
