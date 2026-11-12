export type VehicleStatus = 'moving' | 'idle' | 'offline';
export type SignalQuality = 'good' | 'weak' | 'lost';

export interface TrackingMapVehicle {
  id: string;
  fleetNumber: string;
  plateNumber: string;
  driver: string;
  driverId: string;
  shipment: string;
  shipmentId: string;
  pickupCity?: string;
  deliveryCity?: string;
  status: VehicleStatus;
  speed: number;
  heading: number;
  signalQuality: SignalQuality;
  lastUpdate: string;
  latitude?: number;
  longitude?: number;
}

export interface TrailPoint {
  lat: number;
  lng: number;
  timestamp?: string;
}

export interface MapBounds {
  north: number;
  south: number;
  east: number;
  west: number;
}
