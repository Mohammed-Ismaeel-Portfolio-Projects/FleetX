import { axiosClientWithInterceptors } from '../../../../core/api';

export interface ApiEnvelope<T> {
  data: T;
  message: string;
}

// Dashboard KPIs — mirrors GET /dashboard/kpi (delayed_shipments may be null by SSOT).
export interface DashboardKPIs {
  active_shipments: number;
  pending_assignments: number;
  available_drivers: number;
  vehicles_in_transit: number;
  delayed_shipments: number | null;
  critical_alerts: number;
}

// Fleet KPIs — GET /vehicles/fleet-kpis (availability SSOT via GetVehicleAvailabilityAction)
export interface FleetKPIs {
  total_fleet: number;
  available: number;
  /** Busy / operationally committed (active assignment). */
  assigned: number;
  maintenance: number;
  /** Out of Service + Inactive within active fleet. */
  offline: number;
  /** Backend utilization_rate rounded to int %. */
  utilization: number;
  available_percentage: number;
  assigned_percentage: number;
  maintenance_percentage: number;
  offline_percentage: number;
}

// Shipment KPIs — GET /shipments/kpis (status buckets; delayed is stubbed 0)
export interface ShipmentKPIs {
  /** Backend field name; means total shipment count (not vehicles). */
  total_fleet: number;
  pending: number;
  assigned: number;
  in_transit: number;
  delivered: number;
  /** Always 0 — no official Delayed status. Do not present as real. */
  delayed: number;
  urgent: number;
  /** Alias of urgent for older clients. */
  critical: number;
  pending_percentage: number;
  assigned_percentage: number;
  in_transit_percentage: number;
  delivered_percentage: number;
  delayed_percentage: number;
}

// Driver KPIs — GET /drivers/kpis (availability SSOT via GetDriverAvailabilityAction)
export interface DriverKPIs {
  total_drivers: number;
  available: number;
  busy: number;
  offline: number;
  on_leave: number;
  suspended: number;
  available_percentage: number;
  busy_percentage: number;
  offline_percentage: number;
  on_leave_percentage: number;
  suspended_percentage: number;
}

export class AnalyticsApiError extends Error {
  statusCode?: number;
  
  constructor(message: string, statusCode?: number) {
    super(message);
    this.name = 'AnalyticsApiError';
    this.statusCode = statusCode;
  }
}

function toAnalyticsApiError(error: any, defaultMessage: string): AnalyticsApiError {
  if (error instanceof AnalyticsApiError) return error;
  
  const message = error?.response?.data?.message || error?.message || defaultMessage;
  const statusCode = error?.response?.status;
  
  return new AnalyticsApiError(message, statusCode);
}

export const analyticsApiService = {
  async getDashboardKPIs(): Promise<DashboardKPIs> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<DashboardKPIs>>('/dashboard/kpi');
      return response.data.data;
    } catch (error) {
      throw toAnalyticsApiError(error, 'Failed to fetch dashboard KPIs');
    }
  },

  async getFleetKPIs(): Promise<FleetKPIs> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<FleetKPIs>>('/vehicles/fleet-kpis');
      return response.data.data;
    } catch (error) {
      throw toAnalyticsApiError(error, 'Failed to fetch fleet KPIs');
    }
  },

  async getShipmentKPIs(): Promise<ShipmentKPIs> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<ShipmentKPIs>>('/shipments/kpis');
      return response.data.data;
    } catch (error) {
      throw toAnalyticsApiError(error, 'Failed to fetch shipment KPIs');
    }
  },

  async getDriverKPIs(): Promise<DriverKPIs> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<DriverKPIs>>('/drivers/kpis');
      return response.data.data;
    } catch (error) {
      throw toAnalyticsApiError(error, 'Failed to fetch driver KPIs');
    }
  },
};
