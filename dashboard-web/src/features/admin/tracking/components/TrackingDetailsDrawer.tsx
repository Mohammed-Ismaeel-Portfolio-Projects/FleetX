import { X, MapPin, Gauge, Navigation, Signal, Clock, Truck, User, Package, FileText } from 'lucide-react';
import type { TrackingState } from '../types/tracking.types';
import AdminCompactDriverPerformance from '../../shared/components/AdminCompactDriverPerformance';

interface TrackingDetailsDrawerProps {
  trackingState: TrackingState | null;
  isOpen: boolean;
  isLoading: boolean;
  onClose: () => void;
}

function getMovementStatusColor(status: string): string {
  switch (status) {
    case 'Moving':
      return 'bg-green-500/10 text-green-300 border-green-500/20';
    case 'Stopped':
      return 'bg-yellow-500/10 text-yellow-300 border-yellow-500/20';
    case 'Idle':
      return 'bg-slate-500/10 text-slate-300 border-slate-500/20';
    default:
      return 'bg-slate-500/10 text-slate-300 border-slate-500/20';
  }
}

function getOnlineStatusColor(status: string): string {
  switch (status) {
    case 'Online':
      return 'bg-green-500/10 text-green-300 border-green-500/20';
    case 'Offline':
      return 'bg-red-500/10 text-red-300 border-red-500/20';
    default:
      return 'bg-slate-500/10 text-slate-300 border-slate-500/20';
  }
}

function getSignalQualityColor(quality: string): string {
  switch (quality) {
    case 'Good':
      return 'bg-green-500/10 text-green-300 border-green-500/20';
    case 'Weak':
      return 'bg-yellow-500/10 text-yellow-300 border-yellow-500/20';
    case 'Lost':
      return 'bg-red-500/10 text-red-300 border-red-500/20';
    default:
      return 'bg-slate-500/10 text-slate-300 border-slate-500/20';
  }
}

export default function TrackingDetailsDrawer({
  trackingState,
  isOpen,
  isLoading,
  onClose,
}: TrackingDetailsDrawerProps) {
  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex justify-end">
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-black/50 backdrop-blur-sm"
        onClick={onClose}
      />

      {/* Drawer */}
      <div className="relative z-10 h-full w-full max-w-lg overflow-y-auto bg-[#0E1320] shadow-2xl">
        <div className="flex flex-col">
          {/* Header */}
          <div className="flex items-center justify-between border-b border-white/5 px-6 py-4">
            <h2 className="text-lg font-semibold text-white">Tracking Details</h2>
            <button
              type="button"
              onClick={onClose}
              className="rounded-lg p-2 text-slate-400 transition-colors hover:bg-white/5 hover:text-white"
            >
              <X className="h-5 w-5" />
            </button>
          </div>

          {/* Content */}
          <div className="flex-1 p-6">
            {isLoading ? (
              <div className="space-y-4">
                {Array.from({ length: 6 }).map((_, index) => (
                  <div key={index} className="h-20 animate-pulse rounded-xl bg-white/5" />
                ))}
              </div>
            ) : trackingState ? (
              <div className="space-y-6">
                {/* Vehicle Info */}
                <div className="rounded-xl border border-white/5 bg-[#0A0E17] p-4">
                  <div className="flex items-center gap-3">
                    <div className="rounded-lg bg-cyan-500/10 p-2">
                      <Truck className="h-5 w-5 text-cyan-400" />
                    </div>
                    <div className="flex-1">
                      <p className="text-xs font-bold uppercase tracking-widest text-slate-500">Vehicle</p>
                      <p className="mt-1 font-mono text-sm font-semibold text-white">
                        {trackingState.vehicle?.fleet_number || 'N/A'}
                      </p>
                      <p className="text-xs text-slate-400">
                        {trackingState.vehicle?.plate_number || 'N/A'}
                      </p>
                    </div>
                  </div>
                </div>

                {/* Driver Info */}
                <div className="rounded-xl border border-white/5 bg-[#0A0E17] p-4">
                  <div className="flex items-center gap-3">
                    <div className="rounded-lg bg-cyan-500/10 p-2">
                      <User className="h-5 w-5 text-cyan-400" />
                    </div>
                    <div className="flex-1">
                      <p className="text-xs font-bold uppercase tracking-widest text-slate-500">Driver</p>
                      <p className="mt-1 text-sm font-semibold text-white">
                        {trackingState.driver?.full_name || 'N/A'}
                      </p>
                      <p className="text-xs text-slate-400">
                        {trackingState.driver?.employee_number || 'N/A'}
                      </p>
                    </div>
                  </div>
                </div>

                <AdminCompactDriverPerformance
                  driverId={trackingState.driver_id}
                  assignmentId={trackingState.assignment_id}
                  includeCurrentAssignment
                />

                {/* Shipment Info */}
                <div className="rounded-xl border border-white/5 bg-[#0A0E17] p-4">
                  <div className="flex items-center gap-3">
                    <div className="rounded-lg bg-cyan-500/10 p-2">
                      <Package className="h-5 w-5 text-cyan-400" />
                    </div>
                    <div className="flex-1">
                      <p className="text-xs font-bold uppercase tracking-widest text-slate-500">Shipment</p>
                      <p className="mt-1 font-mono text-sm font-semibold text-white">
                        {trackingState.shipment?.shipment_number || 'N/A'}
                      </p>
                      <p className="text-xs text-slate-400">
                        {trackingState.shipment?.reference_number || 'N/A'}
                      </p>
                    </div>
                  </div>
                </div>

                {/* Assignment Info */}
                <div className="rounded-xl border border-white/5 bg-[#0A0E17] p-4">
                  <div className="flex items-center gap-3">
                    <div className="rounded-lg bg-cyan-500/10 p-2">
                      <FileText className="h-5 w-5 text-cyan-400" />
                    </div>
                    <div className="flex-1">
                      <p className="text-xs font-bold uppercase tracking-widest text-slate-500">Assignment</p>
                      <p className="mt-1 font-mono text-sm font-semibold text-white">
                        {trackingState.assignment?.assignment_code || 'N/A'}
                      </p>
                      <p className="text-xs text-slate-400">
                        {trackingState.assignment?.is_active ? 'Active' : 'Inactive'}
                      </p>
                    </div>
                  </div>
                </div>

                {/* Location */}
                <div className="rounded-xl border border-white/5 bg-[#0A0E17] p-4">
                  <div className="flex items-center gap-3">
                    <div className="rounded-lg bg-cyan-500/10 p-2">
                      <MapPin className="h-5 w-5 text-cyan-400" />
                    </div>
                    <div className="flex-1">
                      <p className="text-xs font-bold uppercase tracking-widest text-slate-500">Location</p>
                      <p className="mt-1 font-mono text-sm text-white">
                        {trackingState.latitude.toFixed(6)}, {trackingState.longitude.toFixed(6)}
                      </p>
                    </div>
                  </div>
                </div>

                {/* Telemetry */}
                <div className="grid grid-cols-2 gap-4">
                  <div className="rounded-xl border border-white/5 bg-[#0A0E17] p-4">
                    <div className="flex items-center gap-2">
                      <Gauge className="h-4 w-4 text-cyan-400" />
                      <p className="text-xs font-bold uppercase tracking-widest text-slate-500">Speed</p>
                    </div>
                    <p className="mt-2 font-mono text-lg font-bold text-white">
                      {trackingState.speed.toFixed(1)} km/h
                    </p>
                  </div>

                  <div className="rounded-xl border border-white/5 bg-[#0A0E17] p-4">
                    <div className="flex items-center gap-2">
                      <Navigation className="h-4 w-4 text-cyan-400" />
                      <p className="text-xs font-bold uppercase tracking-widest text-slate-500">Heading</p>
                    </div>
                    <p className="mt-2 font-mono text-lg font-bold text-white">
                      {trackingState.heading ? `${trackingState.heading.toFixed(1)}°` : 'N/A'}
                    </p>
                  </div>

                  <div className="rounded-xl border border-white/5 bg-[#0A0E17] p-4">
                    <div className="flex items-center gap-2">
                      <Signal className="h-4 w-4 text-cyan-400" />
                      <p className="text-xs font-bold uppercase tracking-widest text-slate-500">Accuracy</p>
                    </div>
                    <p className="mt-2 font-mono text-lg font-bold text-white">
                      {trackingState.accuracy ? `${trackingState.accuracy.toFixed(1)}m` : 'N/A'}
                    </p>
                  </div>

                  <div className="rounded-xl border border-white/5 bg-[#0A0E17] p-4">
                    <div className="flex items-center gap-2">
                      <Clock className="h-4 w-4 text-cyan-400" />
                      <p className="text-xs font-bold uppercase tracking-widest text-slate-500">Last Update</p>
                    </div>
                    <p className="mt-2 text-sm font-bold text-white">
                      {trackingState.last_update_at ? new Date(trackingState.last_update_at).toLocaleString() : 'N/A'}
                    </p>
                  </div>
                </div>

                {/* Status Badges */}
                <div className="flex flex-wrap gap-3">
                  <div className={`rounded-full border px-3 py-1.5 text-xs font-medium capitalize ${getMovementStatusColor(trackingState.movement_status)}`}>
                    {trackingState.movement_status}
                  </div>
                  <div className={`rounded-full border px-3 py-1.5 text-xs font-medium capitalize ${getOnlineStatusColor(trackingState.online_status)}`}>
                    {trackingState.online_status}
                  </div>
                  <div className={`rounded-full border px-3 py-1.5 text-xs font-medium capitalize ${getSignalQualityColor(trackingState.signal_quality)}`}>
                    {trackingState.signal_quality}
                  </div>
                </div>
              </div>
            ) : (
              <p className="text-center text-slate-500">No tracking state selected</p>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
