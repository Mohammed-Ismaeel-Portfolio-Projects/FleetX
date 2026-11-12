import { submitTracking } from '@/features/driverApi/work';
import type { DeviceFix } from '@/features/driverApi/tripTypes';
import { DriverWorkApiError } from '@/features/driverApi/workTypes';
import { offlineQueueStore } from '@/lib/network/offlineQueueStore';

const MIN_INTERVAL_MS = 5000;
const MIN_DISTANCE_M = 25;

function haversineMeters(a: DeviceFix, b: DeviceFix): number {
  const R = 6371000;
  const toRad = (d: number) => (d * Math.PI) / 180;
  const dLat = toRad(b.latitude - a.latitude);
  const dLon = toRad(b.longitude - a.longitude);
  const lat1 = toRad(a.latitude);
  const lat2 = toRad(b.latitude);
  const h =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLon / 2) ** 2;
  return 2 * R * Math.asin(Math.min(1, Math.sqrt(h)));
}

export type TelemetryPublisherStatus = 'idle' | 'active' | 'limited' | 'error' | 'stopped';

export class TelemetryPublisher {
  private lastSent: DeviceFix | null = null;
  private lastSentAt = 0;
  private pending: DeviceFix | null = null;
  private inFlight = false;
  private stopped = false;
  private status: TelemetryPublisherStatus = 'idle';
  private lastError: string | null = null;

  constructor(private readonly assignmentId: number | string) {}

  getStatus(): TelemetryPublisherStatus {
    return this.status;
  }

  getLastError(): string | null {
    return this.lastError;
  }

  stop() {
    this.stopped = true;
    this.status = 'stopped';
    this.pending = null;
  }

  async onFix(fix: DeviceFix): Promise<void> {
    if (this.stopped) return;

    const now = Date.now();
    if (this.lastSent) {
      const dt = now - this.lastSentAt;
      const dist = haversineMeters(this.lastSent, fix);
      if (dt < MIN_INTERVAL_MS && dist < MIN_DISTANCE_M) {
        return;
      }
    }

    if (fix.accuracy != null && fix.accuracy > 200) {
      this.status = 'limited';
    }

    this.pending = fix;
    if (this.inFlight) return;
    await this.flush();
  }

  async flush(): Promise<void> {
    if (this.stopped || this.inFlight) return;
    const fix = this.pending;
    if (!fix) return;
    this.pending = null;
    this.inFlight = true;
    try {
      await submitTracking(this.assignmentId, fix);
      this.lastSent = fix;
      this.lastSentAt = Date.now();
      this.status = 'active';
      this.lastError = null;
    } catch (e) {
      this.lastSent = fix;
      this.lastSentAt = Date.now();
      this.status = 'error';
      this.lastError =
        e instanceof DriverWorkApiError ? e.message : 'Location upload failed';

      if (e instanceof DriverWorkApiError && (e.statusCode === 404 || e.statusCode === 409)) {
        this.stop();
      } else {
        // Enqueue location fix into persistent offline queue
        await offlineQueueStore.enqueueEvent('TRACKING_GPS', this.assignmentId, fix);
      }
    } finally {
      this.inFlight = false;
      if (this.pending && !this.stopped) {
        void this.flush();
      }
    }
  }
}

