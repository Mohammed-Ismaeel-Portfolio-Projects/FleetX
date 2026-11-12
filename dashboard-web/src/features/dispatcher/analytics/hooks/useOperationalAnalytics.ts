import { useCallback, useEffect, useRef, useState } from 'react';
import {
  operationalAnalyticsService,
  OperationalAnalyticsLoadError,
} from '../services/operationalAnalytics.service';
import {
  dashboardService,
  DashboardApiError,
  type ActivityItem,
} from '../../dashboard/services/dashboard.service';
import type { OperationalAnalyticsViewModel } from '../types/operationalAnalytics.types';

/** Same visibility-aware interval as Fleet / Shipments / Drivers / Alerts. */
const POLL_INTERVAL_MS = 15_000;

export type OperationalActivityState = {
  status: 'loading' | 'ready' | 'empty' | 'error';
  items: ActivityItem[];
  error: string | null;
};

const ACTIVITY_LOADING: OperationalActivityState = {
  status: 'loading',
  items: [],
  error: null,
};

type LoadOptions = {
  /** Background poll / visibility refresh — keep last-known-good UI. */
  silent?: boolean;
};

type PendingLoad = {
  silent: boolean;
};

function kpiErrorMessage(err: unknown): string {
  if (err instanceof OperationalAnalyticsLoadError) return err.message;
  if (err instanceof Error && err.message.trim()) return err.message;
  return 'Unable to load analytics';
}

function activityErrorMessage(err: unknown): string {
  if (err instanceof DashboardApiError) return err.message;
  if (err instanceof Error && err.message.trim()) return err.message;
  return 'Unable to load operational activity';
}

export function useOperationalAnalytics() {
  const [viewModel, setViewModel] = useState<OperationalAnalyticsViewModel | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [activity, setActivity] = useState<OperationalActivityState>(ACTIVITY_LOADING);

  const requestIdRef = useRef(0);
  const inFlightRef = useRef(false);
  const pendingRef = useRef<PendingLoad | null>(null);
  const hasLoadedRef = useRef(false);
  const viewModelRef = useRef<OperationalAnalyticsViewModel | null>(null);
  const activityRef = useRef<OperationalActivityState>(ACTIVITY_LOADING);

  viewModelRef.current = viewModel;
  activityRef.current = activity;

  const loadData = useCallback(async (options?: LoadOptions) => {
    if (inFlightRef.current) {
      const nextSilent = Boolean(options?.silent);
      const pending = pendingRef.current;
      // Coalesce: any non-silent intent wins so manual Refresh is not dropped as silent.
      pendingRef.current = {
        silent: pending ? pending.silent && nextSilent : nextSilent,
      };
      return;
    }

    inFlightRef.current = true;
    const requestId = ++requestIdRef.current;
    const hadViewModel = viewModelRef.current != null;
    const silent = Boolean(options?.silent) && hasLoadedRef.current;

    if (!silent) {
      if (!hadViewModel) {
        setIsLoading(true);
        setActivity(ACTIVITY_LOADING);
      } else {
        setIsRefreshing(true);
      }
    }

    const kpiPromise = operationalAnalyticsService.getViewModel();
    const activityPromise = dashboardService.getActivityTimeline();

    const [kpiResult, activityResult] = await Promise.allSettled([kpiPromise, activityPromise]);

    if (requestId !== requestIdRef.current) {
      inFlightRef.current = false;
      return;
    }

    // --- KPIs ---
    if (kpiResult.status === 'fulfilled') {
      setViewModel(kpiResult.value);
      setError(null);
      hasLoadedRef.current = true;
    } else {
      const message = kpiErrorMessage(kpiResult.reason);
      setError(message);
      // Failed refresh must not wipe last-known-good KPIs.
      if (!viewModelRef.current) {
        setViewModel(null);
      }
    }

    // --- Activity (independent of KPI success; A8) ---
    if (activityResult.status === 'fulfilled') {
      const items = activityResult.value;
      if (!Array.isArray(items) || items.length === 0) {
        setActivity({ status: 'empty', items: [], error: null });
      } else {
        setActivity({ status: 'ready', items, error: null });
      }
    } else {
      const message = activityErrorMessage(activityResult.reason);
      const previous = activityRef.current;
      if (previous.items.length > 0) {
        // Keep last-known-good feed; do not flip to empty/error wipe.
        setActivity({ status: 'ready', items: previous.items, error: null });
      } else {
        setActivity({ status: 'error', items: [], error: message });
      }
    }

    if (requestId === requestIdRef.current) {
      inFlightRef.current = false;
      setIsLoading(false);
      setIsRefreshing(false);

      const pending = pendingRef.current;
      if (pending) {
        pendingRef.current = null;
        void loadData({ silent: pending.silent });
      }
    } else {
      inFlightRef.current = false;
    }
  }, []);

  const refreshData = useCallback(() => {
    void loadData({ silent: false });
  }, [loadData]);

  const retryKpis = useCallback(() => {
    void loadData({ silent: false });
  }, [loadData]);

  const retryActivity = useCallback(() => {
    void loadData({ silent: false });
  }, [loadData]);

  // Initial load
  useEffect(() => {
    void loadData({ silent: false });
  }, [loadData]);

  // Visibility-aware 15s polling (Fleet / Shipments / Drivers / Alerts pattern)
  useEffect(() => {
    let intervalId: number | null = null;

    const clearPolling = () => {
      if (intervalId !== null) {
        window.clearInterval(intervalId);
        intervalId = null;
      }
    };

    const triggerPollRefresh = () => {
      void loadData({ silent: true });
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
      // Invalidate any in-flight apply after unmount.
      requestIdRef.current += 1;
    };
  }, [loadData]);

  return {
    viewModel,
    isLoading,
    isRefreshing,
    error,
    activity,
    refreshData,
    retryActivity,
    retryKpis,
  };
}
