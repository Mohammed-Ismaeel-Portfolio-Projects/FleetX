import { memo } from 'react';
import { TrackingMap as SharedTrackingMap } from '../../../../shared/components/tracking-map';
import type { LiveVehicle } from '../types/tracking.types';
import { mapLiveVehiclesToTrackingMapVehicles } from '../utils/vehicleAdapter';
import DispatcherRouteOverlay from './DispatcherRouteOverlay';

interface TrackingMapProps {
  vehicles: LiveVehicle[];
  selectedVehicle: LiveVehicle | null;
  onVehicleSelect: (vehicle: LiveVehicle) => void;
}

function TrackingMapComponent({
  vehicles,
  selectedVehicle,
  onVehicleSelect,
}: TrackingMapProps) {
  const mappedVehicles = mapLiveVehiclesToTrackingMapVehicles(vehicles);

  const handleVehicleSelect = (mappedVehicle: any) => {
    const originalVehicle = vehicles.find(v => v.id === mappedVehicle.id);
    if (originalVehicle) {
      onVehicleSelect(originalVehicle);
    }
  };

  const mappedSelectedVehicle = selectedVehicle 
    ? mapLiveVehiclesToTrackingMapVehicles([selectedVehicle])[0]
    : null;

  console.log("[DEBUG] TrackingMap - selectedVehicle:", selectedVehicle);
  console.log("[DEBUG] TrackingMap - mappedSelectedVehicle:", mappedSelectedVehicle);
  console.log("[DEBUG] TrackingMap - has customOverlay:", !!mappedSelectedVehicle);

  return (
    <SharedTrackingMap
      vehicles={mappedVehicles}
      onVehicleSelect={handleVehicleSelect}
      enableRouteMonitoring={false}
      customOverlay={mappedSelectedVehicle && <DispatcherRouteOverlay selectedVehicle={mappedSelectedVehicle} />}
    />
  );
}

export default memo(TrackingMapComponent);
export type { TrackingMapProps };
