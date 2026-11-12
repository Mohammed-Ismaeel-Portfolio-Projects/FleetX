import { useEffect, useState } from 'react';
import { Bell } from 'lucide-react';
import {
  issuesService,
  IssueApiError,
  type Issue,
} from '../../../features/dispatcher/dashboard/services/issues.service';

export default function NotificationsPage() {
  const [issues, setIssues] = useState<Issue[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      setIsLoading(true);
      setError(null);
      try {
        const result = await issuesService.getIssues({
          page: 1,
          per_page: 25,
          sort_by: 'created_at',
          sort_direction: 'desc',
        });
        if (cancelled) return;
        setIssues(Array.isArray(result.data) ? result.data : []);
      } catch (err) {
        if (cancelled) return;
        setIssues([]);
        setError(err instanceof IssueApiError ? err.message : 'Failed to load issues');
      } finally {
        if (!cancelled) {
          setIsLoading(false);
        }
      }
    }

    void load();
    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <div className="flex w-full flex-col gap-4 text-left">
      <div className="flex flex-col gap-1.5">
        <h1 className="text-2xl font-bold tracking-tight text-white font-sans">Issues</h1>
        <p className="text-xs font-normal text-slate-500 font-sans">
          Records from GET /issues. These are operational issues, not a fictional notification center.
        </p>
      </div>

      <div className="rounded-2xl border border-white/5 bg-[#0E1320] p-5">
        {isLoading && <p className="text-sm text-slate-400">Loading issues…</p>}

        {!isLoading && error && (
          <p className="text-sm text-amber-200">Issues unavailable. {error}</p>
        )}

        {!isLoading && !error && issues.length === 0 && (
          <div className="flex flex-col items-center justify-center py-10 text-center">
            <Bell className="mb-3 h-10 w-10 text-slate-600" />
            <p className="text-sm font-semibold text-slate-300">No issues returned</p>
            <p className="mt-1 text-xs text-slate-500">GET /issues did not return any records.</p>
          </div>
        )}

        {!isLoading && !error && issues.length > 0 && (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[720px] border-collapse">
              <thead>
                <tr className="border-b border-white/5">
                  <th className="pb-3 text-left text-[10px] font-bold uppercase tracking-widest text-slate-500">
                    ID
                  </th>
                  <th className="pb-3 text-left text-[10px] font-bold uppercase tracking-widest text-slate-500">
                    Type
                  </th>
                  <th className="pb-3 text-left text-[10px] font-bold uppercase tracking-widest text-slate-500">
                    Description
                  </th>
                  <th className="pb-3 text-left text-[10px] font-bold uppercase tracking-widest text-slate-500">
                    Status
                  </th>
                  <th className="pb-3 text-left text-[10px] font-bold uppercase tracking-widest text-slate-500">
                    Shipment
                  </th>
                  <th className="pb-3 text-left text-[10px] font-bold uppercase tracking-widest text-slate-500">
                    Created
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-white/5">
                {issues.map((issue) => (
                  <tr key={issue.id}>
                    <td className="py-3 text-xs font-mono text-slate-300">{issue.id}</td>
                    <td className="py-3 text-xs text-slate-300">{issue.type || 'Unavailable'}</td>
                    <td className="py-3 text-xs text-slate-300">
                      {issue.description || 'Unavailable'}
                    </td>
                    <td className="py-3 text-xs text-slate-300">
                      {issue.status?.name || 'Unavailable'}
                    </td>
                    <td className="py-3 text-xs font-mono text-slate-300">
                      {issue.shipment?.shipment_number || issue.shipment_id || '—'}
                    </td>
                    <td className="py-3 text-xs font-mono text-slate-400">
                      {issue.created_at || 'Unavailable'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
