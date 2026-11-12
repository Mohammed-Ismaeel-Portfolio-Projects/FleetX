import React, { useState } from 'react';
import {
  Play,
  Pause,
  Square,
  Zap,
  OctagonAlert,
  GitFork,
  RotateCcw,
  Activity,
  User,
  Truck,
  Container,
  AlertTriangle,
  Loader2,
  ChevronDown,
  ChevronUp,
  X,
  Gauge,
  Navigation,
  Clock,
  History,
  CheckCircle2,
  XCircle,
} from 'lucide-react';
import { useTrackingSession } from '../hooks/useTrackingSession';
import type { TrackingSessionStatus } from '../types/trackingSession.types';
import type { LiveVehicle } from '../types/tracking.types';

interface ActiveShipmentTrackingPanelProps {
  vehicle: LiveVehicle;
  onClose?: () => void;
  className?: string;
}

function getStatusBadgeStyle(status: TrackingSessionStatus | 'UNINITIALIZED') {
  switch (status) {
    case 'ACTIVE':
      return 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40 animate-pulse';
    case 'PAUSED':
      return 'bg-amber-500/20 text-amber-300 border-amber-500/40';
    case 'COMPLETED':
      return 'bg-cyan-500/20 text-cyan-300 border-cyan-500/40';
    case 'TERMINATED':
      return 'bg-rose-500/20 text-rose-300 border-rose-500/40';
    case 'INACTIVE':
    default:
      return 'bg-slate-500/20 text-slate-300 border-slate-500/40';
  }
}

function formatEventBadge(eventType: string) {
  switch (eventType) {
    case 'STARTED':
      return 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30';
    case 'PAUSED':
      return 'bg-amber-500/20 text-amber-300 border-amber-500/30';
    case 'RESUMED':
      return 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30';
    case 'STOPPED':
      return 'bg-rose-500/20 text-rose-300 border-rose-500/30';
    case 'SPEED_OVERRIDE':
      return 'bg-amber-500/20 text-amber-300 border-amber-500/30';
    case 'SUDDEN_STOP_TRIGGERED':
      return 'bg-rose-500/20 text-rose-300 border-rose-500/30';
    case 'ROUTE_DEVIATION_TRIGGERED':
      return 'bg-purple-500/20 text-purple-300 border-purple-500/30';
    case 'OVERRIDE_RESET':
      return 'bg-slate-500/20 text-slate-300 border-slate-500/30';
    default:
      return 'bg-slate-500/20 text-slate-300 border-slate-500/30';
  }
}

export default function ActiveShipmentTrackingPanel({
  vehicle,
  onClose,
  className = '',
}: ActiveShipmentTrackingPanelProps) {
  const [isMinimized, setIsMinimized] = useState<boolean>(false);
  const [showEvents, setShowEvents] = useState<boolean>(false);

  // Priority resolution: shipmentOrderId -> shipmentId (NO vehicle.id fallback)
  const queryId = (vehicle.shipmentOrderId || vehicle.shipmentId) ?? undefined;

  const {
    session,
    events,
    isLoading,
    isActionLoading,
    error,
    startSession,
    pauseSession,
    resumeSession,
    stopSession,
    triggerSpeedViolation,
    triggerSuddenStop,
    triggerRouteDeviation,
    resetOverrides,
  } = useTrackingSession(queryId);

  const currentStatus: TrackingSessionStatus | 'INACTIVE' = session?.status ?? 'INACTIVE';

  const hasActiveOverride =
    session?.simulation.forced_speed_kmh != null ||
    session?.simulation.forced_deceleration ||
    session?.simulation.forced_route_deviation;

  const isTerminalState = currentStatus === 'COMPLETED' || currentStatus === 'TERMINATED';

  return (
    <div
      className={`w-80 sm:w-96 rounded-2xl border border-cyan-500/30 bg-slate-900/95 p-4 text-slate-100 shadow-2xl backdrop-blur-md z-[1000] transition-all select-none ${className}`}
    >
      {/* Header */}
      <div className="flex items-center justify-between border-b border-white/10 pb-3">
        <div className="flex items-center gap-2.5">
          <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
            <Activity className="h-4 w-4" />
          </div>
          <div>
            <h3 className="text-sm font-bold text-slate-100 tracking-wide flex items-center gap-1.5">
              <span>Shipment Tracking Engine</span>
            </h3>
            <p className="text-xs text-slate-400 font-mono">
              Shipment: <span className="text-cyan-300 font-semibold">{vehicle.shipmentNumber || 'N/A'}</span>
            </p>
          </div>
        </div>

        <div className="flex items-center gap-1.5">
          <span
            className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider ${getStatusBadgeStyle(
              currentStatus
            )}`}
          >
            {currentStatus}
          </span>
          <button
            type="button"
            onClick={() => setIsMinimized((prev) => !prev)}
            className="rounded-lg p-1 text-slate-400 hover:bg-white/10 hover:text-slate-200 transition"
            title={isMinimized ? 'Expand Control Panel' : 'Minimize Control Panel'}
          >
            {isMinimized ? <ChevronDown className="h-4 w-4" /> : <ChevronUp className="h-4 w-4" />}
          </button>
          {onClose && (
            <button
              type="button"
              onClick={onClose}
              className="rounded-lg p-1 text-slate-400 hover:bg-rose-500/20 hover:text-rose-300 transition"
              title="Close Panel"
            >
              <X className="h-4 w-4" />
            </button>
          )}
        </div>
      </div>

      {!isMinimized && (
        <div className="mt-3 space-y-3.5">
          {/* Missing shipment identifier error guard */}
          {!queryId && (
            <div className="flex items-center gap-2 rounded-xl border border-amber-500/30 bg-amber-500/10 p-2.5 text-xs text-amber-300">
              <AlertTriangle className="h-4 w-4 shrink-0 text-amber-400" />
              <span>No active shipment order bound to this vehicle.</span>
            </div>
          )}

          {/* API Error notification */}
          {error && queryId && (
            <div className="flex items-center gap-2 rounded-xl border border-rose-500/30 bg-rose-500/10 p-2.5 text-xs text-rose-300">
              <AlertTriangle className="h-4 w-4 shrink-0 text-rose-400" />
              <span className="flex-1">{error}</span>
            </div>
          )}

          {/* Live Telemetry Real-time Bar */}
          <div className="grid grid-cols-3 gap-2 rounded-xl border border-cyan-500/20 bg-slate-950/80 p-2 text-xs">
            <div className="flex items-center gap-1.5">
              <Gauge className="h-3.5 w-3.5 text-emerald-400 shrink-0" />
              <div>
                <p className="text-[10px] text-slate-400 font-medium">Speed</p>
                <p className="font-mono font-bold text-emerald-300">{Math.round(vehicle.speed ?? 0)} km/h</p>
              </div>
            </div>

            <div className="flex items-center gap-1.5">
              <Navigation className="h-3.5 w-3.5 text-cyan-400 shrink-0" />
              <div className="truncate">
                <p className="text-[10px] text-slate-400 font-medium">Coordinates</p>
                <p className="font-mono text-[10px] font-semibold text-cyan-200 truncate">
                  {vehicle.latitude?.toFixed(4) ?? '0.0000'}, {vehicle.longitude?.toFixed(4) ?? '0.0000'}
                </p>
              </div>
            </div>

            <div className="flex items-center gap-1.5">
              <Clock className="h-3.5 w-3.5 text-amber-400 shrink-0" />
              <div className="truncate">
                <p className="text-[10px] text-slate-400 font-medium">Updated</p>
                <p className="font-mono text-[10px] font-semibold text-slate-300 truncate">
                  {vehicle.lastUpdateAt ? new Date(vehicle.lastUpdateAt).toLocaleTimeString() : 'Just now'}
                </p>
              </div>
            </div>
          </div>

          {/* Bound Operational Resource Snapshot */}
          <div className="grid grid-cols-3 gap-2 rounded-xl border border-white/5 bg-slate-950/60 p-2 text-xs">
            <div className="flex items-center gap-1.5 overflow-hidden">
              <User className="h-3.5 w-3.5 text-slate-400 shrink-0" />
              <div className="truncate">
                <p className="text-[10px] text-slate-400 font-medium">Driver</p>
                <p className="truncate font-semibold text-slate-200">
                  {session?.driver?.name || vehicle.driverName || 'Unassigned'}
                </p>
              </div>
            </div>

            <div className="flex items-center gap-1.5 overflow-hidden">
              <Truck className="h-3.5 w-3.5 text-slate-400 shrink-0" />
              <div className="truncate">
                <p className="text-[10px] text-slate-400 font-medium">Truck</p>
                <p className="truncate font-semibold text-cyan-300 font-mono">
                  {session?.vehicle?.plate_number || vehicle.plateNumber || 'FLEETX-TRK'}
                </p>
              </div>
            </div>

            <div className="flex items-center gap-1.5 overflow-hidden">
              <Container className="h-3.5 w-3.5 text-slate-400 shrink-0" />
              <div className="truncate">
                <p className="text-[10px] text-slate-400 font-medium">Trailer</p>
                <p className="truncate font-semibold text-slate-200 font-mono">
                  {session?.trailer?.code || 'None'}
                </p>
              </div>
            </div>
          </div>

          {/* Lifecycle Controls State Machine */}
          <div>
            <p className="mb-1.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
              Engine Control
            </p>

            {currentStatus === 'COMPLETED' ? (
              <div className="flex items-center gap-2 rounded-xl border border-cyan-500/30 bg-cyan-500/10 p-2.5 text-xs text-cyan-300 font-semibold">
                <CheckCircle2 className="h-4 w-4 text-cyan-400 shrink-0" />
                <span>Destination Reached — Session Completed</span>
              </div>
            ) : currentStatus === 'TERMINATED' ? (
              <div className="flex items-center gap-2 rounded-xl border border-rose-500/30 bg-rose-500/10 p-2.5 text-xs text-rose-300 font-semibold">
                <XCircle className="h-4 w-4 text-rose-400 shrink-0" />
                <span>Session Stopped / Terminated</span>
              </div>
            ) : (
              <div className="flex flex-wrap items-center gap-2">
                {currentStatus === 'INACTIVE' ? (
                  <button
                    type="button"
                    onClick={() => void startSession()}
                    disabled={!queryId || isLoading || isActionLoading}
                    className="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl border border-emerald-500/40 bg-emerald-500/20 px-3 py-2 text-xs font-bold text-emerald-200 hover:bg-emerald-500/30 active:scale-95 disabled:opacity-50 transition"
                  >
                    {isActionLoading ? (
                      <Loader2 className="h-3.5 w-3.5 animate-spin" />
                    ) : (
                      <Play className="h-3.5 w-3.5 text-emerald-400 fill-emerald-400" />
                    )}
                    Start Engine
                  </button>
                ) : null}

                {currentStatus === 'ACTIVE' ? (
                  <button
                    type="button"
                    onClick={() => void pauseSession()}
                    disabled={isActionLoading}
                    className="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl border border-amber-500/40 bg-amber-500/20 px-3 py-2 text-xs font-bold text-amber-200 hover:bg-amber-500/30 active:scale-95 disabled:opacity-50 transition"
                  >
                    {isActionLoading ? (
                      <Loader2 className="h-3.5 w-3.5 animate-spin" />
                    ) : (
                      <Pause className="h-3.5 w-3.5 text-amber-400 fill-amber-400" />
                    )}
                    Pause Engine
                  </button>
                ) : null}

                {currentStatus === 'PAUSED' ? (
                  <button
                    type="button"
                    onClick={() => void resumeSession()}
                    disabled={isActionLoading}
                    className="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl border border-emerald-500/40 bg-emerald-500/20 px-3 py-2 text-xs font-bold text-emerald-200 hover:bg-emerald-500/30 active:scale-95 disabled:opacity-50 transition"
                  >
                    {isActionLoading ? (
                      <Loader2 className="h-3.5 w-3.5 animate-spin" />
                    ) : (
                      <Play className="h-3.5 w-3.5 text-emerald-400 fill-emerald-400" />
                    )}
                    Resume Engine
                  </button>
                ) : null}

                {currentStatus === 'ACTIVE' || currentStatus === 'PAUSED' ? (
                  <button
                    type="button"
                    onClick={() => void stopSession()}
                    disabled={isActionLoading}
                    className="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl border border-rose-500/40 bg-rose-500/20 px-3 py-2 text-xs font-bold text-rose-200 hover:bg-rose-500/30 active:scale-95 disabled:opacity-50 transition"
                  >
                    {isActionLoading ? (
                      <Loader2 className="h-3.5 w-3.5 animate-spin" />
                    ) : (
                      <Square className="h-3.5 w-3.5 text-rose-400 fill-rose-400" />
                    )}
                    Stop Engine
                  </button>
                ) : null}
              </div>
            )}
          </div>

          {/* Test Simulation Overrides */}
          {!isTerminalState && (
            <div className="border-t border-white/10 pt-2.5">
              <div className="flex items-center justify-between mb-2">
                <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                  Test Overrides
                </p>
                {hasActiveOverride && (
                  <span className="rounded-md bg-amber-500/20 px-1.5 py-0.5 text-[10px] font-bold text-amber-300 border border-amber-500/30">
                    Override Active
                  </span>
                )}
              </div>

              <div className="grid grid-cols-2 gap-2">
                <button
                  type="button"
                  onClick={() => void triggerSpeedViolation()}
                  disabled={!session || isActionLoading || isTerminalState}
                  className={`inline-flex items-center justify-center gap-1.5 rounded-xl border px-2.5 py-2 text-xs font-semibold active:scale-95 disabled:opacity-40 transition ${
                    session?.simulation.forced_speed_kmh != null
                      ? 'border-amber-400 bg-amber-500/30 text-amber-200'
                      : 'border-white/10 bg-white/5 text-slate-300 hover:bg-white/10'
                  }`}
                >
                  <Zap className="h-3.5 w-3.5 text-amber-400" />
                  Speed Violation
                </button>

                <button
                  type="button"
                  onClick={() => void triggerSuddenStop()}
                  disabled={!session || isActionLoading || isTerminalState}
                  className={`inline-flex items-center justify-center gap-1.5 rounded-xl border px-2.5 py-2 text-xs font-semibold active:scale-95 disabled:opacity-40 transition ${
                    session?.simulation.forced_deceleration
                      ? 'border-rose-400 bg-rose-500/30 text-rose-200'
                      : 'border-white/10 bg-white/5 text-slate-300 hover:bg-white/10'
                  }`}
                >
                  <OctagonAlert className="h-3.5 w-3.5 text-rose-400" />
                  Sudden Stop
                </button>

                <button
                  type="button"
                  onClick={() => void triggerRouteDeviation()}
                  disabled={!session || isActionLoading || isTerminalState}
                  className={`inline-flex items-center justify-center gap-1.5 rounded-xl border px-2.5 py-2 text-xs font-semibold active:scale-95 disabled:opacity-40 transition ${
                    session?.simulation.forced_route_deviation
                      ? 'border-purple-400 bg-purple-500/30 text-purple-200'
                      : 'border-white/10 bg-white/5 text-slate-300 hover:bg-white/10'
                  }`}
                >
                  <GitFork className="h-3.5 w-3.5 text-purple-400" />
                  Route Deviation
                </button>

                <button
                  type="button"
                  onClick={() => void resetOverrides()}
                  disabled={!session || isActionLoading || isTerminalState}
                  className="inline-flex items-center justify-center gap-1.5 rounded-xl border border-white/10 bg-white/5 px-2.5 py-2 text-xs font-semibold text-slate-400 hover:bg-white/10 hover:text-slate-200 active:scale-95 disabled:opacity-40 transition"
                >
                  <RotateCcw className="h-3.5 w-3.5 text-slate-400" />
                  Reset Overrides
                </button>
              </div>
            </div>
          )}

          {/* Audit Event History Collapsible */}
          {session?.id && (
            <div className="border-t border-white/10 pt-2.5">
              <button
                type="button"
                onClick={() => setShowEvents((prev) => !prev)}
                className="w-full flex items-center justify-between text-[11px] font-semibold text-slate-400 hover:text-slate-200 transition"
              >
                <span className="flex items-center gap-1.5">
                  <History className="h-3.5 w-3.5 text-cyan-400" />
                  Session Audit Events ({events.length})
                </span>
                {showEvents ? <ChevronUp className="h-3.5 w-3.5" /> : <ChevronDown className="h-3.5 w-3.5" />}
              </button>

              {showEvents && (
                <div className="mt-2 max-h-40 overflow-y-auto space-y-1.5 pr-1">
                  {events.length === 0 ? (
                    <p className="text-[10px] text-slate-500 italic p-1">No events logged yet.</p>
                  ) : (
                    events.map((ev) => (
                      <div
                        key={ev.id}
                        className="flex items-center justify-between rounded-lg border border-white/5 bg-slate-950/60 p-2 text-[10px]"
                      >
                        <span
                          className={`rounded-md border px-1.5 py-0.5 font-bold uppercase tracking-wider ${formatEventBadge(
                            ev.event_type
                          )}`}
                        >
                          {ev.event_type}
                        </span>
                        <span className="text-slate-400 font-mono">
                          {new Date(ev.created_at).toLocaleTimeString()}
                        </span>
                      </div>
                    ))
                  )}
                </div>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  );
}
