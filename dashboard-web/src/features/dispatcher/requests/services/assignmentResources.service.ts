/**
 * Services for fetching drivers, vehicles, and trailers for the assignment workspace.
 * Reuses existing GET /drivers, GET /vehicles, GET /trailers endpoints.
 */
import { axiosClientWithInterceptors } from '../../../../core/api';
import type {
  AssignmentDriver,
  AssignmentVehicle,
  AssignmentTrailer,
  AssignmentCreationPayload,
} from '../types/assignment.types';

interface ApiEnvelope<T> {
  data: T;
  message?: string;
  success?: boolean;
}

interface PaginatedApiResponse<T> {
  data: T[];
  meta: { current_page: number; per_page: number; total: number; last_page: number };
}

function toError(error: unknown, fallback: string): Error {
  const err = error as {
    response?: {
      data?: {
        message?: string;
        errors?: Record<string, string[] | string>;
      };
      status?: number;
    };
    message?: string;
  };
  const data = err?.response?.data;
  if (data?.errors && typeof data.errors === 'object') {
    const first = Object.values(data.errors).flat().find((v) => typeof v === 'string' && v.trim());
    if (first) return new Error(first);
  }
  return new Error(data?.message || err?.message || fallback);
}

export const assignmentResourcesService = {
  async fetchDrivers(search?: string): Promise<AssignmentDriver[]> {
    try {
      const params: Record<string, string> = { per_page: '50' };
      if (search && search.trim()) params.search = search.trim();
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<PaginatedApiResponse<any>>>(
        '/drivers',
        { params }
      );
      const items = response.data.data?.data ?? [];
      return items.map((d: any): AssignmentDriver => ({
        id: d.id,
        employeeNumber: d.employee_number || '',
        fullName: d.full_name || `${d.first_name || ''} ${d.last_name || ''}`.trim(),
        email: d.email ?? null,
        userId: d.user_id ?? null,
        phone: d.phone ?? null,
        status: d.status?.name ?? null,
        dispatchAvailability: d.dispatch_availability ?? null,
        hasActiveAssignment: Boolean(d.has_active_assignment),
        activeAssignmentCode: d.active_assignment?.assignment_code ?? null,
        activeVehicleCode: d.active_assignment?.vehicle?.operational_code
          || d.active_assignment?.vehicle?.plate_number
          || null,
        companyId: d.company_id ?? d.company?.id ?? null,
      }));
    } catch (error) {
      throw toError(error, 'Failed to fetch drivers');
    }
  },

  async fetchVehicles(search?: string): Promise<AssignmentVehicle[]> {
    try {
      const params: Record<string, string> = { per_page: '50' };
      if (search && search.trim()) params.search = search.trim();
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<PaginatedApiResponse<any>>>(
        '/vehicles',
        { params }
      );
      const items = response.data.data?.data ?? [];
      return items.map((v: any): AssignmentVehicle => ({
        id: v.id,
        operationalCode: v.operational_code ?? null,
        plateNumber: v.plate_number || '',
        manufacturer: v.manufacturer ?? null,
        model: v.model ?? null,
        status: v.status?.name ?? null,
        isActive: Boolean(v.is_active),
        hasActiveAssignment: v.active_assignment != null,
        payloadCapacityKg: v.payload_capacity_kg ?? null,
        driverId: v.driver_id ?? v.driver?.id ?? null,
        companyId: v.company_id ?? v.company?.id ?? null,
        driver: v.driver
          ? {
            id: v.driver.id,
            fullName: v.driver.full_name || `${v.driver.first_name || ''} ${v.driver.last_name || ''}`.trim(),
            employeeNumber: v.driver.employee_number || '',
            phone: v.driver.phone ?? null,
            userId: v.driver.user_id ?? null,
            status: typeof v.driver.status === 'object' ? v.driver.status?.name : v.driver.status ?? null,
          }
          : null,
      }));
    } catch (error) {
      throw toError(error, 'Failed to fetch vehicles');
    }
  },

  async fetchTrailers(search?: string, requestedTrailerType?: string | null): Promise<AssignmentTrailer[]> {
    try {
      const params: Record<string, string> = { per_page: '100', available_only: '1' };
      if (search && search.trim()) params.search = search.trim();
      if (requestedTrailerType && requestedTrailerType.trim()) {
        params.requested_trailer_type = requestedTrailerType.trim();
      }
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<PaginatedApiResponse<any>>>(
        '/trailers',
        { params }
      );
      const items = response.data.data?.data ?? [];
      return items.map((t: any): AssignmentTrailer => ({
        id: t.id,
        trailerCode: t.trailer_code ?? null,
        plateNumber: t.plate_number || '',
        trailerType: t.trailer_type ?? null,
        manufacturer: t.manufacturer ?? null,
        status: t.status?.name ?? null,
        isActive: Boolean(t.is_active),
        payloadCapacityKg: t.payload_capacity_kg ?? null,
        isMatchingRequestedType: t.is_matching_requested_type ?? null,
        companyId: t.company_id ?? t.company?.id ?? null,
      }));
    } catch (error) {
      throw toError(error, 'Failed to fetch trailers');
    }
  },

  async fetchAssignmentStatusId(): Promise<number> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<any>>(
        '/statuses',
        { params: { type: 'assignment', name: 'Assigned' } }
      );
      const statuses = response.data.data;
      if (Array.isArray(statuses) && statuses.length > 0) return statuses[0].id;
      if (statuses?.id) return statuses.id;
      throw new Error('Assignment status "Assigned" not found');
    } catch (error) {
      throw toError(error, 'Failed to fetch assignment status');
    }
  },

  async createAssignment(payload: AssignmentCreationPayload): Promise<{ id: string; assignmentCode: string }> {
    try {
      const response = await axiosClientWithInterceptors.post<ApiEnvelope<any>>(
        '/assignments',
        payload
      );
      const data = response.data.data;
      return {
        id: data.id,
        assignmentCode: data.assignment_code,
      };
    } catch (error) {
      throw toError(error, 'Failed to create assignment');
    }
  },
};
