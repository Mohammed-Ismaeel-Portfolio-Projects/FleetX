import { useCallback, useEffect, useState } from 'react';
import type {
  TrackingSession,
  TrackingSessionEventItem,
  TrackingSessionOverridePayload,
} from '../types/trackingSession.types';
import { trackingSessionService } from '../services/trackingSession.service';

export function useTrackingSession(shipmentOrderId: string | undefined) {
  const [session, setSession] = useState<TrackingSession | null>(null);
  const [events, setEvents] = useState<TrackingSessionEventItem[]>([]);
  const [isLoading, setIsLoading] = useState<boolean>(false);
  const [isActionLoading, setIsActionLoading] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);

  const fetchSession = useCallback(async () => {
    if (!shipmentOrderId) {
      setSession(null);
      setEvents([]);
      return;
    }
    setIsLoading(true);
    setError(null);
    try {
      const result = await trackingSessionService.getSessionByShipmentOrder(shipmentOrderId);
      setSession(result);
      if (result?.id) {
        const evList = await trackingSessionService.getEvents(result.id);
        setEvents(evList);
      } else {
        setEvents([]);
      }
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to load tracking session';
      setError(message);
    } finally {
      setIsLoading(false);
    }
  }, [shipmentOrderId]);

  useEffect(() => {
    void fetchSession();
  }, [fetchSession]);

  const refreshEvents = async (sessionId: string) => {
    try {
      const evList = await trackingSessionService.getEvents(sessionId);
      setEvents(evList);
    } catch {
      // Ignore event fetch error silently
    }
  };

  const startSession = async () => {
    if (!shipmentOrderId || isActionLoading) return;
    setIsActionLoading(true);
    setError(null);
    try {
      const updated = await trackingSessionService.startSession(shipmentOrderId);
      setSession(updated);
      void refreshEvents(updated.id);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to start tracking session';
      setError(message);
    } finally {
      setIsActionLoading(false);
    }
  };

  const pauseSession = async () => {
    if (!session || isActionLoading) return;
    setIsActionLoading(true);
    setError(null);
    try {
      const updated = await trackingSessionService.pauseSession(session.id);
      setSession(updated);
      void refreshEvents(updated.id);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to pause tracking session';
      setError(message);
    } finally {
      setIsActionLoading(false);
    }
  };

  const resumeSession = async () => {
    if (!session || isActionLoading) return;
    setIsActionLoading(true);
    setError(null);
    try {
      const updated = await trackingSessionService.resumeSession(session.id);
      setSession(updated);
      void refreshEvents(updated.id);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to resume tracking session';
      setError(message);
    } finally {
      setIsActionLoading(false);
    }
  };

  const stopSession = async () => {
    if (!session || isActionLoading) return;
    setIsActionLoading(true);
    setError(null);
    try {
      const updated = await trackingSessionService.stopSession(session.id);
      setSession(updated);
      void refreshEvents(updated.id);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to stop tracking session';
      setError(message);
    } finally {
      setIsActionLoading(false);
    }
  };

  const applyOverride = async (payload: TrackingSessionOverridePayload) => {
    if (!session || isActionLoading) return;
    setIsActionLoading(true);
    setError(null);
    try {
      const updated = await trackingSessionService.updateOverride(session.id, payload);
      setSession(updated);
      void refreshEvents(updated.id);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to update simulation override';
      setError(message);
    } finally {
      setIsActionLoading(false);
    }
  };

  const triggerSpeedViolation = () => applyOverride({ forced_speed_kmh: 120 });
  const triggerSuddenStop = () => applyOverride({ forced_speed_kmh: 0, forced_deceleration: true });
  const triggerRouteDeviation = () => applyOverride({ forced_route_deviation: true });
  const resetOverrides = () =>
    applyOverride({
      forced_speed_kmh: null,
      forced_deceleration: false,
      forced_route_deviation: false,
    });

  return {
    session,
    events,
    isLoading,
    isActionLoading,
    error,
    refresh: fetchSession,
    refreshEvents: () => session?.id && refreshEvents(session.id),
    startSession,
    pauseSession,
    resumeSession,
    stopSession,
    triggerSpeedViolation,
    triggerSuddenStop,
    triggerRouteDeviation,
    resetOverrides,
  };
}
