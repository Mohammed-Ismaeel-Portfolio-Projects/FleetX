import type { Incident, IncidentMetrics, IssuePaginationMeta } from '../types/notifications.types';
import { issuesService, type IssueQueryParams } from '../../dashboard/services/issues.service';
import { mapIssuesToIncidents } from '../utils/issueAdapter';

export type IssuesListResult = {
  incidents: Incident[];
  meta: IssuePaginationMeta;
};

/** Build Issues GET params from Alerts query state. Omits cleared/empty filters. */
export function buildIssueQueryParams(input: {
  type?: string | null;
  statusId?: number | null;
  page?: number | null;
  perPage?: number | null;
}): IssueQueryParams {
  const params: IssueQueryParams = {};

  const type = input.type?.trim();
  if (type) {
    params.type = type;
  }

  if (input.statusId != null && Number.isFinite(input.statusId) && input.statusId > 0) {
    params.status_id = input.statusId;
  }

  if (input.page != null && Number.isFinite(input.page) && input.page >= 1) {
    params.page = Math.floor(input.page);
  }

  if (input.perPage != null && Number.isFinite(input.perPage) && input.perPage >= 1) {
    params.per_page = Math.floor(input.perPage);
  }

  return params;
}

export const notificationsService = {
  async getIncidents(params?: IssueQueryParams): Promise<IssuesListResult> {
    const response = await issuesService.getIssues(params);
    return {
      incidents: mapIssuesToIncidents(response.data),
      meta: response.meta,
    };
  },

  /** Status counts from the current loaded page only — not query-wide aggregates. */
  getPageStatusCounts(incidents: Incident[]): Pick<
    IncidentMetrics,
    'pageOpen' | 'pageInProgress' | 'pageResolved' | 'pageCount'
  > {
    return {
      pageCount: incidents.length,
      pageOpen: incidents.filter((incident) => incident.status === 'Open').length,
      pageInProgress: incidents.filter((incident) => incident.status === 'In Progress').length,
      pageResolved: incidents.filter((incident) => incident.status === 'Resolved').length,
    };
  },
};

export type { Incident, IncidentMetrics };
