import { Link } from 'react-router-dom';
import { Bell, Mail, MessageSquare, Navigation, Package, Phone, ShieldAlert, Users } from 'lucide-react';
import type { Driver } from '../../drivers/types/driver-operations.types';
import { cn } from '../../../../lib/utils';
import { dispatcherDesignTokens } from '../../../../shared/constants/dispatcherDesignSystem';
import { mailtoHref, telHref } from '../utils/contactActions';

interface QuickActionsProps {
  selectedDriver: Driver | null;
  onOpenConversation?: () => void;
  messagingAvailable?: boolean;
}

const SURFACE_ACTIONS = [
  { label: 'Driver Operations', href: '/dispatcher/drivers', icon: Users },
  { label: 'Shipments Center', href: '/dispatcher/shipments', icon: Package },
  { label: 'Active Tracking', href: '/dispatcher/tracking', icon: Navigation },
  { label: 'Alerts Center', href: '/dispatcher/notifications', icon: Bell },
] as const;

/** Route jumps + real contact actions for the selected driver. */
export function QuickActions({
  selectedDriver,
  onOpenConversation,
  messagingAvailable = false,
}: QuickActionsProps) {
  const callHref = telHref(selectedDriver?.phone);
  const mailHref = mailtoHref(selectedDriver?.email);
  const emergencyHref = telHref(selectedDriver?.emergencyContactPhone);
  const shipmentHref = selectedDriver?.assignment?.shipmentId
    ? `/dispatcher/shipments/${selectedDriver.assignment.shipmentId}`
    : null;

  return (
    <div className={cn(dispatcherDesignTokens.surface, 'p-4 md:p-5')}>
      <h2 className="text-lg font-semibold text-white">Quick Actions</h2>
      <p className="mt-1 text-sm text-slate-500">
        <span className="text-slate-300">Works now:</span> phone, email, Open Conversation when
        linked, and ops surfaces. <span className="text-slate-500">Not connected:</span> broadcast
        send or realtime chat.
      </p>

      <p className="mt-4 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">
        Contact actions
      </p>
      {!selectedDriver ? (
        <p className="mt-2 rounded-2xl border border-dashed border-white/10 px-3 py-3 text-sm text-slate-500">
          Select a driver contact first. Then Call / Email / Message appear when available.
        </p>
      ) : null}

      <div className="mt-3 grid gap-2 sm:grid-cols-2">
        {callHref ? (
          <a
            href={callHref}
            className={cn(
              dispatcherDesignTokens.buttonPrimary,
              'w-full justify-start gap-3 px-3 py-3 text-left text-sm'
            )}
          >
            <span className="rounded-lg bg-cyan-500/20 p-2 text-cyan-200">
              <Phone className="h-4 w-4" aria-hidden />
            </span>
            <span>Call {selectedDriver?.name ?? 'driver'}</span>
          </a>
        ) : (
          <button
            type="button"
            disabled
            className={cn(
              dispatcherDesignTokens.buttonSecondary,
              'w-full justify-start gap-3 px-3 py-3 text-left text-sm opacity-50'
            )}
            title={
              selectedDriver
                ? 'Driver phone unavailable on Drivers SSOT'
                : 'Select a driver contact first'
            }
          >
            <span className="rounded-lg bg-white/5 p-2 text-slate-500">
              <Phone className="h-4 w-4" aria-hidden />
            </span>
            <span>Call: Unavailable</span>
          </button>
        )}

        {mailHref ? (
          <a
            href={mailHref}
            className={cn(
              dispatcherDesignTokens.buttonSecondary,
              'w-full justify-start gap-3 px-3 py-3 text-left text-sm'
            )}
          >
            <span className="rounded-lg bg-cyan-500/10 p-2 text-cyan-300">
              <Mail className="h-4 w-4" aria-hidden />
            </span>
            <span>Email {selectedDriver?.name ?? 'driver'}</span>
          </a>
        ) : (
          <button
            type="button"
            disabled
            className={cn(
              dispatcherDesignTokens.buttonSecondary,
              'w-full justify-start gap-3 px-3 py-3 text-left text-sm opacity-50'
            )}
            title={
              selectedDriver
                ? 'Driver email unavailable on Drivers SSOT'
                : 'Select a driver contact first'
            }
          >
            <span className="rounded-lg bg-white/5 p-2 text-slate-500">
              <Mail className="h-4 w-4" aria-hidden />
            </span>
            <span>Email: Unavailable</span>
          </button>
        )}

        {messagingAvailable && onOpenConversation ? (
          <button
            type="button"
            onClick={onOpenConversation}
            className={cn(
              dispatcherDesignTokens.buttonSecondary,
              'w-full justify-start gap-3 px-3 py-3 text-left text-sm sm:col-span-2'
            )}
          >
            <span className="rounded-lg bg-emerald-500/15 p-2 text-emerald-200">
              <MessageSquare className="h-4 w-4" aria-hidden />
            </span>
            <span>Message / Open conversation</span>
          </button>
        ) : selectedDriver ? (
          <p className="sm:col-span-2 rounded-xl border border-white/5 bg-white/[0.02] px-3 py-2 text-xs text-slate-600">
            {selectedDriver.userId == null
              ? 'Messaging unavailable — driver account not linked. Use Call / Email.'
              : !selectedDriver.assignment?.shipmentId
                ? 'Messaging unavailable — no active shipment/assignment.'
                : 'Messaging unavailable.'}
          </p>
        ) : null}

        {emergencyHref ? (
          <a
            href={emergencyHref}
            className={cn(
              dispatcherDesignTokens.buttonSecondary,
              'w-full justify-start gap-3 border-rose-400/25 px-3 py-3 text-left text-sm text-rose-100 sm:col-span-2'
            )}
          >
            <span className="rounded-lg bg-rose-500/15 p-2 text-rose-200">
              <ShieldAlert className="h-4 w-4" aria-hidden />
            </span>
            <span>
              Call emergency
              {selectedDriver?.emergencyContactName
                ? ` (${selectedDriver.emergencyContactName})`
                : ''}
            </span>
          </a>
        ) : selectedDriver ? (
          <p className="sm:col-span-2 rounded-xl border border-white/5 bg-white/[0.02] px-3 py-2 text-xs text-slate-600">
            Emergency contact phone: Unavailable on this driver record.
          </p>
        ) : null}

        {shipmentHref ? (
          <Link
            to={shipmentHref}
            className={cn(
              dispatcherDesignTokens.buttonSecondary,
              'w-full justify-start gap-3 px-3 py-3 text-left text-sm sm:col-span-2'
            )}
          >
            <span className="rounded-lg bg-cyan-500/10 p-2 text-cyan-300">
              <Package className="h-4 w-4" aria-hidden />
            </span>
            <span>
              Open shipment {selectedDriver?.assignment?.shipmentNumber ?? 'record'}
            </span>
          </Link>
        ) : selectedDriver ? (
          <p className="sm:col-span-2 rounded-xl border border-white/5 bg-white/[0.02] px-3 py-2 text-xs text-slate-600">
            Linked shipment: Unavailable — open Shipments Center or Active Tracking instead.
          </p>
        ) : null}
      </div>

      <p className="mt-5 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">
        Operational surfaces
      </p>
      <div className="mt-3 grid gap-2 sm:grid-cols-2">
        {SURFACE_ACTIONS.map((action) => {
          const Icon = action.icon;
          return (
            <Link
              key={action.href}
              to={action.href}
              className={cn(
                dispatcherDesignTokens.buttonSecondary,
                'w-full justify-start gap-3 px-3 py-3 text-left text-sm text-slate-200'
              )}
            >
              <span className="rounded-lg bg-cyan-500/10 p-2 text-cyan-300">
                <Icon className="h-4 w-4" aria-hidden />
              </span>
              <span>{action.label}</span>
            </Link>
          );
        })}
      </div>
    </div>
  );
}
