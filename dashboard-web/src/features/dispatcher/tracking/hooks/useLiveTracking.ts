import { useState, useEffect, useCallback, useRef } from 'react';
import { useAuth } from '../../../../core/auth';
import type { LiveVehicle } from '../types/tracking.types';
import { trackingService } from '../services/tracking.service';
import { shipmentsService } from '../../shipments/services/shipments.service';
import type { FleetKPIs } from '../types/tracking.types';
import { realtimeConnectionService } from '../../../../core/realtime/services/realtime-connection.service';
import type { TrackingUpdateMessage } from '../../../../core/realtime/interfaces/tracking-subscription.interface';

export const POLL_INTERVAL_MS = 5000;

export function isDispatcherTrackingRealtimeEnabled(): boolean {
  return import.meta.env.VITE_REALTIME_ENABLED === 'true';
}

/**
 * Patch an already-polled vehicle from a TrackingStateBroadcasted payload.
 * Unknown vehicle_ids are ignored so WS cannot invent fleet members.
 */
export function applyFleetRealtimeUpdate(
  vehicles: LiveVehicle[],
  update: TrackingUpdateMessage
): LiveVehicle[] {
  return vehicles.map((vehicle) => {
    if (vehicle.vehicleId !== update.vehicle_id) {
      return vehicle;
    }

    return {
      ...vehicle,
      latitude: update.location?.latitude ?? vehicle.latitude,
      longitude: update.location?.longitude ?? vehicle.longitude,
      speed: update.telemetry?.speed ?? vehicle.speed,
      heading: update.telemetry?.heading ?? vehicle.heading,
      movementStatus: update.status?.movement_status ?? vehicle.movementStatus,
      onlineStatus: update.status?.online_status ?? vehicle.onlineStatus,
      lastUpdateAt: update.timestamp ?? vehicle.lastUpdateAt,
    };
  });
}

/**
 * Patch a partner shipment-scoped vehicle update from a tracking.shipment.{id} channel.
 * Ignores updates for shipment IDs not in the authorized partner set.
 */
export function applyShipmentRealtimeUpdate(
  vehicles: LiveVehicle[],
  update: TrackingUpdateMessage,
  authorizedShipmentIds: Set<string>
): LiveVehicle[] {
  if (update.shipment_id && !authorizedShipmentIds.has(update.shipment_id)) {
    return vehicles;
  }

  return vehicles.map((vehicle) => {
    if (vehicle.id === update.shipment_id || vehicle.vehicleId === update.vehicle_id) {
      return {
        ...vehicle,
        latitude: update.location?.latitude ?? vehicle.latitude,
        longitude: update.location?.longitude ?? vehicle.longitude,
        speed: update.telemetry?.speed ?? vehicle.speed,
        heading: update.telemetry?.heading ?? vehicle.heading,
        movementStatus: update.status?.movement_status ?? vehicle.movementStatus,
        onlineStatus: update.status?.online_status ?? vehicle.onlineStatus,
        lastUpdateAt: update.timestamp ?? vehicle.lastUpdateAt,
      };
    }
    return vehicle;
  });
}

/**
 * Live tracking hook supporting internal global fleet & partner shipment-scoped tracking.
 */
export function useLiveTracking() {
  const { user } = useAuth();
  const isPartner = Boolean(user?.isPartnerCarrier);
  const companyId = user?.companyId ?? null;

  const [vehicles, setVehicles] = useState<LiveVehicle[]>([]);
  const [selectedVehicleId, setSelectedVehicleId] = useState<string | null>(null);
  const [kpis, setKpis] = useState<FleetKPIs | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('All');
  const [regionFilter, setRegionFilter] = useState<string>('All');
  const [loading, setLoading] = useState(true);
  const selectedVehicleIdRef = useRef<string | null>(null);

  selectedVehicleIdRef.current = selectedVehicleId;

  const refreshVehicles = useCallback(async (showLoading = false) => {
    try {
      if (showLoading) setLoading(true);

      // PARTNER CARRIER DISPATCHER FLOW
      if (isPartner) {
        if (!companyId) {
          // FAIL CLOSED RULE: Missing partner company context -> zero tracking requests
          setVehicles([]);
          setKpis(null);
          return;
        }

        // Fetch partner-visible shipments from /api/v1/shipments
        const shipmentResponse = await shipmentsService.getShipments({ per_page: 100 });
        const rawShipments = shipmentResponse.data || [];

        // Scoped to partner's executing company
        const partnerShipments = rawShipments.filter(
          (s) => !s.executingCompanyId || s.executingCompanyId === companyId
        );

        // Map authorized partner shipments to LiveVehicle interface
        const mappedVehicles: LiveVehicle[] = partnerShipments.map((s) => {
          const vehicleId = s.activeAssignment?.vehicleCode || s.id;
          const lat = s.engineOps?.routeProgress?.latitude ?? 41.0082;
          const lng = s.engineOps?.routeProgress?.longitude ?? 28.9784;
          const speed = s.engineOps?.routeProgress?.heading != null ? 50 : 0;
          return {
            id: s.id,
            vehicleId,
            assignmentId: s.activeAssignment?.id || `asn-${s.id}`,
            driverId: `drv-${s.id}`,
            driverName: s.driver || 'Partner Driver',
            shipmentId: s.id,
            shipmentNumber: s.shipmentNumber,
            status: s.status === 'In Transit' ? 'In Transit' : s.status === 'Assigned' ? 'Active' : 'Idle',
            latitude: lat,
            longitude: lng,
            speed,
            heading: s.engineOps?.routeProgress?.heading ?? 0,
            movementStatus: s.status === 'In Transit' ? 'Moving' : 'Idle',
            onlineStatus: 'Online',
            signalQuality: 100,
            lastUpdateAt: new Date().toISOString(),
            vehicleCode: s.truck || s.shipmentNumber,
            plateNumber: s.activeAssignment?.vehiclePlate || s.truck || 'Partner Truck',
          };
        });

        setVehicles(mappedVehicles);
        setKpis(null); // Never call /vehicles/fleet-kpis for partners

        const currentSelected = selectedVehicleIdRef.current;
        if (!currentSelected && mappedVehicles.length > 0) {
          setSelectedVehicleId(mappedVehicles[0].vehicleId);
        } else if (currentSelected && !mappedVehicles.some((v) => v.vehicleId === currentSelected)) {
          setSelectedVehicleId(mappedVehicles[0]?.vehicleId ?? null);
        }
        return;
      }

      // FLEETX INTERNAL DISPATCHER FLOW
      const vehiclesResult = await trackingService.getLiveVehicles();
      const next = vehiclesResult.vehicles;

      setVehicles(next);

      const currentSelected = selectedVehicleIdRef.current;
      if (!currentSelected && next.length > 0) {
        setSelectedVehicleId(next[0].vehicleId);
      } else if (currentSelected && !next.some((v) => v.vehicleId === currentSelected)) {
        setSelectedVehicleId(next[0]?.vehicleId ?? null);
      }
    } catch (error) {
      console.error('Failed to load live tracking data:', error);
    } finally {
      if (showLoading) setLoading(false);
    }
  }, [isPartner, companyId]);

  useEffect(() => {
    const loadData = async () => {
      await refreshVehicles(true);
      if (!isPartner) {
        try {
          const kpisData = await trackingService.getFleetKPIs();
          setKpis(kpisData);
        } catch (error) {
          console.error('Failed to load KPIs:', error);
        }
      }
    };

    void loadData();

    const interval = setInterval(() => {
      void refreshVehicles(false);
    }, POLL_INTERVAL_MS);

    return () => clearInterval(interval);
  }, [refreshVehicles, isPartner]);

  // REALTIME SUBSCRIPTION ADAPTATION
  useEffect(() => {
    if (!isDispatcherTrackingRealtimeEnabled()) {
      return;
    }

    // FAIL CLOSED RULE: Partner carrier with missing companyId -> 0 subscriptions
    if (isPartner && !companyId) {
      return;
    }

    let cancelled = false;
    const unsubscribes: Array<() => void> = [];

    const boot = async () => {
      try {
        await realtimeConnectionService.connect();
        if (cancelled) return;

        if (!isPartner) {
          // Internal dispatcher: subscribe to tracking.fleet
          await realtimeConnectionService.subscribe('tracking.fleet');
          if (cancelled) return;
          const unsub = realtimeConnectionService.on<TrackingUpdateMessage>(
            'tracking.fleet',
            '.tracking.state.updated',
            (update) => {
              if (cancelled) return;
              setVehicles((current) => applyFleetRealtimeUpdate(current, update));
            }
          );
          unsubscribes.push(unsub);
        } else {
          // Partner dispatcher: subscribe ONLY to tracking.shipment.{shipmentId}
          const authorizedShipmentIds = new Set(vehicles.map((v) => v.id).filter(Boolean));

          for (const shipmentId of authorizedShipmentIds) {
            const channel = `tracking.shipment.${shipmentId}`;
            await realtimeConnectionService.subscribe(channel);
            if (cancelled) return;

            const unsub = realtimeConnectionService.on<TrackingUpdateMessage>(
              channel,
              '.tracking.state.updated',
              (update) => {
                if (cancelled) return;
                setVehicles((current) =>
                  applyShipmentRealtimeUpdate(current, update, authorizedShipmentIds)
                );
              }
            );
            unsubscribes.push(unsub);
          }
        }
      } catch (error) {
        if (!cancelled) {
          console.warn('[useLiveTracking] realtime subscribe failed; polling continues', error);
        }
      }
    };

    void boot();

    return () => {
      cancelled = true;
      for (const unsub of unsubscribes) {
        unsub();
      }
    };
  }, [isPartner, companyId, vehicles.map((v) => v.id).join(',')]);

  const selectedVehicle = vehicles.find((v) => v.vehicleId === selectedVehicleId) || null;

  const filteredVehicles = vehicles.filter((v) => {
    const matchesSearch =
      v.vehicleCode.toLowerCase().includes(searchQuery.toLowerCase()) ||
      v.driverName.toLowerCase().includes(searchQuery.toLowerCase()) ||
      v.shipmentNumber.toLowerCase().includes(searchQuery.toLowerCase());

    const matchesStatus = statusFilter === 'All' || v.status === statusFilter;
    const matchesRegion = regionFilter === 'All';

    return matchesSearch && matchesStatus && matchesRegion;
  });

  return {
    vehicles: filteredVehicles,
    selectedVehicle,
    selectedVehicleId,
    setSelectedVehicleId,
    kpis,
    searchQuery,
    setSearchQuery,
    statusFilter,
    setStatusFilter,
    regionFilter,
    setRegionFilter,
    loading,
    refresh: () => refreshVehicles(false),
  };
}
