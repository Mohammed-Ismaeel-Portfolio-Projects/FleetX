import { useEffect, useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { User, X, AlertCircle } from 'lucide-react';
import type { Vehicle } from '../types/vehicle.types';
import { vehiclesService } from '../services/vehicles.service';
import { assignmentResourcesService } from '../../../dispatcher/requests/services/assignmentResources.service';
import type { AssignmentDriver } from '../../../dispatcher/requests/types/assignment.types';

interface PairDriverModalProps {
  vehicle: Vehicle | null;
  isOpen: boolean;
  onClose: () => void;
  onSuccess: (updatedVehicle: Vehicle) => void;
}

export default function PairDriverModal({
  vehicle,
  isOpen,
  onClose,
  onSuccess,
}: PairDriverModalProps) {
  const [drivers, setDrivers] = useState<AssignmentDriver[]>([]);
  const [selectedDriverId, setSelectedDriverId] = useState<number | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [loading, setLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (isOpen && vehicle) {
      setSelectedDriverId(vehicle.driver_id ?? null);
      setSearchQuery('');
      setError(null);
      void loadDrivers();
    }
  }, [isOpen, vehicle]);

  const loadDrivers = async (query?: string) => {
    setLoading(true);
    setError(null);
    try {
      const data = await assignmentResourcesService.fetchDrivers(query);
      setDrivers(data);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load drivers');
    } finally {
      setLoading(false);
    }
  };

  const handlePair = async () => {
    if (!vehicle || !selectedDriverId || submitting) return;
    setSubmitting(true);
    setError(null);

    try {
      const updated = await vehiclesService.pairDriver(vehicle.id, selectedDriverId);
      onSuccess(updated);
      onClose();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to pair driver');
    } finally {
      setSubmitting(false);
    }
  };

  const handleUnpair = async () => {
    if (!vehicle || submitting) return;
    setSubmitting(true);
    setError(null);

    try {
      const updated = await vehiclesService.unpairDriver(vehicle.id);
      onSuccess(updated);
      onClose();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to unpair driver');
    } finally {
      setSubmitting(false);
    }
  };

  if (!isOpen || !vehicle) return null;

  return (
    <AnimatePresence>
      <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          className="absolute inset-0 bg-black/60 backdrop-blur-sm"
          onClick={onClose}
        />

        <motion.div
          initial={{ scale: 0.95, opacity: 0 }}
          animate={{ scale: 1, opacity: 1 }}
          exit={{ scale: 0.95, opacity: 0 }}
          className="relative w-full max-w-md rounded-2xl border border-white/10 bg-[#0E1320] p-6 shadow-2xl z-10"
        >
          <div className="flex items-center justify-between border-b border-white/10 pb-4">
            <div className="flex items-center gap-2.5">
              <User className="h-5 w-5 text-cyan-400" />
              <h3 className="text-base font-bold text-white">
                Pair Driver to Vehicle ({vehicle.operational_code})
              </h3>
            </div>
            <button
              type="button"
              onClick={onClose}
              className="p-1 text-slate-400 hover:text-white"
            >
              <X className="h-5 w-5" />
            </button>
          </div>

          {error ? (
            <div className="mt-4 flex items-center gap-2 rounded-xl border border-rose-400/20 bg-rose-500/10 p-3 text-xs text-rose-200">
              <AlertCircle className="h-4 w-4 shrink-0" />
              <span>{error}</span>
            </div>
          ) : null}

          <div className="mt-4 space-y-4">
            <p className="text-xs text-slate-400">
              Select a primary driver for vehicle <span className="font-semibold text-white">{vehicle.operational_code}</span> ({vehicle.plate_number}). One driver belongs to at most one vehicle.
            </p>

            <div>
              <input
                type="text"
                placeholder="Search driver by name, code, phone…"
                value={searchQuery}
                onChange={(e) => {
                  setSearchQuery(e.target.value);
                  void loadDrivers(e.target.value);
                }}
                className="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs text-white outline-none focus:border-cyan-400"
              />
            </div>

            <div className="max-h-48 overflow-y-auto space-y-1 rounded-xl border border-white/5 bg-black/20 p-2">
              {loading ? (
                <p className="p-3 text-center text-xs text-slate-500">Loading drivers…</p>
              ) : drivers.length === 0 ? (
                <p className="p-3 text-center text-xs text-slate-500">No matching drivers found.</p>
              ) : (
                drivers.map((d) => {
                  const isSelected = selectedDriverId === d.id;
                  return (
                    <button
                      key={d.id}
                      type="button"
                      onClick={() => setSelectedDriverId(d.id)}
                      className={`w-full flex items-center justify-between rounded-lg px-3 py-2 text-left transition ${
                        isSelected
                          ? 'bg-cyan-500/20 text-white border border-cyan-400/40'
                          : 'hover:bg-white/5 text-slate-300'
                      }`}
                    >
                      <div>
                        <p className="text-xs font-semibold">{d.fullName}</p>
                        <p className="text-[10px] text-slate-400">#{d.employeeNumber} · {d.phone || 'No phone'}</p>
                      </div>
                      {isSelected ? (
                        <span className="text-[10px] font-bold text-cyan-400 uppercase">Selected</span>
                      ) : null}
                    </button>
                  );
                })
              )}
            </div>
          </div>

          <div className="mt-6 flex items-center justify-between gap-3 pt-4 border-t border-white/10">
            {vehicle.driver_id ? (
              <button
                type="button"
                disabled={submitting}
                onClick={() => void handleUnpair()}
                className="rounded-xl border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-xs font-bold text-rose-300 hover:bg-rose-500/20 disabled:opacity-50"
              >
                Unpair Driver
              </button>
            ) : <div />}

            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={onClose}
                disabled={submitting}
                className="rounded-xl border border-white/10 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-white/5"
              >
                Cancel
              </button>
              <button
                type="button"
                disabled={!selectedDriverId || submitting}
                onClick={() => void handlePair()}
                className="rounded-xl bg-cyan-500 px-4 py-2 text-xs font-bold text-white hover:bg-cyan-600 disabled:opacity-50"
              >
                {submitting ? 'Saving…' : 'Save Pairing'}
              </button>
            </div>
          </div>
        </motion.div>
      </div>
    </AnimatePresence>
  );
}
