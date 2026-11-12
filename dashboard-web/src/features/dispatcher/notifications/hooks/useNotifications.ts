import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import type {
  Incident,
  IncidentMetrics,
  IssuePaginationMeta,
  OfficialIssueStatus,
} from '../types/notifications.types';
import { buildIssueQueryParams, notificationsService } from '../services/notifications.service';
import { IssueApiError, issuesService } from '../../dashboard/services/issues.service';
import { mapIssueToIncident } from '../utils/issueAdapter';
import { ALERTS_ISSUE_QUERY_KEY, parseIssueIdQueryParam } from '../utils/issueDeepLink';

const OFFICIAL_STATUSES: OfficialIssueStatus[] = ['Open', 'In Progress', 'Resolved'];
const DEFAULT_PER_PAGE = 15;
const POLL_INTERVAL_MS = 15_000;

function mergeKnownTypes(existing: string[], incidents: Incident[]): string[] {
  const next = new Set(existing);
  for (const incident of incidents) {
    if (incident.type) next.add(incident.type);
  }
  return Array.from(next).sort();
}

function mergeStatusCatalog(
  existing: Record<string, number>,
  incidents: Incident[]
): Record<string, number> {
  const next = { ...existing };
  for (const incident of incidents) {
    if (
      incident.status &&
      incident.statusId != null &&
      (OFFICIAL_STATUSES as string[]).includes(incident.status)
    ) {
      next[incident.status] = incident.statusId;
    }
  }
  return next;
}

function mergeStatusFromIssueRow(
  existing: Record<string, number>,
  status?: { id: number; name: string } | null
): Record<string, number> {
  if (!status?.name || status.id == null) return existing;
  if (!(OFFICIAL_STATUSES as string[]).includes(status.name)) return existing;
  return { ...existing, [status.name]: status.id };
}

const EMPTY_META: IssuePaginationMeta = {
  current_page: 1,
  per_page: DEFAULT_PER_PAGE,
  total: 0,
  last_page: 1,
  from: null,
  to: null,
};

export function useNotifications() {
  const [searchParams] = useSearchParams();
  const urlIssueId = useMemo(
    () => parseIssueIdQueryParam(searchParams.get(ALERTS_ISSUE_QUERY_KEY)),
    [searchParams]
  );
  const urlIssueIdRef = useRef<string | null>(urlIssueId);
  urlIssueIdRef.current = urlIssueId;

  const [incidents, setIncidents] = useState<Incident[]>([]);
  const [pagination, setPagination] = useState<IssuePaginationMeta>(EMPTY_META);
  const [page, setPage] = useState(1);
  const [selectedIncidentId, setSelectedIncidentId] = useState<string | null>(null);
  /** Issue loaded via GET /issues/{id} when deep-linked and not on the current list page. */
  const [deepLinkIncident, setDeepLinkIncident] = useState<Incident | null>(null);
  const [deepLinkPending, setDeepLinkPending] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [viewState, setViewState] = useState<'loading' | 'ready' | 'empty' | 'error'>('loading');
  const [isResolving, setIsResolving] = useState(false);
  const [resolveError, setResolveError] = useState<string | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);

  /** Client-side search over the current loaded server page only. */
  const [searchQuery, setSearchQuery] = useState('');

  /** Server-backed Issues GET filters (no sentinel query values). */
  const [typeFilter, setTypeFilter] = useState('');
  const [statusIdFilter, setStatusIdFilter] = useState<number | null>(null);

  const [knownTypes, setKnownTypes] = useState<string[]>([]);
  const [statusCatalog, setStatusCatalog] = useState<Record<string, number>>({});

  const requestIdRef = useRef(0);
  const inFlightRef = useRef(false);
  const hasLoadedRef = useRef(false);
  const pageRef = useRef(page);
  pageRef.current = page;
  const deepLinkRequestRef = useRef(0);
  /** Avoid re-fetching GET /issues/{id} on every list poll after a resolved or failed deep link. */
  const deepLinkResolvedIdRef = useRef<string | null>(null);
  const deepLinkFailedIdRef = useRef<string | null>(null);

  const loadData = useCallback(
    async (options?: { page?: number; silent?: boolean }) => {
      if (inFlightRef.current) return;
      inFlightRef.current = true;

      const requestPage = options?.page ?? pageRef.current;
      const requestId = ++requestIdRef.current;
      const silent = Boolean(options?.silent) && hasLoadedRef.current;

      if (!silent) {
        setIsLoading(true);
        setViewState('loading');
      }

      const applyResult = (data: Incident[], meta: IssuePaginationMeta) => {
        setIncidents(data);
        setPagination(meta);
        setPage(meta.current_page);
        setKnownTypes((current) => mergeKnownTypes(current, data));
        setStatusCatalog((current) => mergeStatusCatalog(current, data));
        hasLoadedRef.current = true;

        const deepId = urlIssueIdRef.current;

        if (data.length === 0) {
          setViewState('empty');
          // Keep deep-link target alive while show fetch may still resolve it.
          if (!deepId) {
            setSelectedIncidentId(null);
          } else {
            setSelectedIncidentId(deepId);
          }
        } else {
          setViewState('ready');
          setSelectedIncidentId((current) => {
            if (deepId && data.some((item) => item.id === deepId)) return deepId;
            if (current && data.some((item) => item.id === current)) return current;
            if (deepId) return deepId;
            return data[0].id;
          });
        }
      };

      const params = buildIssueQueryParams({
        type: typeFilter,
        statusId: statusIdFilter,
        page: requestPage,
        perPage: DEFAULT_PER_PAGE,
      });

      try {
        const { incidents: data, meta } = await notificationsService.getIncidents(params);
        if (requestId !== requestIdRef.current) return;

        setLoadError(null);

        if (meta.last_page >= 1 && requestPage > meta.last_page) {
          setPage(meta.last_page);
          const corrected = await notificationsService.getIncidents(
            buildIssueQueryParams({
              type: typeFilter,
              statusId: statusIdFilter,
              page: meta.last_page,
              perPage: DEFAULT_PER_PAGE,
            })
          );
          if (requestId !== requestIdRef.current) return;
          applyResult(corrected.incidents, corrected.meta);
          return;
        }

        applyResult(data, meta);
      } catch (error) {
        if (requestId !== requestIdRef.current) return;
        console.error('Failed to load incident data:', error);
        const message =
          error instanceof IssueApiError
            ? error.message
            : error instanceof Error
              ? error.message
              : 'Failed to load issues';
        setLoadError(message);
        setViewState('error');
        // Do not apply [] — failures must not become an empty success state.
      } finally {
        if (requestId === requestIdRef.current) {
          inFlightRef.current = false;
          if (!silent) {
            setIsLoading(false);
          }
        } else {
          inFlightRef.current = false;
        }
      }
    },
    [typeFilter, statusIdFilter]
  );

  useEffect(() => {
    setPage(1);
    void loadData({ page: 1 });
  }, [typeFilter, statusIdFilter, loadData]);

  // Visibility-aware 15s polling (same model as Fleet / Shipments / Drivers)
  const triggerPollRefresh = useCallback(() => {
    void loadData({ page: pageRef.current, silent: true });
  }, [loadData]);

  useEffect(() => {
    let intervalId: number | null = null;

    const clearPolling = () => {
      if (intervalId !== null) {
        window.clearInterval(intervalId);
        intervalId = null;
      }
    };

    const startPolling = () => {
      if (intervalId !== null || document.hidden) return;
      intervalId = window.setInterval(() => {
        if (!document.hidden) {
          triggerPollRefresh();
        }
      }, POLL_INTERVAL_MS);
    };

    const onVisibilityChange = () => {
      if (document.hidden) {
        clearPolling();
        return;
      }
      triggerPollRefresh();
      startPolling();
    };

    startPolling();
    document.addEventListener('visibilitychange', onVisibilityChange);

    return () => {
      clearPolling();
      document.removeEventListener('visibilitychange', onVisibilityChange);
    };
  }, [triggerPollRefresh]);

  // Resolve `?issue=` via list membership or existing GET /issues/{id}.
  useEffect(() => {
    if (!urlIssueId) {
      deepLinkResolvedIdRef.current = null;
      deepLinkFailedIdRef.current = null;
      setDeepLinkIncident(null);
      setDeepLinkPending(false);
      return;
    }

    if (incidents.some((incident) => incident.id === urlIssueId)) {
      deepLinkResolvedIdRef.current = urlIssueId;
      deepLinkFailedIdRef.current = null;
      setDeepLinkIncident(null);
      setDeepLinkPending(false);
      setSelectedIncidentId(urlIssueId);
      return;
    }

    if (deepLinkResolvedIdRef.current === urlIssueId || deepLinkFailedIdRef.current === urlIssueId) {
      if (deepLinkResolvedIdRef.current === urlIssueId) {
        setSelectedIncidentId(urlIssueId);
      }
      setDeepLinkPending(false);
      return;
    }

    const requestId = ++deepLinkRequestRef.current;
    setDeepLinkPending(true);

    void issuesService
      .getIssueById(urlIssueId)
      .then((issue) => {
        if (requestId !== deepLinkRequestRef.current) return;
        const incident = mapIssueToIncident(issue);
        deepLinkResolvedIdRef.current = incident.id;
        deepLinkFailedIdRef.current = null;
        setDeepLinkIncident(incident);
        setSelectedIncidentId(incident.id);
        setStatusCatalog((current) => mergeStatusFromIssueRow(current, issue.status ?? null));
        setKnownTypes((current) => mergeKnownTypes(current, [incident]));
      })
      .catch(() => {
        if (requestId !== deepLinkRequestRef.current) return;
        // Nonexistent / unauthorized — do not fabricate a selection.
        deepLinkFailedIdRef.current = urlIssueId;
        deepLinkResolvedIdRef.current = null;
        setDeepLinkIncident(null);
      })
      .finally(() => {
        if (requestId === deepLinkRequestRef.current) {
          setDeepLinkPending(false);
        }
      });
  }, [urlIssueId, incidents]);

  useEffect(() => {
    if (urlIssueId && deepLinkPending) return;

    if (!incidents.length) {
      if (!urlIssueId || !deepLinkIncident) {
        if (!urlIssueId) setSelectedIncidentId(null);
      }
      return;
    }

    if (urlIssueId && incidents.some((incident) => incident.id === urlIssueId)) {
      setSelectedIncidentId(urlIssueId);
      return;
    }

    if (urlIssueId && deepLinkIncident?.id === urlIssueId) {
      setSelectedIncidentId(urlIssueId);
      return;
    }

    // Invalid / missing deep link → normal first-row selection when needed.
    if (
      !selectedIncidentId ||
      (!incidents.some((incident) => incident.id === selectedIncidentId) &&
        deepLinkIncident?.id !== selectedIncidentId)
    ) {
      setSelectedIncidentId(incidents[0].id);
    }
  }, [incidents, selectedIncidentId, urlIssueId, deepLinkIncident, deepLinkPending]);

  const displayedIncidents = useMemo(() => {
    const needle = searchQuery.trim().toLowerCase();
    if (!needle) return incidents;

    return incidents.filter((incident) =>
      [
        incident.type,
        incident.description,
        incident.driverName,
        incident.shipmentNumber,
        incident.shipmentId,
        incident.id,
        incident.status,
      ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()
        .includes(needle)
    );
  }, [incidents, searchQuery]);

  const metrics = useMemo<IncidentMetrics>(() => {
    const pageCounts = notificationsService.getPageStatusCounts(displayedIncidents);
    return {
      queryTotal: pagination.total,
      ...pageCounts,
    };
  }, [displayedIncidents, pagination.total]);

  const selectedIncident =
    displayedIncidents.find((incident) => incident.id === selectedIncidentId) ||
    (deepLinkIncident && deepLinkIncident.id === selectedIncidentId ? deepLinkIncident : null);

  const statusOptions = useMemo(
    () =>
      OFFICIAL_STATUSES.filter((name) => statusCatalog[name] != null).map((name) => ({
        name,
        id: statusCatalog[name],
      })),
    [statusCatalog]
  );

  /** Real Resolved status_id from Issue SSOT relations only — never hardcoded. */
  const resolvedStatusId = statusCatalog.Resolved ?? null;

  const canResolveSelected = Boolean(
    selectedIncident &&
      selectedIncident.status !== 'Resolved' &&
      resolvedStatusId != null &&
      !isResolving
  );

  const handlePageChange = useCallback(
    (nextPage: number) => {
      if (!Number.isFinite(nextPage) || nextPage < 1) return;
      if (nextPage > pagination.last_page) return;
      if (nextPage === page) return;
      setPage(nextPage);
      void loadData({ page: nextPage });
    },
    [loadData, page, pagination.last_page]
  );

  const resolveSelectedIncident = useCallback(async () => {
    if (!selectedIncident || selectedIncident.status === 'Resolved') return;

    const statusId = statusCatalog.Resolved;
    if (statusId == null) {
      setResolveError(
        'Resolved status ID is unavailable from loaded Issues SSOT data. Cannot resolve without a real status_id.'
      );
      return;
    }

    setIsResolving(true);
    setResolveError(null);

    try {
      const updated = await issuesService.updateIssue(selectedIncident.id, {
        status_id: statusId,
      });
      setStatusCatalog((current) => mergeStatusFromIssueRow(current, updated.status ?? null));
      await loadData({ page: pageRef.current });
    } catch (error) {
      const message =
        error instanceof IssueApiError
          ? error.message
          : error instanceof Error
            ? error.message
            : 'Failed to resolve issue';
      setResolveError(message);
    } finally {
      setIsResolving(false);
    }
  }, [selectedIncident, statusCatalog.Resolved, loadData]);

  return {
    incidents: displayedIncidents,
    selectedIncident,
    selectedIncidentId,
    setSelectedIncidentId,
    metrics,
    pagination,
    isLoading,
    viewState,
    refreshIncidents: () => loadData({ page: pageRef.current }),
    searchQuery,
    setSearchQuery,
    typeFilter,
    setTypeFilter,
    statusIdFilter,
    setStatusIdFilter,
    knownTypes,
    statusOptions,
    onPageChange: handlePageChange,
    canResolveSelected,
    resolvedStatusId,
    isResolving,
    resolveError,
    resolveSelectedIncident,
    loadError,
    retryLoad: () => loadData({ page: pageRef.current }),
    resetFilters: () => {
      setSearchQuery('');
      setTypeFilter('');
      setStatusIdFilter(null);
      setResolveError(null);
      setLoadError(null);
    },
  };
}

export type { Incident, IncidentMetrics };
