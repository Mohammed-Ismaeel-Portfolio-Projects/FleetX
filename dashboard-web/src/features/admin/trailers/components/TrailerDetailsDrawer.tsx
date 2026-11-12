import { useEffect, useRef } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { Truck, X } from 'lucide-react';
import type { Trailer } from '../types/trailer.types';

interface TrailerDetailsDrawerProps {
  trailer: Trailer | null;
  isOpen: boolean;
  isLoading: boolean;
  onClose: () => void;
  statusNameById: Record<number, string>;
}

function valueOf(value: string | number | boolean | null | undefined) {
  if (typeof value === 'boolean') {
    return value ? 'Yes' : 'No';
  }

  return value || 'Not provided';
}

function DetailSection({ title, rows }: { title: string; rows: Array<[string, string | number | boolean | null | undefined]> }) {
  return (
    <section className="rounded-2xl border border-white/5 bg-[#090D16] p-4">
      <h3 className="text-[10px] font-bold uppercase tracking-widest text-slate-500">{title}</h3>
      <dl className="mt-4 grid grid-cols-1 gap-3">
        {rows.map(([label, value]) => (
          <div key={label} className="flex items-start justify-between gap-4">
            <dt className="text-xs text-slate-500">{label}</dt>
            <dd className="max-w-[62%] text-right text-xs font-semibold text-slate-200">{valueOf(value)}</dd>
          </div>
        ))}
      </dl>
    </section>
  );
}

export default function TrailerDetailsDrawer({
  trailer,
  isOpen,
  isLoading,
  onClose,
  statusNameById,
}: TrailerDetailsDrawerProps) {
  const closeRef = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    if (!isOpen) {
      return;
    }

    closeRef.current?.focus();
    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        onClose();
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [isOpen, onClose]);

  return (
    <AnimatePresence>
      {isOpen && (
        <div className="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label="Trailer details">
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="absolute inset-0 bg-slate-950/70 backdrop-blur-sm"
            onClick={onClose}
          />
          <motion.aside
            initial={{ x: '100%' }}
            animate={{ x: 0 }}
            exit={{ x: '100%' }}
            transition={{ duration: 0.24 }}
            className="absolute right-0 top-0 flex h-full w-full max-w-xl flex-col border-l border-white/10 bg-[#0E1320] shadow-2xl"
          >
            <div className="flex items-start justify-between gap-4 border-b border-white/5 px-6 py-5">
              <div className="flex items-start gap-3">
                <span className="rounded-xl border border-secondary/20 bg-secondary/10 p-2 text-accent">
                  <Truck className="h-5 w-5" />
                </span>
                <div>
                  <h2 className="text-lg font-bold text-white">Trailer Details</h2>
                  <p className="mt-1 text-xs text-slate-500">{trailer?.trailer_code ?? 'Loading trailer information'}</p>
                </div>
              </div>
              <button
                ref={closeRef}
                type="button"
                onClick={onClose}
                className="rounded-xl p-2 text-slate-500 transition-colors hover:bg-white/5 hover:text-white"
                aria-label="Close trailer details"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="flex-1 overflow-y-auto p-6">
              {isLoading ? (
                <div className="flex h-64 items-center justify-center">
                  <span className="h-8 w-8 rounded-full border-2 border-secondary border-t-transparent animate-spin" />
                </div>
              ) : trailer ? (
                <div className="flex flex-col gap-4">
                  <DetailSection
                    title="General"
                    rows={[
                      ['Trailer Code', trailer.trailer_code],
                      ['Fleet Number', trailer.fleet_number],
                      ['Plate Number', trailer.plate_number],
                      ['Trailer Type', trailer.trailer_type],
                      ['Manufacturer', trailer.manufacturer],
                      ['Model', trailer.model],
                      ['Production Year', trailer.production_year],
                    ]}
                  />
                  <DetailSection
                    title="Configuration"
                    rows={[
                      ['Status', statusNameById[trailer.status_id] || `Status #${trailer.status_id}`],
                      ['Active', trailer.is_active],
                      ['Current Odometer', trailer.current_odometer != null ? `${trailer.current_odometer.toLocaleString()} km` : null],
                      ['Axle Count', trailer.axle_count],
                      ['Payload Capacity', trailer.payload_capacity_kg],
                      ['Length', trailer.length_m],
                      ['Width', trailer.width_m],
                      ['Height', trailer.height_m],
                      ['Cargo Volume', trailer.cargo_volume_m3],
                    ]}
                  />
                  <DetailSection
                    title="Dates"
                    rows={[
                      ['Insurance Expiry', trailer.insurance_expiry_date],
                      ['Registration Expiry', trailer.registration_expiry_date],
                      ['Inspection Expiry', trailer.inspection_expiry_date],
                      ['Created At', new Date(trailer.created_at).toLocaleDateString()],
                      ['Updated At', new Date(trailer.updated_at).toLocaleDateString()],
                    ]}
                  />
                  <DetailSection
                    title="Additional"
                    rows={[
                      ['GPS Device Identifier', trailer.gps_device_identifier],
                      ['Notes', trailer.notes],
                    ]}
                  />
                </div>
              ) : (
                <div className="rounded-2xl border border-red-500/20 bg-red-500/10 p-5 text-sm text-red-200">
                  Trailer details could not be loaded.
                </div>
              )}
            </div>
          </motion.aside>
        </div>
      )}
    </AnimatePresence>
  );
}
