import { useCallback, useEffect, useRef } from 'react';
import { realtimeConnectionService } from '../../../../core/realtime/services/realtime-connection.service';
import { trackingSubscriptionManager } from '../../../../core/realtime/services/tracking-subscription-manager.service';
import type { RouteDeviationUpdateMessage } from '../../../../core/realtime/interfaces/tracking-subscription.interface';
import { useToast } from '../../../../shared/components/toast';

interface UseRouteDeviationSubscriptionOptions {
  vehicleId: string | null;
  shipmentId: string | null;
  fleetNumber?: string;
  enabled?: boolean;
  onDeviation: (message: RouteDeviationUpdateMessage) => void;
}

/**
 * Subscribes to tracking.route.deviation for the selected vehicle/shipment.
 * Shows a professional toast; does not contain routing business logic.
 */
export function useRouteDeviationSubscription({
  vehicleId,
  shipmentId,
  fleetNumber,
  enabled = true,
  onDeviation,
}: UseRouteDeviationSubscriptionOptions) {
  const toast = useToast();
  const onDeviationRef = useRef(onDeviation);
  onDeviationRef.current = onDeviation;

  const handleDeviation = useCallback(
    (message: RouteDeviationUpdateMessage) => {
      if (vehicleId && message.vehicle_id !== vehicleId) {
        return;
      }
      if (shipmentId && message.shipment_id !== shipmentId) {
        return;
      }

      const truckLabel = fleetNumber || message.vehicle_id;
      const meters = Math.round(message.deviation_distance || 0);

      toast.warning(
        `Truck ${truckLabel} left its assigned route`,
        `Deviation: ${meters} meters. Calculating new truck route…`
      );

      onDeviationRef.current(message);
    },
    [vehicleId, shipmentId, fleetNumber, toast]
  );

  useEffect(() => {
    if (!enabled || !vehicleId) {
      return;
    }

    let cancelled = false;

    const subscribe = async () => {
      try {
        await realtimeConnectionService.connect();
        if (cancelled) return;

        await trackingSubscriptionManager.subscribeVehicle(vehicleId, {
          onUpdate: () => {
            // Position updates are handled by live tracking polling / other subscribers.
          },
          onRouteDeviation: handleDeviation,
          onError: (error) => {
            console.warn('[useRouteDeviationSubscription]', error.message);
          },
        });
      } catch (error) {
        console.warn('[useRouteDeviationSubscription] subscribe failed', error);
      }
    };

    subscribe();

    return () => {
      cancelled = true;
      void trackingSubscriptionManager.unsubscribe();
    };
  }, [enabled, vehicleId, handleDeviation]);
}
