import { memo, useCallback, useState } from 'react';
import { TrackingMap as SharedTrackingMap } from '../../../../shared/components/tracking-map';
import type { LiveVehicle } from '../types/tracking.types';
import { mapLiveVehiclesToTrackingMapVehicles } from '../utils/vehicleAdapter';
import DispatcherRouteOverlay from './DispatcherRouteOverlay';

interface FleetMapProps {
  vehicles: LiveVehicle[];
  selectedVehicle: LiveVehicle | null;
  onVehicleSelect?: (vehicle: LiveVehicle) => void;
}

function FleetMapComponent({ vehicles, selectedVehicle, onVehicleSelect }: FleetMapProps) {
  const [alertVehicleId, setAlertVehicleId] = useState<string | null>(null);
  const mappedVehicles = mapLiveVehiclesToTrackingMapVehicles(vehicles);

  const handleVehicleSelect = (mappedVehicle: { id: string }) => {
    setAlertVehicleId(null);
    const originalVehicle = vehicles.find((v) => v.vehicleId === mappedVehicle.id);
    if (originalVehicle && onVehicleSelect) {
      onVehicleSelect(originalVehicle);
    }
  };

  const mappedSelectedVehicle = selectedVehicle
    ? mapLiveVehiclesToTrackingMapVehicles([selectedVehicle])[0]
    : null;

  const handleAlertChange = useCallback((vehicleId: string | null, hasAlert: boolean) => {
    setAlertVehicleId(hasAlert && vehicleId ? vehicleId : null);
  }, []);

  const alertVehicleIds = alertVehicleId ? { [alertVehicleId]: true } : {};

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl shadow-lg overflow-hidden relative h-[600px] w-full [.tracking-workspace:fullscreen_&]:h-full [.tracking-workspace:fullscreen_&]:w-full [.tracking-workspace:fullscreen_&]:rounded-none [.tracking-workspace:fullscreen_&]:border-none">
      <SharedTrackingMap
        vehicles={mappedVehicles}
        onVehicleSelect={handleVehicleSelect}
        selectedVehicleId={selectedVehicle?.vehicleId ?? null}
        enableRouteMonitoring={false}
        alertVehicleIds={alertVehicleIds}
        customOverlay={
          mappedSelectedVehicle ? (
            <DispatcherRouteOverlay
              selectedVehicle={mappedSelectedVehicle}
              onAlertChange={handleAlertChange}
            />
          ) : null
        }
      />
    </div>
  );
}

export default memo(FleetMapComponent);
