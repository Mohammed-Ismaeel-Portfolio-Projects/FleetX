import { axiosClientWithInterceptors } from '../../../../core/api';

export class AssignmentApiError extends Error {
  statusCode?: number;

  constructor(message: string, statusCode?: number) {
    super(message);
    this.name = 'AssignmentApiError';
    this.statusCode = statusCode;
  }
}

function toAssignmentApiError(error: unknown, defaultMessage: string): AssignmentApiError {
  if (error instanceof AssignmentApiError) return error;
  const err = error as {
    response?: { data?: { message?: string }; status?: number };
    message?: string;
  };
  const message = err?.response?.data?.message || err?.message || defaultMessage;
  const statusCode = err?.response?.status;
  return new AssignmentApiError(message, statusCode);
}

/**
 * Dispatcher Assignment service — Engine enrollment only (C2.9-C2.8 Step 7).
 * Reuses POST /assignments/{id}/engine-enroll. No status / ops-stage mutation.
 */
export const assignmentsService = {
  async enrollInEngine(assignmentId: string): Promise<{ message: string }> {
    try {
      const response = await axiosClientWithInterceptors.post<{
        success?: boolean;
        message?: string;
        data?: unknown;
      }>(`/assignments/${assignmentId}/engine-enroll`);

      return {
        message: response.data.message || 'Assignment enrolled in engine',
      };
    } catch (error) {
      throw toAssignmentApiError(error, 'Failed to enroll assignment in engine');
    }
  },
};
