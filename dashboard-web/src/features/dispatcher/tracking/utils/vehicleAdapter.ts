import type { LiveVehicle } from '../types/tracking.types';
import type { TrackingMapVehicle, VehicleStatus } from '../../../../shared/components/tracking-map/types';

export function mapLiveVehicleToTrackingMapVehicle(vehicle: LiveVehicle): TrackingMapVehicle {
  const status = mapVehicleStatus(vehicle.status);
  
  return {
    // Map marker / overlay identity = vehicle_id (LiveVehicle.id === vehicleId)
    id: vehicle.vehicleId,
    fleetNumber: vehicle.vehicleCode,
    plateNumber: vehicle.plateNumber,
    driver: vehicle.driverName,
    driverId: vehicle.driverId,
    shipment: vehicle.shipmentNumber,
    shipmentId: vehicle.shipmentId,
    pickupCity: vehicle.pickupCity,
    deliveryCity: vehicle.deliveryCity,
    status,
    speed: vehicle.speed,
    heading: vehicle.heading,
    signalQuality: mapSignalQuality(vehicle.signalQuality),
    lastUpdate: vehicle.lastUpdateAt,
    latitude: vehicle.latitude,
    longitude: vehicle.longitude,
  };
}

function mapVehicleStatus(status: string): VehicleStatus {
  const normalizedStatus = status.toLowerCase();
  if (normalizedStatus === 'active' || normalizedStatus === 'in transit') {
    return 'moving';
  }
  if (normalizedStatus === 'idle') {
    return 'idle';
  }
  if (normalizedStatus === 'offline' || normalizedStatus === 'delayed') {
    return 'offline';
  }
  return 'idle';
}

function mapSignalQuality(signalQuality: number): 'good' | 'weak' | 'lost' {
  if (signalQuality >= 80) return 'good';
  if (signalQuality >= 50) return 'weak';
  return 'lost';
}

export function mapLiveVehiclesToTrackingMapVehicles(vehicles: LiveVehicle[]): TrackingMapVehicle[] {
  return vehicles.map(mapLiveVehicleToTrackingMapVehicle);
}
