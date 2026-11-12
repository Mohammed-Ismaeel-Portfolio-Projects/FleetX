import { useCallback, useEffect, useRef, useState } from 'react';
import { motion } from 'framer-motion';
import { useAuth } from '../../../../core/auth';
import { DispatcherSurface } from '../../../../shared/components/dispatcher/DispatcherSurface';
import { PageHeader } from '../../../../shared/components/dispatcher/PageHeader';
import { LoadingState } from '../../../../shared/components/dispatcher/LoadingState';
import { EmptyState } from '../../../../shared/components/dispatcher/EmptyState';
import { deriveFleetHealth, getVehicles } from '../services/fleet-operations.service';
import type { FleetHealth, Vehicle } from '../types/fleet-operations.types';
import FleetKPIs from '../components/FleetKPIs';
import FleetList from '../components/FleetList';
import VehicleDetailsPanel from '../components/VehicleDetailsPanel';
import MaintenancePanel from '../components/MaintenancePanel';
import VehicleTimeline from '../components/VehicleTimeline';
import AssignmentPanel from '../components/AssignmentPanel';
import FleetHealthCard from '../components/FleetHealthCard';

const POLL_INTERVAL_MS = 15_000;

export default function FleetManagementPage() {
  const { user } = useAuth();
  const isPartner = Boolean(user?.isPartnerCarrier);
  const companyId = user?.companyId ?? null;

  const [vehicles, setVehicles] = useState<Vehicle[]>([]);
  const [selectedVehicleId, setSelectedVehicleId] = useState<string>('');
  const [selectedVehicle, setSelectedVehicle] = useState<Vehicle | null>(null);
  const [fleetHealth, setFleetHealth] = useState<FleetHealth | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshKey, setRefreshKey] = useState(0);

  const selectedVehicleIdRef = useRef(selectedVehicleId);
  const inFlightRef = useRef(false);
  const hasLoadedRef = useRef(false);

  useEffect(() => {
    selectedVehicleIdRef.current = selectedVehicleId;
  }, [selectedVehicleId]);

  const applyVehicleSelection = useCallback((vehicle: Vehicle) => {
    setSelectedVehicleId(vehicle.id);
    setSelectedVehicle(vehicle);
    setFleetHealth(deriveFleetHealth(vehicle));
  }, []);

  const clearVehicleSelection = useCallback(() => {
    setSelectedVehicleId('');
    setSelectedVehicle(null);
    setFleetHealth(null);
  }, []);

  const loadFleet = useCallback(async (options?: { initial?: boolean }) => {
    if (inFlightRef.current) return;
    inFlightRef.current = true;
    const initial = Boolean(options?.initial);

    try {
      if (initial && !hasLoadedRef.current) {
        setLoading(true);
      }

      const rawVehicles = await getVehicles();

      // Company Scoping Logic & Fail-Closed Rule
      let vehiclesData: Vehicle[];
      if (isPartner) {
        if (!companyId) {
          vehiclesData = []; // Fail closed if company context missing
        } else {
          vehiclesData = rawVehicles.filter(
            (v) => !v.companyId || v.companyId === companyId
          );
        }
      } else {
        vehiclesData = rawVehicles;
      }

      setVehicles(vehiclesData);

      const currentId = selectedVehicleIdRef.current;
      if (currentId) {
        const stillPresent = vehiclesData.find((v) => v.id === currentId);
        if (stillPresent) {
          applyVehicleSelection(stillPresent);
        } else {
          clearVehicleSelection();
        }
      } else if (initial && vehiclesData.length > 0) {
        applyVehicleSelection(vehiclesData[0]);
      } else {
        clearVehicleSelection();
      }

      hasLoadedRef.current = true;
    } catch (error) {
      console.error('Error loading fleet data:', error);
    } finally {
      inFlightRef.current = false;
      if (initial) {
        setLoading(false);
      }
    }
  }, [isPartner, companyId, applyVehicleSelection, clearVehicleSelection]);

  const triggerRefresh = useCallback(() => {
    setRefreshKey((key) => key + 1);
    void loadFleet();
  }, [loadFleet]);

  useEffect(() => {
    void loadFleet({ initial: true });
  }, [loadFleet]);

  useEffect(() => {
    let intervalId: number | null = null;

    const clearPolling = () => {
      if (intervalId !== null) {
        window.clearInterval(intervalId);
        intervalId = null;
      }
    };

    const startPolling = () => {
      if (intervalId !== null || document.hidden) return;
      intervalId = window.setInterval(() => {
        if (!document.hidden) {
          triggerRefresh();
        }
      }, POLL_INTERVAL_MS);
    };

    const onVisibilityChange = () => {
      if (document.hidden) {
        clearPolling();
        return;
      }
      triggerRefresh();
      startPolling();
    };

    startPolling();
    document.addEventListener('visibilitychange', onVisibilityChange);

    return () => {
      clearPolling();
      document.removeEventListener('visibilitychange', onVisibilityChange);
    };
  }, [triggerRefresh]);

  const handleVehicleSelect = (vehicle: Vehicle) => {
    applyVehicleSelection(vehicle);
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 15 },
    visible: {
      opacity: 1,
      y: 0,
      transition: { duration: 0.6, ease: [0.16, 1, 0.3, 1] as const },
    },
  };

  if (loading) {
    return <LoadingState title="Loading fleet operations" description="Loading vehicle roster and availability counts." />;
  }

  if (!loading && vehicles.length === 0) {
    return (
      <DispatcherSurface className="w-full text-left font-sans select-none">
        <PageHeader
          title="Fleet Operations"
          subtitle={
            isPartner
              ? `Partner Fleet Workspace (${user?.companyName || 'Carrier Fleet'}) · Fleet roster and vehicle availability.`
              : 'Fleet roster, assignment commitment, and live tracking state.'
          }
          showStreaming={false}
          onRefresh={triggerRefresh}
        />
        <EmptyState
          title={isPartner ? 'No vehicles are available for your partner fleet.' : 'No vehicles found in fleet roster.'}
          description="Adjust filter criteria or contact an administrator to register vehicles."
        />
      </DispatcherSurface>
    );
  }

  return (
    <DispatcherSurface className="w-full text-left font-sans select-none">
      <motion.div variants={itemVariants}>
        <PageHeader
          title="Fleet Operations"
          subtitle={
            isPartner
              ? `Partner Fleet Workspace (${user?.companyName || 'Carrier Fleet'}) · Fleet roster and vehicle availability.`
              : 'Fleet roster, assignment commitment, and live tracking state.'
          }
          showStreaming={false}
          onRefresh={triggerRefresh}
        />
      </motion.div>

      <motion.div variants={itemVariants}>
        <FleetKPIs refreshKey={refreshKey} />
      </motion.div>

      <motion.div
        variants={itemVariants}
        className="grid grid-cols-1 lg:grid-cols-12 gap-6 w-full items-stretch"
      >
        <div className="lg:col-span-3 h-full">
          <FleetList
            vehicles={vehicles}
            selectedVehicleId={selectedVehicleId}
            onVehicleSelect={handleVehicleSelect}
          />
        </div>

        <div className="lg:col-span-3 h-full">
          <VehicleDetailsPanel vehicle={selectedVehicle} />
        </div>

        <div className="lg:col-span-3 h-full">
          <MaintenancePanel maintenance={[]} />
        </div>

        <div className="lg:col-span-3 h-full">
          <VehicleTimeline events={[]} />
        </div>
      </motion.div>

      <motion.div
        variants={itemVariants}
        className="grid grid-cols-1 lg:grid-cols-2 gap-6 w-full"
      >
        <div className="lg:col-span-1">
          <AssignmentPanel vehicle={selectedVehicle} />
        </div>

        <div className="lg:col-span-1">
          <FleetHealthCard health={fleetHealth} />
        </div>
      </motion.div>
    </DispatcherSurface>
  );
}
