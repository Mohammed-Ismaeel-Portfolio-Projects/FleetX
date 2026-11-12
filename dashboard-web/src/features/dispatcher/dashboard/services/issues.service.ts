import { axiosClientWithInterceptors, cleanParams } from '../../../../core/api';

interface ApiEnvelope<T> {
  success: boolean;
  message: string;
  data: T;
}

export interface Issue {
  id: number;
  shipment_id: string | null;
  driver_id: number | null;
  type: string;
  description: string;
  status_id: number;
  created_at: string;
  updated_at: string;
  shipment?: {
    id: string;
    shipment_number: string;
  };
  driver?: {
    id: number;
    full_name: string;
  };
  status?: {
    id: number;
    name: string;
  };
}

export interface IssueQueryParams {
  type?: string;
  status_id?: number;
  shipment_id?: string;
  driver_id?: number;
  sort_by?: string;
  sort_direction?: string;
  per_page?: number;
  page?: number;
}

/** Alerts Center mutation payload — status_id only (narrow write surface). */
export interface IssueStatusUpdatePayload {
  status_id: number;
}

/** Real Issues GET pagination fields from Laravel LengthAwarePaginator JSON. */
export interface IssuePaginationMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
  from: number | null;
  to: number | null;
}

export class IssueApiError extends Error {
  status?: number;

  constructor(message: string, status?: number) {
    super(message);
    this.name = 'IssueApiError';
    this.status = status;
  }
}

function toIssueApiError(error: unknown, fallback: string): IssueApiError {
  if (error && typeof error === 'object' && 'response' in error) {
    const axiosError = error as any;
    const data = axiosError.response?.data as {
      message?: string;
    } | undefined;

    return new IssueApiError(
      data?.message || fallback,
      axiosError.response?.status
    );
  }

  return new IssueApiError(error instanceof Error ? error.message : fallback);
}

/**
 * Normalize Issues list payload.
 * QueryIssuesAction returns a Laravel LengthAwarePaginator (flat fields + `data` rows),
 * not Pagination::format({ data, meta }). Also accepts a nested `{ data, meta }` shape.
 */
export function normalizeIssuesListPayload(payload: unknown): {
  data: Issue[];
  meta: IssuePaginationMeta;
} {
  const emptyMeta: IssuePaginationMeta = {
    current_page: 1,
    per_page: 15,
    total: 0,
    last_page: 1,
    from: null,
    to: null,
  };

  if (!payload || typeof payload !== 'object') {
    return { data: [], meta: emptyMeta };
  }

  const record = payload as Record<string, unknown>;

  // Nested { data, meta } (Pagination::format style)
  if (
    Array.isArray(record.data) &&
    record.meta &&
    typeof record.meta === 'object' &&
    !Array.isArray(record.meta)
  ) {
    const meta = record.meta as Record<string, unknown>;
    const rows = record.data as Issue[];
    return {
      data: rows,
      meta: {
        current_page: Number(meta.current_page) || 1,
        per_page: Number(meta.per_page) || 15,
        total: Number(meta.total) || 0,
        last_page: Number(meta.last_page) || 1,
        from: meta.from == null ? null : Number(meta.from),
        to: meta.to == null ? null : Number(meta.to),
      },
    };
  }

  // Laravel LengthAwarePaginator JSON
  if (Array.isArray(record.data)) {
    const rows = record.data as Issue[];
    return {
      data: rows,
      meta: {
        current_page: Number(record.current_page) || 1,
        per_page: Number(record.per_page) || 15,
        total: Number(record.total) || 0,
        last_page: Number(record.last_page) || 1,
        from: record.from == null ? null : Number(record.from),
        to: record.to == null ? null : Number(record.to),
      },
    };
  }

  if (Array.isArray(payload)) {
    const rows = payload as Issue[];
    return {
      data: rows,
      meta: {
        current_page: 1,
        per_page: rows.length || 15,
        total: rows.length,
        last_page: 1,
        from: rows.length ? 1 : null,
        to: rows.length || null,
      },
    };
  }

  return { data: [], meta: emptyMeta };
}

export const issuesService = {
  async getIssues(params?: IssueQueryParams): Promise<{ data: Issue[]; meta: IssuePaginationMeta }> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<unknown>>('/issues', {
        params: params ? cleanParams(params) : undefined,
      });
      return normalizeIssuesListPayload(response.data.data);
    } catch (error) {
      throw toIssueApiError(error, 'Failed to fetch issues');
    }
  },

  /** Existing GET /issues/{issue} — used for Alerts deep-link selection when not on the loaded page. */
  async getIssueById(issueId: number | string): Promise<Issue> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<Issue>>(
        `/issues/${issueId}`
      );
      return response.data.data;
    } catch (error) {
      throw toIssueApiError(error, 'Failed to fetch issue');
    }
  },

  /**
   * Persist Issue status via existing PUT /issues/{issue}.
   * Alerts Center must send only `status_id` — no invented lifecycle fields.
   */
  async updateIssue(issueId: number | string, payload: IssueStatusUpdatePayload): Promise<Issue> {
    try {
      const response = await axiosClientWithInterceptors.put<ApiEnvelope<Issue>>(
        `/issues/${issueId}`,
        { status_id: payload.status_id }
      );
      return response.data.data;
    } catch (error) {
      throw toIssueApiError(error, 'Failed to update issue');
    }
  },
};
