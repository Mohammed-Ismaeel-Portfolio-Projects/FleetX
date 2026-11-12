import { useEffect, useRef } from 'react';
import { Search, Users } from 'lucide-react';
import type { Driver } from '../../drivers/types/driver-operations.types';
import { cn } from '../../../../lib/utils';
import { dispatcherDesignTokens } from '../../../../shared/constants/dispatcherDesignSystem';
import { StatusBadge } from '../../../../shared/components/dispatcher/StatusBadge';

interface ContactsPanelProps {
  drivers: Driver[];
  total: number | null;
  search: string;
  onSearch: (value: string) => void;
  availabilityFilter: string;
  onAvailabilityFilter: (value: string) => void;
  selectedDriverId: string | null;
  onSelectDriver: (id: string) => void;
  sourceError?: string | null;
}

function availabilityTone(
  value: string | null | undefined
): 'success' | 'info' | 'warning' | 'danger' | 'neutral' {
  const v = (value || '').toLowerCase();
  if (v === 'available') return 'success';
  if (v === 'busy') return 'info';
  if (v === 'on leave') return 'warning';
  if (v === 'suspended') return 'danger';
  return 'neutral';
}

export function ContactsPanel({
  drivers,
  total,
  search,
  onSearch,
  availabilityFilter,
  onAvailabilityFilter,
  selectedDriverId,
  onSelectDriver,
  sourceError = null,
}: ContactsPanelProps) {
  const selectedRef = useRef<HTMLButtonElement | null>(null);

  useEffect(() => {
    if (!selectedDriverId || !selectedRef.current) return;
    selectedRef.current.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }, [selectedDriverId]);

  return (
    <div className={cn(dispatcherDesignTokens.surface, 'flex flex-col p-4 md:p-5')}>
      <div className="flex items-start justify-between gap-3">
        <div>
          <div className="flex items-center gap-2">
            <Users className="h-4 w-4 text-cyan-300" aria-hidden />
            <h2 className="text-lg font-semibold text-white">Driver Contacts</h2>
          </div>
          <p className="mt-1 text-sm text-slate-500">
            Select a contact for Call / Email and ops context. Messaging requires Open Conversation
            (or selecting an existing thread).
            {total != null ? (
              <>
                {' '}
                · <span className="tabular-nums text-slate-400">{total}</span> active
              </>
            ) : null}
          </p>
        </div>
      </div>

      {sourceError ? (
        <div className="mt-3 space-y-2 rounded-2xl border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-sm text-amber-100">
          <p>Unavailable — {sourceError}</p>
          <p className="text-amber-100/80">
            Instead: open Driver Operations or use Attention Queue deep links when Issues include a
            driver.
          </p>
        </div>
      ) : null}

      <div className="mt-4 flex flex-col gap-2 sm:flex-row">
        <label className="relative min-w-0 flex-1">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
          <input
            value={search}
            onChange={(e) => onSearch(e.target.value)}
            placeholder="Search name, employee #, phone, shipment…"
            className={cn(dispatcherDesignTokens.input, 'w-full pl-9')}
          />
        </label>
        <select
          value={availabilityFilter}
          onChange={(e) => onAvailabilityFilter(e.target.value)}
          className={cn(dispatcherDesignTokens.input, 'sm:w-44')}
          aria-label="Filter by availability"
        >
          <option value="">All availability</option>
          <option value="Available">Available</option>
          <option value="Busy">Busy</option>
          <option value="Offline">Offline</option>
          <option value="On Leave">On Leave</option>
          <option value="Suspended">Suspended</option>
        </select>
      </div>

      <div className="mt-4 max-h-[420px] space-y-2 overflow-y-auto overscroll-contain pr-1">
        {!sourceError && drivers.length === 0 ? (
          <div className="rounded-2xl border border-dashed border-white/10 px-4 py-8 text-center text-sm text-slate-500">
            <p>No drivers match the current filters.</p>
            <p className="mt-2 text-slate-600">
              Clear search/availability, or open Driver Operations to browse the full roster.
            </p>
          </div>
        ) : null}

        {drivers.map((driver) => {
          const selected = driver.id === selectedDriverId;
          const availability = driver.dispatchAvailability || driver.status || 'Unavailable';
          return (
            <button
              key={driver.id}
              ref={selected ? selectedRef : undefined}
              type="button"
              onClick={() => onSelectDriver(driver.id)}
              aria-pressed={selected}
              className={cn(
                'w-full rounded-2xl border px-3 py-3 text-left transition',
                selected
                  ? 'border-cyan-400/50 bg-cyan-500/15 ring-2 ring-cyan-400/30'
                  : 'border-white/10 bg-white/[0.03] hover:border-white/20 hover:bg-white/[0.05]'
              )}
            >
              <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                  <div className="flex flex-wrap items-center gap-2">
                    <p className="truncate font-semibold text-white">{driver.name}</p>
                    {selected ? (
                      <span className="rounded-full border border-cyan-400/30 bg-cyan-500/15 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-cyan-100">
                        Selected
                      </span>
                    ) : null}
                  </div>
                  <p className="mt-0.5 truncate text-xs text-slate-500">
                    {driver.employeeNumber}
                    {driver.phone ? ` · ${driver.phone}` : ''}
                  </p>
                </div>
                <StatusBadge label={availability} tone={availabilityTone(availability)} />
              </div>
              {driver.assignment?.shipmentNumber || driver.assignment?.vehiclePlate ? (
                <p className="mt-2 truncate text-xs text-slate-400">
                  {driver.assignment.shipmentNumber
                    ? `Shipment ${driver.assignment.shipmentNumber}`
                    : null}
                  {driver.assignment.shipmentNumber && driver.assignment.vehiclePlate ? ' · ' : null}
                  {driver.assignment.vehiclePlate
                    ? `Vehicle ${driver.assignment.vehiclePlate}`
                    : null}
                </p>
              ) : (
                <p className="mt-2 text-xs text-slate-600">No active assignment</p>
              )}
              <p className="mt-1.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-600">
                {driver.userId != null
                  ? driver.assignment?.shipmentId
                    ? 'Account linked · can open conversation'
                    : 'Account linked · no shipment'
                  : 'Account not linked'}
              </p>
            </button>
          );
        })}
      </div>
    </div>
  );
}
