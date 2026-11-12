import { useEffect, useRef, useState } from 'react';
import { Clock, CheckCircle, AlertCircle } from 'lucide-react';
import WidgetState from './WidgetState';
import { dashboardService, type ActivityItem as ApiActivityItem, DashboardApiError } from '../../../features/dispatcher/dashboard/services/dashboard.service';

interface ActivityItem {
  id: string;
  time: string;
  timestampMs: number;
  message: string;
  type: string;
  sourceType: string;
}

/** Deterministic tie-break when timestamps are equal. */
const SOURCE_ORDER: Record<string, number> = {
  tracking: 0,
  assignment: 1,
  issue: 2,
  audit: 3,
};

function sourceRank(type: string): number {
  return SOURCE_ORDER[type.toLowerCase()] ?? 99;
}

function mapActivityFromApi(activity: ApiActivityItem): ActivityItem {
  const timestampMs = new Date(activity.timestamp).getTime();

  return {
    id: `${activity.type}-${activity.id}-${activity.timestamp}`,
    time: new Date(activity.timestamp).toLocaleString([], {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }),
    timestampMs: Number.isFinite(timestampMs) ? timestampMs : 0,
    message: activity.description || activity.message || 'Operational event',
    type: activity.icon,
    sourceType: activity.type || 'audit',
  };
}

function sortActivities(items: ActivityItem[]): ActivityItem[] {
  return [...items].sort((a, b) => {
    if (b.timestampMs !== a.timestampMs) {
      return b.timestampMs - a.timestampMs;
    }
    const typeDiff = sourceRank(a.sourceType) - sourceRank(b.sourceType);
    if (typeDiff !== 0) return typeDiff;
    return a.id.localeCompare(b.id);
  });
}

function getActivityIcon(type: string) {
  switch (type) {
    case 'success':
      return <CheckCircle className="w-4 h-4 text-emerald-400" />;
    case 'warning':
      return <AlertCircle className="w-4 h-4 text-amber-400" />;
    case 'error':
      return <AlertCircle className="w-4 h-4 text-red-400" />;
    default:
      return <Clock className="w-4 h-4 text-slate-400" />;
  }
}

export default function RecentActivityPanel({ refreshKey = 0 }: { refreshKey?: number }) {
  const [activityData, setActivityData] = useState<ActivityItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const hasLoadedRef = useRef(false);
  const inFlightRef = useRef(false);

  const load = async (silent = false) => {
    if (inFlightRef.current) return;
    inFlightRef.current = true;
    try {
      if (!silent) setIsLoading(true);
      setError(null);
      const activities = await dashboardService.getActivityTimeline();
      setActivityData(sortActivities(activities.map(mapActivityFromApi)).slice(0, 10));
      hasLoadedRef.current = true;
    } catch (err) {
      setError(err instanceof DashboardApiError ? err.message : 'Unable to load recent activity.');
      console.error('Error fetching activity timeline:', err);
    } finally {
      inFlightRef.current = false;
      if (!silent) setIsLoading(false);
    }
  };

  useEffect(() => {
    load(hasLoadedRef.current);
  }, [refreshKey]);

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-xl p-4 sm:p-5">
      <div className="mb-3">
        <h3 className="text-sm font-bold text-white tracking-tight">Recent Activity</h3>
        <p className="text-[10px] text-slate-500 mt-1">
          Latest tracking, audit, and issue events
        </p>
      </div>

      {isLoading ? (
        <WidgetState state="loading" message="Loading recent operational events…" />
      ) : error && !hasLoadedRef.current ? (
        <WidgetState state="error" message={error} onRetry={() => load(false)} />
      ) : activityData.length === 0 ? (
        <WidgetState
          state="empty"
          message="No operational events recorded recently."
        />
      ) : (
        <div className="max-h-[300px] overflow-y-auto custom-scrollbar divide-y divide-white/5">
          {activityData.map((activity) => (
            <div
              key={activity.id}
              className="flex items-start gap-3 px-1 py-2.5"
            >
              <div className="flex-shrink-0 mt-0.5">{getActivityIcon(activity.type)}</div>
              <div className="flex-1 min-w-0">
                <p className="text-xs text-slate-300 leading-relaxed">{activity.message}</p>
                <p className="text-[10px] text-slate-500 mt-1 font-mono">{activity.time}</p>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
