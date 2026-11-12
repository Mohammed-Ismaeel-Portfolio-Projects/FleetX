/**
 * Dispatcher Route Overlay
 *
 * Enterprise route monitoring for the selected truck:
 * loads GraphHopper routes, shows ops panel, listens for deviation,
 * refreshes polyline after auto-reroute with a smooth fade transition.
 */

import { memo, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import RouteProgressRenderer from '../../../../shared/components/tracking-map/route-monitoring/RouteProgressRenderer';
import RouteMarkers from '../../../../shared/components/tracking-map/route-monitoring/RouteMarkers';
import RouteMonitoringCard from '../../../../shared/components/tracking-map/route-monitoring/RouteMonitoringCard';
import { routeApiService } from '../../../../features/admin/tracking-dashboard/services/route/route-api.service';
import { adaptRouteData } from '../../../../features/admin/tracking-dashboard/services/route/route-data.adapter';
import { routeProgressService } from '../../../../features/admin/tracking-dashboard/services/route/route-progress.service';
import type {
  RouteData,
  RoutePoint,
} from '../../../../features/admin/tracking-dashboard/services/route/route-provider.interface';
import type { RouteMonitoringData } from '../../../../features/admin/tracking-dashboard/services/route/route-monitoring.service';
import type { TrackingMapVehicle } from '../../../../shared/components/tracking-map/types';
import type { DispatcherRouteStatus } from '../../../../shared/components/tracking-map/route-monitoring/route-status.types';
import type { RouteDeviationUpdateMessage } from '../../../../core/realtime/interfaces/tracking-subscription.interface';
import { useRouteDeviationSubscription } from '../hooks/useRouteDeviationSubscription';
import { useToast } from '../../../../shared/components/toast';

interface DispatcherRouteOverlayProps {
  selectedVehicle: TrackingMapVehicle | null;
  onAlertChange?: (vehicleId: string | null, hasAlert: boolean) => void;
}

interface LoadedRoute {
  shipmentId: string;
  allPoints: RoutePoint[];
  origin: RoutePoint;
  destination: RoutePoint;
  totalDistance: number;
  originCity: string;
  destinationCity: string;
  shipmentNumber: string;
  routeStatusFromApi: DispatcherRouteStatus;
  deviationDistanceMeters: number;
}

function formatEta(remainingDistanceKm: number, speedKmh: number): string {
  if (remainingDistanceKm <= 0.05) return 'Arrived';
  const speed = speedKmh > 0 ? speedKmh : 60;
  const hours = remainingDistanceKm / speed;
  const hoursInt = Math.floor(hours);
  const minutes = Math.round((hours - hoursInt) * 60);

  if (hoursInt === 0) return `${minutes}m`;
  if (hoursInt < 24) return `${hoursInt}h ${minutes}m`;

  const days = Math.floor(hoursInt / 24);
  const remainingHours = hoursInt % 24;
  return `${days}d ${remainingHours}h`;
}

function buildRouteData(loaded: LoadedRoute, currentPosition: RoutePoint): RouteData {
  const progress = routeProgressService.calculateProgress(
    loaded.allPoints,
    currentPosition,
    loaded.totalDistance
  );

  return {
    origin: loaded.origin,
    destination: loaded.destination,
    completedSegment: progress.completedSegment,
    remainingSegment: progress.remainingSegment,
    totalDistance: loaded.totalDistance,
    completedDistance: progress.completedDistance,
    remainingDistance: progress.remainingDistance,
  };
}

function mapApiStatus(
  raw: unknown,
  vehicle: TrackingMapVehicle | null,
  progressPercent: number
): DispatcherRouteStatus {
  if (vehicle?.status === 'offline') return 'OFFLINE';
  if (progressPercent >= 99) return 'ARRIVED';

  const status = String(raw || 'ON_ROUTE').toUpperCase();
  if (status === 'DEVIATED') return 'DEVIATED';
  return 'ON_ROUTE';
}

function DispatcherRouteOverlayComponent({
  selectedVehicle,
  onAlertChange,
}: DispatcherRouteOverlayProps) {
  const toast = useToast();
  const [routeData, setRouteData] = useState<RouteData | null>(null);
  const [loadedRoute, setLoadedRoute] = useState<LoadedRoute | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [routeStatus, setRouteStatus] = useState<DispatcherRouteStatus>('ON_ROUTE');
  const [deviationDistance, setDeviationDistance] = useState<number>(0);
  const [isRerouting, setIsRerouting] = useState(false);
  const [routeOpacity, setRouteOpacity] = useState(1);

  const loadedShipmentIdRef = useRef<string | null>(null);
  const requestIdRef = useRef(0);
  const rerouteTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const shipmentId = selectedVehicle?.shipmentId || null;
  const vehicleId = selectedVehicle?.id || null;

  const clearRerouteTimer = useCallback(() => {
    if (rerouteTimerRef.current) {
      clearTimeout(rerouteTimerRef.current);
      rerouteTimerRef.current = null;
    }
  }, []);

  const resetOverlayState = useCallback(() => {
    clearRerouteTimer();
    loadedShipmentIdRef.current = null;
    setRouteData(null);
    setLoadedRoute(null);
    setError(null);
    setIsLoading(false);
    setRouteStatus('ON_ROUTE');
    setDeviationDistance(0);
    setIsRerouting(false);
    setRouteOpacity(1);
    onAlertChange?.(null, false);
  }, [clearRerouteTimer, onAlertChange]);

  const applyLoadedResponse = useCallback(
    (
      shipment: string,
      responseData: NonNullable<Awaited<ReturnType<typeof routeApiService.getShipmentRoute>>['data']>,
      vehicle: TrackingMapVehicle,
      animateIn: boolean
    ) => {
      const currentPosition = {
        latitude: vehicle.latitude || 0,
        longitude: vehicle.longitude || 0,
      };

      const adapted = adaptRouteData(responseData, currentPosition);
      const geometryPoints = (responseData.geometry.coordinates || [])
        .map((coord) => ({
          latitude: Number(coord.latitude),
          longitude: Number(coord.longitude),
        }))
        .filter(
          (point) => Number.isFinite(point.latitude) && Number.isFinite(point.longitude)
        );

      const progressPercent =
        adapted.totalDistance > 0
          ? Math.round((adapted.completedDistance / adapted.totalDistance) * 100)
          : 0;

      const apiStatus = mapApiStatus(
        responseData.metadata?.route_status,
        vehicle,
        progressPercent
      );

      const nextLoaded: LoadedRoute = {
        shipmentId: shipment,
        allPoints:
          geometryPoints.length >= 2
            ? geometryPoints
            : [adapted.origin, adapted.destination],
        origin: adapted.origin,
        destination: adapted.destination,
        totalDistance: adapted.totalDistance,
        originCity: String(
          responseData.metadata?.pickup_city || vehicle.pickupCity || 'Origin'
        ),
        destinationCity: String(
          responseData.metadata?.delivery_city || vehicle.deliveryCity || 'Destination'
        ),
        shipmentNumber: String(
          responseData.metadata?.shipment_number || vehicle.shipment || shipment
        ),
        routeStatusFromApi: apiStatus,
        deviationDistanceMeters: Number(responseData.metadata?.deviation_distance ?? 0),
      };

      loadedShipmentIdRef.current = shipment;
      setLoadedRoute(nextLoaded);
      setRouteData(buildRouteData(nextLoaded, currentPosition));
      setDeviationDistance(nextLoaded.deviationDistanceMeters);
      setRouteStatus(apiStatus === 'DEVIATED' ? 'DEVIATED' : apiStatus);
      setIsRerouting(false);
      onAlertChange?.(vehicle.id, apiStatus === 'DEVIATED');

      if (animateIn) {
        setRouteOpacity(0.15);
        requestAnimationFrame(() => {
          setRouteOpacity(1);
        });
        toast.success('Route updated', 'New truck-compatible route is active.');
      } else {
        setRouteOpacity(1);
      }
    },
    [onAlertChange, toast]
  );

  const loadRoute = useCallback(
    async (shipment: string, vehicle: TrackingMapVehicle, options?: { force?: boolean; animate?: boolean }) => {
      if (!options?.force && loadedShipmentIdRef.current === shipment && !options?.animate) {
        return;
      }

      const requestId = ++requestIdRef.current;
      setIsLoading(true);
      setError(null);

      try {
        const response = await routeApiService.getShipmentRoute(shipment);
        if (requestId !== requestIdRef.current) return;

        if (response.success && response.data) {
          applyLoadedResponse(shipment, response.data, vehicle, Boolean(options?.animate));
        } else {
          loadedShipmentIdRef.current = null;
          setRouteData(null);
          setLoadedRoute(null);
          setError(response.message || 'No route data available');
        }
      } catch (err) {
        if (requestId !== requestIdRef.current) return;
        console.error('[DispatcherRouteOverlay] Error loading route:', err);
        loadedShipmentIdRef.current = null;
        setError('Failed to load route');
        setRouteData(null);
        setLoadedRoute(null);
      } finally {
        if (requestId === requestIdRef.current) {
          setIsLoading(false);
        }
      }
    },
    [applyLoadedResponse]
  );

  // Truck switch / clear
  useEffect(() => {
    if (!selectedVehicle || !shipmentId) {
      resetOverlayState();
      return;
    }

    if (loadedShipmentIdRef.current && loadedShipmentIdRef.current !== shipmentId) {
      resetOverlayState();
    }

    void loadRoute(shipmentId, selectedVehicle);
  }, [selectedVehicle?.id, shipmentId]); // eslint-disable-line react-hooks/exhaustive-deps

  // Live progress from GPS position updates
  useEffect(() => {
    if (!selectedVehicle || !loadedRoute) return;
    if (loadedRoute.shipmentId !== selectedVehicle.shipmentId) return;

    const currentPosition = {
      latitude: selectedVehicle.latitude || 0,
      longitude: selectedVehicle.longitude || 0,
    };

    const next = buildRouteData(loadedRoute, currentPosition);
    setRouteData(next);

    const progressPercent =
      next.totalDistance > 0
        ? Math.round((next.completedDistance / next.totalDistance) * 100)
        : 0;

    if (selectedVehicle.status === 'offline') {
      setRouteStatus('OFFLINE');
    } else if (progressPercent >= 99) {
      setRouteStatus('ARRIVED');
      onAlertChange?.(selectedVehicle.id, false);
    } else if (!isRerouting && routeStatus !== 'DEVIATED' && routeStatus !== 'REROUTING') {
      setRouteStatus(loadedRoute.routeStatusFromApi === 'DEVIATED' ? 'DEVIATED' : 'ON_ROUTE');
    }
  }, [
    loadedRoute,
    selectedVehicle?.shipmentId,
    selectedVehicle?.latitude,
    selectedVehicle?.longitude,
    selectedVehicle?.status,
    selectedVehicle?.id,
    isRerouting,
    routeStatus,
    onAlertChange,
  ]);

  const handleDeviation = useCallback(
    (message: RouteDeviationUpdateMessage) => {
      if (!selectedVehicle || !shipmentId) return;
      if (message.shipment_id !== shipmentId) return;

      setRouteStatus('DEVIATED');
      setDeviationDistance(message.deviation_distance || 0);
      setIsRerouting(true);
      onAlertChange?.(selectedVehicle.id, true);

      // Fade old polyline while backend auto-reroutes
      setRouteOpacity(0.25);
      setRouteStatus('REROUTING');

      clearRerouteTimer();
      rerouteTimerRef.current = setTimeout(() => {
        void loadRoute(shipmentId, selectedVehicle, { force: true, animate: true });
      }, 3800);
    },
    [selectedVehicle, shipmentId, onAlertChange, clearRerouteTimer, loadRoute]
  );

  useRouteDeviationSubscription({
    vehicleId,
    shipmentId,
    fleetNumber: selectedVehicle?.fleetNumber,
    enabled: Boolean(vehicleId && shipmentId),
    onDeviation: handleDeviation,
  });

  useEffect(() => () => clearRerouteTimer(), [clearRerouteTimer]);

  const monitoringData = useMemo<RouteMonitoringData | null>(() => {
    if (!routeData || !loadedRoute || !selectedVehicle) return null;

    const progressPercent =
      routeData.totalDistance > 0
        ? Math.round((routeData.completedDistance / routeData.totalDistance) * 100)
        : 0;

    return {
      shipmentNumber: loadedRoute.shipmentNumber,
      originCity: loadedRoute.originCity,
      destinationCity: loadedRoute.destinationCity,
      assignedTruck: selectedVehicle.fleetNumber || selectedVehicle.plateNumber,
      driverName: selectedVehicle.driver,
      currentPosition: {
        latitude: selectedVehicle.latitude || 0,
        longitude: selectedVehicle.longitude || 0,
      },
      currentSpeed: selectedVehicle.speed || 0,
      averageSpeed: (selectedVehicle.speed || 0) * 0.9,
      routeData,
      progressPercent: Math.min(100, Math.max(0, progressPercent)),
      eta: formatEta(routeData.remainingDistance, selectedVehicle.speed || 0),
      isLoading,
      error,
      routeStatus,
      deviationDistanceMeters: deviationDistance,
      lastGpsUpdate: selectedVehicle.lastUpdate,
      movementStatus: selectedVehicle.status,
      isRerouting,
    };
  }, [
    routeData,
    loadedRoute,
    selectedVehicle,
    isLoading,
    error,
    routeStatus,
    deviationDistance,
    isRerouting,
  ]);

  if (!selectedVehicle || !shipmentId) {
    return null;
  }

  if (isLoading && !routeData) {
    return (
      <div
        style={{
          position: 'absolute',
          top: 12,
          left: 12,
          zIndex: 1000,
          pointerEvents: 'none',
        }}
      >
        <div
          style={{
            background: 'rgba(14,19,32,0.95)',
            border: '1px solid rgba(6,182,212,0.25)',
            borderRadius: 12,
            padding: '12px 14px',
            color: '#67e8f9',
            fontSize: 11,
            fontWeight: 600,
          }}
        >
          Loading GraphHopper truck route…
        </div>
      </div>
    );
  }

  if (error && !routeData) {
    return (
      <div
        style={{
          position: 'absolute',
          top: 12,
          left: 12,
          zIndex: 1000,
          pointerEvents: 'none',
        }}
      >
        <div
          style={{
            background: 'rgba(14,19,32,0.95)',
            border: '1px solid rgba(239,68,68,0.35)',
            borderRadius: 12,
            padding: '12px 14px',
            color: '#fca5a5',
            fontSize: 11,
            fontWeight: 600,
          }}
        >
          {error}
        </div>
      </div>
    );
  }

  if (!routeData || !monitoringData || !loadedRoute) {
    return null;
  }

  const completedCount = routeData.completedSegment?.points?.length ?? 0;
  const remainingCount = routeData.remainingSegment?.points?.length ?? 0;
  if (completedCount < 2 && remainingCount < 2) {
    return null;
  }

  return (
    <>
      <RouteProgressRenderer
        routeData={routeData}
        isVisible={true}
        opacity={routeOpacity}
        variant={routeStatus === 'DEVIATED' || routeStatus === 'REROUTING' ? 'alert' : 'default'}
      />

      <RouteMarkers
        origin={routeData.origin}
        destination={routeData.destination}
        originCity={loadedRoute.originCity}
        destinationCity={loadedRoute.destinationCity}
        shipmentNumber={loadedRoute.shipmentNumber}
        isVisible={true}
      />

      <RouteMonitoringCard
        data={monitoringData}
        isVisible={true}
        opacity={1}
      />
    </>
  );
}

export default memo(DispatcherRouteOverlayComponent);
