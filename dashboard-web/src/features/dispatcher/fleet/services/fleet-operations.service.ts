import { axiosClientWithInterceptors } from '../../../../core/api';
import type {
  Vehicle,
  FleetHealth,
  Maintenance,
  VehicleTimelineEvent,
  VehicleStatus,
  AvailabilityStatus,
} from '../types/fleet-operations.types';

interface ApiEnvelope<T> {
  success: boolean;
  message: string;
  data: T;
}

interface VehicleApiResponse {
  id: string;
  operational_code: string;
  fleet_number: string;
  plate_number: string;
  vin: string;
  manufacturer: string;
  model: string;
  production_year: number;
  payload_capacity_kg: number | null;
  fuel_tank_capacity_l: number | null;
  current_odometer: number | null;
  status_id: number;
  is_active: boolean;
  insurance_expiry_date: string | null;
  registration_expiry_date: string | null;
  company_id?: string | null;
  company?: {
    id: string;
    company_name?: string;
  } | null;
  status?: {
    id: number;
    name: string;
    type: string;
  };
  vehicle_type?: {
    id: number;
    name: string;
  };
  active_assignment?: {
    id: string;
    assignment_code?: string;
    is_active?: boolean;
    status_id?: number;
    driver?: {
      id: string;
      first_name: string;
      last_name: string;
      full_name?: string;
      phone: string;
    } | null;
    shipment?: {
      id: string;
      shipment_number: string;
    } | null;
    trailer?: {
      id: string;
      plate_number: string;
      trailer_type?: string;
    } | null;
  } | null;
  tracking_state?: {
    id: string;
    latitude: number;
    longitude: number;
    speed?: number;
    movement_status?: string;
    online_status?: string;
    signal_quality?: string;
    last_update_at: string;
  } | null;
}

const OFFICIAL_VEHICLE_STATUSES: VehicleStatus[] = [
  'Available',
  'Assigned',
  'In Transit',
  'Loading',
  'Unloading',
  'At Customs',
  'Maintenance',
  'Out of Service',
  'Inactive',
];

const LOCKED_STATUSES = new Set<VehicleStatus>(['Maintenance', 'Out of Service', 'Inactive']);

function mapStatus(name?: string): VehicleStatus | undefined {
  if (name && OFFICIAL_VEHICLE_STATUSES.includes(name as VehicleStatus)) {
    return name as VehicleStatus;
  }
  return undefined;
}

function mapAvailability(status: VehicleStatus | undefined, hasActiveAssignment: boolean): AvailabilityStatus {
  if ((status && LOCKED_STATUSES.has(status)) || hasActiveAssignment) {
    return 'Unavailable';
  }
  return 'Available';
}

function mapApiVehicleToVehicle(apiVehicle: VehicleApiResponse): Vehicle {
  const assignment = apiVehicle.active_assignment ?? undefined;
  const driver = assignment?.driver ?? undefined;
  const shipment = assignment?.shipment ?? undefined;
  const trailer = assignment?.trailer ?? undefined;
  const trackingState = apiVehicle.tracking_state ?? undefined;
  const status = mapStatus(apiVehicle.status?.name);
  const hasActiveAssignment = Boolean(assignment);

  const driverName = driver
    ? (driver.full_name || `${driver.first_name} ${driver.last_name}`.trim() || undefined)
    : undefined;

  return {
    id: apiVehicle.id,
    truckId: apiVehicle.operational_code || apiVehicle.fleet_number || apiVehicle.id,
    plateNumber: apiVehicle.plate_number,
    truckType: apiVehicle.vehicle_type?.name,
    trailerType: trailer?.trailer_type,
    driver: driverName,
    currentShipment: shipment?.shipment_number,
    currentLocation: trackingState
      ? `${trackingState.latitude.toFixed(4)}, ${trackingState.longitude.toFixed(4)}`
      : undefined,
    status,
    mileage: apiVehicle.current_odometer ?? undefined,
    gpsStatus: trackingState
      ? trackingState.online_status === 'Online'
        ? 'Active'
        : 'Inactive'
      : undefined,
    availability: mapAvailability(status, hasActiveAssignment),
    hasActiveAssignment,
    assignment: assignment
      ? {
          id: assignment.id,
          assignmentCode: assignment.assignment_code,
          isActive: Boolean(assignment.is_active),
          driverName,
          driverPhone: driver?.phone,
          shipmentNumber: shipment?.shipment_number,
          trailerType: trailer?.trailer_type,
          trailerPlate: trailer?.plate_number,
        }
      : undefined,
    onlineStatus: trackingState?.online_status,
    signalQuality: trackingState?.signal_quality,
    speed: trackingState?.speed,
    movementStatus: trackingState?.movement_status,
    specifications: {
      make: apiVehicle.manufacturer,
      model: apiVehicle.model,
      year: apiVehicle.production_year,
      vin: apiVehicle.vin,
      capacityKg: apiVehicle.payload_capacity_kg ?? undefined,
      fuelCapacityL: apiVehicle.fuel_tank_capacity_l ?? undefined,
    },
    assignedDriver: driver
      ? {
          id: driver.id,
          name: driverName || '',
          phone: driver.phone || undefined,
        }
      : undefined,
    insuranceExpiry: apiVehicle.insurance_expiry_date ?? undefined,
    registrationExpiry: apiVehicle.registration_expiry_date ?? undefined,
    companyId: apiVehicle.company_id ?? apiVehicle.company?.id ?? null,
  };
}

export function deriveFleetHealth(vehicle: Vehicle): FleetHealth {
  return {
    status: vehicle.status,
    availability: vehicle.availability,
    hasActiveAssignment: vehicle.hasActiveAssignment,
    onlineStatus: vehicle.onlineStatus,
    signalQuality: vehicle.signalQuality,
    speed: vehicle.speed,
    movementStatus: vehicle.movementStatus,
    odometer: vehicle.mileage,
  };
}

export class FleetApiError extends Error {
  status?: number;

  constructor(message: string, status?: number) {
    super(message);
    this.name = 'FleetApiError';
    this.status = status;
  }
}

function toFleetApiError(error: unknown, fallback: string): FleetApiError {
  if (error && typeof error === 'object' && 'response' in error) {
    const axiosError = error as any;
    const data = axiosError.response?.data as {
      message?: string;
    } | undefined;

    return new FleetApiError(
      data?.message || fallback,
      axiosError.response?.status
    );
  }

  return new FleetApiError(error instanceof Error ? error.message : fallback);
}

export async function getVehicles(): Promise<Vehicle[]> {
  try {
    const response = await axiosClientWithInterceptors.get<ApiEnvelope<{ data: VehicleApiResponse[] }>>('/vehicles', {
      params: {
        per_page: 100,
        is_active: true,
      },
    });
    const vehiclesData = response.data.data.data || [];
    return vehiclesData.map(mapApiVehicleToVehicle);
  } catch (error) {
    throw toFleetApiError(error, 'Failed to fetch vehicles');
  }
}

export async function getVehicleById(id: string): Promise<Vehicle | undefined> {
  try {
    const response = await axiosClientWithInterceptors.get<ApiEnvelope<VehicleApiResponse>>(`/vehicles/${id}`);
    return mapApiVehicleToVehicle(response.data.data);
  } catch (error) {
    throw toFleetApiError(error, 'Failed to fetch vehicle');
  }
}

export async function getFleetHealth(id: string): Promise<FleetHealth> {
  try {
    const vehicle = await getVehicleById(id);
    if (!vehicle) {
      throw new FleetApiError('Vehicle not found');
    }
    return deriveFleetHealth(vehicle);
  } catch (error) {
    throw toFleetApiError(error, 'Failed to fetch fleet health');
  }
}

export async function getVehicleMaintenance(_id: string): Promise<Maintenance[]> {
  return [];
}

export async function getVehicleTimeline(_id: string): Promise<VehicleTimelineEvent[]> {
  return [];
}

export async function getFleetKPIs(): Promise<{
  total_fleet: number;
  available: number;
  assigned: number;
  maintenance: number;
  offline: number;
  utilization: number;
  available_percentage: number;
  assigned_percentage: number;
  maintenance_percentage: number;
  offline_percentage: number;
}> {
  try {
    const response = await axiosClientWithInterceptors.get<ApiEnvelope<{
      total_fleet: number;
      available: number;
      assigned: number;
      maintenance: number;
      offline: number;
      utilization: number;
      available_percentage: number;
      assigned_percentage: number;
      maintenance_percentage: number;
      offline_percentage: number;
    }>>('/vehicles/fleet-kpis');
    return response.data.data;
  } catch (error) {
    throw toFleetApiError(error, 'Failed to fetch fleet KPIs');
  }
}
