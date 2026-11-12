import { AlertTriangle, ArrowRight } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { issuesService, type Issue, IssueApiError } from '../../../features/dispatcher/dashboard/services/issues.service';

interface AttentionItem {
  id: string;
  title: string;
  description: string;
  severity: 'critical' | 'high' | 'medium' | 'low';
  time: string;
  relatedEntity: string;
}

const SEVERITY_ORDER: Record<AttentionItem['severity'], number> = {
  critical: 0,
  high: 1,
  medium: 2,
  low: 3,
};

function mapIssueToAttentionItem(issue: Issue): AttentionItem | null {
  if (issue.status?.name === 'Resolved') {
    return null;
  }

  const severity: AttentionItem['severity'] =
    (issue.type === 'critical' || issue.type === 'emergency') ? 'critical' :
    issue.type === 'high' ? 'high' :
    issue.type === 'medium' ? 'medium' : 'low';

  return {
    id: issue.id.toString(),
    title: issue.type ? issue.type.replace(/_/g, ' ') : 'Issue',
    description: issue.description || 'No description provided.',
    severity,
    time: new Date(issue.created_at).toLocaleString([], {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }),
    relatedEntity: issue.shipment?.shipment_number || issue.driver?.full_name || 'Unlinked',
  };
}

function getSeverityClasses(severity: string) {
  switch (severity) {
    case 'critical':
      return 'border-red-500/25 bg-red-500/5';
    case 'high':
      return 'border-orange-500/25 bg-orange-500/5';
    case 'medium':
      return 'border-amber-500/20 bg-amber-500/5';
    default:
      return 'border-white/10 bg-white/[0.02]';
  }
}

function getSeverityBadge(severity: string) {
  switch (severity) {
    case 'critical':
      return 'bg-red-500/15 text-red-300';
    case 'high':
      return 'bg-orange-500/15 text-orange-300';
    case 'medium':
      return 'bg-amber-500/15 text-amber-300';
    default:
      return 'bg-slate-500/15 text-slate-400';
  }
}

function AttentionRow({ item }: { item: AttentionItem }) {
  return (
    <div className={`flex items-start gap-3 p-3 sm:p-4 rounded-xl border ${getSeverityClasses(item.severity)}`}>
      <AlertTriangle className={`w-4 h-4 mt-0.5 flex-shrink-0 ${
        item.severity === 'critical' ? 'text-red-400' :
        item.severity === 'high' ? 'text-orange-400' : 'text-slate-400'
      }`} />
      <div className="flex-1 min-w-0">
        <div className="flex flex-wrap items-center gap-2 mb-1">
          <span className={`px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider ${getSeverityBadge(item.severity)}`}>
            {item.severity}
          </span>
          <h4 className="text-xs font-semibold text-white capitalize truncate">{item.title}</h4>
          <span className="text-[10px] text-slate-500 font-mono ml-auto whitespace-nowrap">{item.time}</span>
        </div>
        <p className="text-[11px] text-slate-400 leading-relaxed line-clamp-2 mb-1.5">
          {item.description}
        </p>
        <span className="text-[10px] text-slate-500 font-mono">{item.relatedEntity}</span>
      </div>
    </div>
  );
}

export default function AttentionRequiredPanel({ refreshKey = 0 }: { refreshKey?: number }) {
  const [items, setItems] = useState<AttentionItem[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loaded, setLoaded] = useState(false);
  const hasLoadedRef = useRef(false);
  const inFlightRef = useRef(false);

  useEffect(() => {
    let cancelled = false;

    async function fetchIssues() {
      if (inFlightRef.current) return;
      inFlightRef.current = true;

      try {
        setError(null);
        const response = await issuesService.getIssues({ per_page: 10, sort_by: 'created_at', sort_direction: 'desc' });
        if (cancelled) return;
        const mapped = (response.data || [])
          .map(mapIssueToAttentionItem)
          .filter((item): item is AttentionItem => item !== null)
          .sort((a, b) => SEVERITY_ORDER[a.severity] - SEVERITY_ORDER[b.severity]);
        setItems(mapped);
        hasLoadedRef.current = true;
      } catch (err) {
        if (cancelled) return;
        setError(err instanceof IssueApiError ? err.message : 'Unable to load open issues.');
        console.error('Error fetching issues:', err);
      } finally {
        inFlightRef.current = false;
        if (!cancelled) {
          setLoaded(true);
        }
      }
    }

    fetchIssues();

    return () => {
      cancelled = true;
    };
  }, [refreshKey]);

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-xl p-4 sm:p-5">
      <div className="flex flex-wrap items-center justify-between gap-2 mb-3">
        <div>
          <h3 className="text-sm font-bold text-white tracking-tight">Attention Required</h3>
          <p className="text-[10px] text-slate-500 mt-1">
            Open issues that need dispatcher action
          </p>
        </div>
        <Link
          to="/dispatcher/notifications"
          className="flex items-center gap-1.5 text-[10px] font-bold text-slate-400 hover:text-white uppercase tracking-wider transition-colors"
        >
          <span>Open Alerts Center</span>
          <ArrowRight className="w-3.5 h-3.5" />
        </Link>
      </div>

      {error && !hasLoadedRef.current ? (
        <div className="py-6 text-center">
          <p className="text-sm text-red-400 font-medium">Unable to load attention items</p>
          <p className="text-xs text-slate-500 mt-1">{error}</p>
        </div>
      ) : !loaded ? (
        <p className="text-sm text-slate-500 py-6 text-center">Loading open issues…</p>
      ) : items.length === 0 ? (
        <div className="py-8 text-center border border-dashed border-white/10 rounded-xl">
          <p className="text-sm text-slate-300">No dispatcher action required.</p>
        </div>
      ) : (
        <div className="space-y-2">
          {items.map((item) => (
            <AttentionRow key={item.id} item={item} />
          ))}
        </div>
      )}
    </div>
  );
}
