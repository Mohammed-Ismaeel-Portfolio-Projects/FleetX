import { Link } from 'react-router-dom';
import { AlertTriangle, ArrowRight, Bell, ExternalLink, MessageSquare, UserRound } from 'lucide-react';
import type { Issue } from '../../dashboard/services/issues.service';
import type { Conversation } from '../types/messaging.types';
import { cn } from '../../../../lib/utils';
import { dispatcherDesignTokens } from '../../../../shared/constants/dispatcherDesignSystem';
import { StatusBadge } from '../../../../shared/components/dispatcher/StatusBadge';
import { resolveIssueAttentionTargets } from '../utils/attentionNavigation';

interface AttentionQueueProps {
  issues: Issue[];
  sourceError?: string | null;
  /** Driver IDs currently loaded in Communication contacts (for in-page focus). */
  knownDriverIds?: ReadonlySet<string>;
  /** Select matching driver contact without leaving Communication Center. */
  onFocusDriver?: (driverId: string) => void;
  /** Existing M2 conversations keyed by shipment id — never invent threads from Issues. */
  conversationsByShipment?: ReadonlyMap<string, Conversation>;
  onOpenConversation?: (shipmentId: string) => void;
}

function issueTone(type: string, status?: string): 'danger' | 'warning' | 'info' | 'neutral' {
  const t = type.toLowerCase();
  if (t === 'critical' || t === 'emergency') return 'danger';
  if (status === 'Open') return 'warning';
  if (status === 'In Progress') return 'info';
  return 'neutral';
}

function formatWhen(iso: string): string {
  const ms = new Date(iso).getTime();
  if (!Number.isFinite(ms)) return 'Unavailable';
  return new Date(ms).toLocaleString([], {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function NavUnavailable({ label }: { label: string }) {
  return (
    <span
      className="inline-flex items-center gap-1 rounded-lg border border-white/5 bg-white/[0.02] px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-600"
      title={`${label} navigation unavailable — no related record ID on this Issue`}
    >
      {label}: Unavailable
    </span>
  );
}

function NavLink({ to, label, emphasize }: { to: string; label: string; emphasize?: boolean }) {
  return (
    <Link
      to={to}
      className={cn(
        'inline-flex items-center gap-1 rounded-lg border px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] transition',
        emphasize
          ? 'border-rose-400/30 bg-rose-500/10 text-rose-100 hover:border-rose-400/50 hover:bg-rose-500/15'
          : 'border-cyan-400/25 bg-cyan-500/10 text-cyan-100 hover:border-cyan-400/40 hover:bg-cyan-500/15'
      )}
    >
      <ExternalLink className="h-3 w-3" aria-hidden />
      {label}
    </Link>
  );
}

/** Operational Issues requiring dispatcher attention — not chat conversations. */
export function AttentionQueue({
  issues,
  sourceError = null,
  knownDriverIds,
  onFocusDriver,
  conversationsByShipment,
  onOpenConversation,
}: AttentionQueueProps) {
  return (
    <div className={cn(dispatcherDesignTokens.surface, 'p-4 md:p-5')}>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <div className="flex items-center gap-2">
            <Bell className="h-4 w-4 text-amber-300" aria-hidden />
            <h2 className="text-lg font-semibold text-white">Needs Attention</h2>
          </div>
          <p className="mt-1 text-sm text-slate-500">
            Operational Issues (not chat). Focus contact only selects the driver — it does not open
            messaging. Open Conversation appears only when an M2 thread already exists for the
            shipment.
          </p>
        </div>
        <Link
          to="/dispatcher/notifications"
          className="inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.14em] text-cyan-300/90 hover:text-cyan-200"
        >
          Alerts Center <ArrowRight className="h-3.5 w-3.5" />
        </Link>
      </div>

      {sourceError ? (
        <p className="mt-3 rounded-2xl border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-sm text-amber-100">
          Unavailable — {sourceError}
        </p>
      ) : null}

      <div className="mt-4 max-h-[420px] space-y-2 overflow-y-auto overscroll-contain pr-1">
        {!sourceError && issues.length === 0 ? (
          <div className="rounded-2xl border border-dashed border-white/10 px-4 py-8 text-center text-sm text-slate-500">
            <p>No recent Issues from the Issues endpoint.</p>
            <p className="mt-2 text-slate-600">
              Open Alerts Center to browse the full queue, or select a driver contact for messaging
              and Call / Email.
            </p>
            <Link
              to="/dispatcher/notifications"
              className={cn(
                dispatcherDesignTokens.buttonSecondary,
                'mt-4 inline-flex px-3 py-2 text-xs'
              )}
            >
              Open Alerts Center
            </Link>
          </div>
        ) : null}

        {issues.map((issue) => {
          const statusName = issue.status?.name || 'Unknown';
          const tone = issueTone(issue.type, statusName);
          const targets = resolveIssueAttentionTargets(issue);
          const relatedDriverId = targets.driverId;
          const canFocusDriver =
            relatedDriverId != null &&
            Boolean(onFocusDriver) &&
            Boolean(knownDriverIds?.has(relatedDriverId));
          const existingConversation =
            targets.shipmentId && conversationsByShipment
              ? conversationsByShipment.get(targets.shipmentId)
              : undefined;

          return (
            <article
              key={issue.id}
              className="rounded-2xl border border-white/10 bg-white/[0.03] px-3 py-3"
            >
              <div className="flex items-start justify-between gap-2">
                <div className="flex min-w-0 items-start gap-2">
                  <AlertTriangle
                    className={cn(
                      'mt-0.5 h-4 w-4 shrink-0',
                      tone === 'danger' ? 'text-rose-300' : 'text-amber-300'
                    )}
                    aria-hidden
                  />
                  <div className="min-w-0">
                    <p className="truncate text-sm font-semibold text-slate-100">
                      {issue.type} · #{issue.id}
                    </p>
                    <p className="mt-1 line-clamp-2 text-xs leading-relaxed text-slate-400">
                      {issue.description?.trim() || 'No description'}
                    </p>
                    <p className="mt-2 text-[11px] text-slate-500">
                      <span className="tabular-nums">
                        {formatWhen(issue.updated_at || issue.created_at)}
                      </span>
                      {issue.driver?.full_name ? ` · ${issue.driver.full_name}` : null}
                      {issue.shipment?.shipment_number
                        ? ` · Shipment ${issue.shipment.shipment_number}`
                        : null}
                    </p>
                  </div>
                </div>
                <StatusBadge label={statusName} tone={tone === 'danger' ? 'danger' : tone} />
              </div>

              <div className="mt-3 flex flex-wrap items-center gap-1.5 border-t border-white/5 pt-3">
                <NavLink
                  to={targets.alertsHref}
                  label={targets.prefersAlerts ? 'Alerts (primary)' : 'Alerts Center'}
                  emphasize={targets.prefersAlerts}
                />

                {targets.shipmentHref ? (
                  <NavLink to={targets.shipmentHref} label="Shipment record" />
                ) : (
                  <NavUnavailable label="Shipment" />
                )}

                {targets.driverSurfaceHref ? (
                  <NavLink to={targets.driverSurfaceHref} label="Driver Operations" />
                ) : (
                  <NavUnavailable label="Driver" />
                )}

                {canFocusDriver && relatedDriverId ? (
                  <button
                    type="button"
                    onClick={() => onFocusDriver?.(relatedDriverId)}
                    className="inline-flex items-center gap-1 rounded-lg border border-emerald-400/25 bg-emerald-500/10 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-emerald-100 transition hover:border-emerald-400/40 hover:bg-emerald-500/15"
                  >
                    <UserRound className="h-3 w-3" aria-hidden />
                    Focus contact
                  </button>
                ) : relatedDriverId ? (
                  <span
                    className="inline-flex items-center gap-1 rounded-lg border border-white/5 bg-white/[0.02] px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-600"
                    title="Driver ID is on the Issue but not in the currently loaded Communication contacts list"
                  >
                    Focus contact: Unavailable
                  </span>
                ) : null}

                {existingConversation && targets.shipmentId && onOpenConversation ? (
                  <button
                    type="button"
                    onClick={() => onOpenConversation(targets.shipmentId!)}
                    className="inline-flex items-center gap-1 rounded-lg border border-cyan-400/25 bg-cyan-500/10 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-cyan-100 transition hover:border-cyan-400/40 hover:bg-cyan-500/15"
                  >
                    <MessageSquare className="h-3 w-3" aria-hidden />
                    Open Conversation
                  </button>
                ) : null}
              </div>
            </article>
          );
        })}
      </div>
    </div>
  );
}
