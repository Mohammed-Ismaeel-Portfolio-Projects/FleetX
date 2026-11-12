import { axiosClientWithInterceptors } from '../../../../core/api';

interface ApiEnvelope<T> {
  success: boolean;
  message: string;
  data: T;
}

export interface DashboardKPI {
  active_shipments: number;
  pending_assignments: number;
  available_drivers: number;
  vehicles_in_transit: number;
  delayed_shipments: number | null;
  critical_alerts: number;
}

export interface ActivityItem {
  id: number;
  type: string;
  message: string;
  description: string;
  timestamp: string;
  icon: string;
}

/** GET /dashboard/performance — backend-computed delivery/dispatch metrics. */
export interface DashboardPerformance {
  average_dispatch_time_minutes: number | null;
  eta_accuracy_percentage: number | null;
  completed_today: number | null;
  on_time_delivery_percentage: number | null;
  overall_efficiency: number | null;
  customer_satisfaction: number | null;
  cost_savings: number | null;
}

export class DashboardApiError extends Error {
  status?: number;

  constructor(message: string, status?: number) {
    super(message);
    this.name = 'DashboardApiError';
    this.status = status;
  }
}

function toDashboardApiError(error: unknown, fallback: string): DashboardApiError {
  if (error && typeof error === 'object' && 'response' in error) {
    const axiosError = error as any;
    const data = axiosError.response?.data as {
      message?: string;
    } | undefined;

    return new DashboardApiError(
      data?.message || fallback,
      axiosError.response?.status
    );
  }

  return new DashboardApiError(error instanceof Error ? error.message : fallback);
}

export const dashboardService = {
  async getKPI(): Promise<DashboardKPI> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<DashboardKPI>>('/dashboard/kpi');
      return response.data.data;
    } catch (error) {
      throw toDashboardApiError(error, 'Failed to fetch dashboard KPIs');
    }
  },

  async getActivityTimeline(): Promise<ActivityItem[]> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<ActivityItem[]>>('/dashboard/activity');
      return response.data.data;
    } catch (error) {
      throw toDashboardApiError(error, 'Failed to fetch activity timeline');
    }
  },

  async getPerformance(): Promise<DashboardPerformance> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<DashboardPerformance>>(
        '/dashboard/performance'
      );
      return response.data.data;
    } catch (error) {
      throw toDashboardApiError(error, 'Failed to fetch dashboard performance metrics');
    }
  },
};
