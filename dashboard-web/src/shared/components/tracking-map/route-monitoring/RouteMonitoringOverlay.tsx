import { memo, useEffect, useState, useRef } from 'react';
import RouteMarkers from './RouteMarkers';
import RouteProgressRenderer from './RouteProgressRenderer';
import RouteMonitoringCard from './RouteMonitoringCard';
import { routeMonitoringService, type RouteMonitoringData } from '../../../../features/admin/tracking-dashboard/services/route/route-monitoring.service';
import { trackingSubscriptionManager } from '../../../../core/realtime/services/tracking-subscription-manager.service';
import type { TrackingMapVehicle } from '../types';

interface RouteMonitoringOverlayProps {
  selectedVehicle: TrackingMapVehicle | null;
  vehicleDetails: any;
}

function RouteMonitoringOverlayComponent({
  selectedVehicle,
  vehicleDetails,
}: RouteMonitoringOverlayProps) {
  const [routeData, setRouteData] = useState<RouteMonitoringData | null>(null);
  const [opacity, setOpacity] = useState(0);
  const [isLoading, setIsLoading] = useState(false);
  const updateTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    if (!selectedVehicle || !vehicleDetails?.shipment) {
      setRouteData(null);
      return;
    }

    const calculateRoute = async () => {
      setIsLoading(true);
      try {
        const data = await routeMonitoringService.calculateRouteMonitoring(
          selectedVehicle.shipment,
          selectedVehicle.shipmentId,
          vehicleDetails.shipment.origin,
          vehicleDetails.shipment.destination,
          {
            latitude: selectedVehicle.latitude || 0,
            longitude: selectedVehicle.longitude || 0,
          },
          selectedVehicle.speed,
          selectedVehicle.speed * 0.9
        );
        setRouteData(data);
      } catch (error) {
        console.error('Error calculating route:', error);
      } finally {
        setIsLoading(false);
      }
    };

    calculateRoute();
  }, [selectedVehicle, vehicleDetails]);

  useEffect(() => {
    if (!selectedVehicle || !routeData || isLoading) return;

    if (updateTimeoutRef.current) {
      clearTimeout(updateTimeoutRef.current);
    }

    updateTimeoutRef.current = setTimeout(async () => {
      try {
        const updatedData = await routeMonitoringService.updateRouteMonitoring(
          routeData,
          {
            latitude: selectedVehicle.latitude || 0,
            longitude: selectedVehicle.longitude || 0,
          },
          selectedVehicle.speed
        );
        setRouteData(updatedData);
      } catch (error) {
        console.error('Error updating route:', error);
      }
    }, 500);

    return () => {
      if (updateTimeoutRef.current) {
        clearTimeout(updateTimeoutRef.current);
      }
    };
  }, [selectedVehicle?.latitude, selectedVehicle?.longitude, selectedVehicle?.speed, routeData, isLoading]);

  const lastRouteTimestampRef = useRef<number>(0);

  useEffect(() => {
    if (!selectedVehicle?.id || !selectedVehicle?.shipmentId) {
      return;
    }

    let isSubscribed = true;

    const setupSubscription = async () => {
      try {
        await trackingSubscriptionManager.subscribeVehicle(selectedVehicle.id, {
          onUpdate: () => {},
          onRouteUpdated: (message) => {
            if (!isSubscribed || !message) return;

            const eventVehicleId = String(message.vehicle_id || '');
            const eventShipmentId = String(message.shipment_id || '');
            if (
              (eventVehicleId && eventVehicleId !== String(selectedVehicle.id)) &&
              (eventShipmentId && eventShipmentId !== String(selectedVehicle.shipmentId))
            ) {
              return;
            }

            const timestampStr = message.metadata?.broadcast_at || message.metadata?.created_at;
            const eventTime = timestampStr ? new Date(timestampStr).getTime() : Date.now();
            if (lastRouteTimestampRef.current > 0 && eventTime <= lastRouteTimestampRef.current) {
              return;
            }

            const rawCoords = message.route?.geometry?.coordinates || message.route?.coordinates;
            if (!Array.isArray(rawCoords) || rawCoords.length < 2) {
              return;
            }

            lastRouteTimestampRef.current = eventTime;

            void routeMonitoringService
              .calculateRouteMonitoring(
                selectedVehicle.shipment || '',
                selectedVehicle.shipmentId || '',
                vehicleDetails?.shipment?.origin || '',
                vehicleDetails?.shipment?.destination || '',
                {
                  latitude: selectedVehicle.latitude || 0,
                  longitude: selectedVehicle.longitude || 0,
                },
                selectedVehicle.speed || 0,
                (selectedVehicle.speed || 0) * 0.9
              )
              .then((freshData) => {
                if (isSubscribed && freshData?.routeData) {
                  setRouteData(freshData);
                }
              })
              .catch((err) => {
                console.warn('[RouteMonitoringOverlay] Error updating route from event:', err);
              });
          },
        });
      } catch (err) {
        console.warn('[RouteMonitoringOverlay] Subscription error:', err);
      }
    };

    void setupSubscription();

    return () => {
      isSubscribed = false;
      void trackingSubscriptionManager.unsubscribe();
    };
  }, [selectedVehicle?.id, selectedVehicle?.shipmentId, selectedVehicle?.shipment, vehicleDetails?.shipment]);

  useEffect(() => {
    if (selectedVehicle && routeData) {
      const fadeIn = setInterval(() => {
        setOpacity(prev => {
          if (prev >= 1) {
            clearInterval(fadeIn);
            return 1;
          }
          return prev + 0.1;
        });
      }, 30);
      return () => clearInterval(fadeIn);
    } else {
      const fadeOut = setInterval(() => {
        setOpacity(prev => {
          if (prev <= 0) {
            clearInterval(fadeOut);
            return 0;
          }
          return prev - 0.1;
        });
      }, 30);
      return () => clearInterval(fadeOut);
    }
  }, [selectedVehicle, routeData]);

  const isVisible = selectedVehicle !== null && routeData !== null && opacity > 0;

  if (!isVisible) return null;

  if (!routeData || !routeData.routeData) return null;

  return (
    <>
      <RouteProgressRenderer
        routeData={routeData.routeData}
        isVisible={isVisible}
        opacity={opacity}
      />

      <RouteMarkers
        origin={routeData.routeData.origin}
        destination={routeData.routeData.destination}
        originCity={routeData.originCity}
        destinationCity={routeData.destinationCity}
        shipmentNumber={routeData.shipmentNumber}
        isVisible={isVisible}
      />

      <RouteMonitoringCard
        data={routeData}
        isVisible={isVisible}
        opacity={opacity}
      />
    </>
  );
}

export default memo(RouteMonitoringOverlayComponent);
