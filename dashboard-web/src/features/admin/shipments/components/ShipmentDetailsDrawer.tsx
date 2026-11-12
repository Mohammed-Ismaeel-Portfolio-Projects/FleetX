import { useEffect, useRef } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { Package } from 'lucide-react';
import type { Shipment } from '../types/shipment.types';
import { DeliveryEvidenceConsole } from './DeliveryEvidenceConsole';

interface ShipmentDetailsDrawerProps {
  shipment: Shipment | null;
  isOpen: boolean;
  isLoading: boolean;
  onClose: () => void;
}

function DetailSection({ title, rows }: { title: string; rows: Array<[string, string | number | boolean | null]> }) {
  return (
    <section className="rounded-2xl border border-white/5 bg-[#090D16] p-4">
      <h3 className="text-[10px] font-bold uppercase tracking-widest text-slate-500">{title}</h3>
      <dl className="mt-4 grid grid-cols-1 gap-3">
        {rows.map(([label, value]) => (
          <div key={label} className="flex items-start justify-between gap-4">
            <dt className="text-xs text-slate-500">{label}</dt>
            <dd className="max-w-[68%] text-right text-xs font-semibold text-slate-200">{value ?? 'Not provided'}</dd>
          </div>
        ))}
      </dl>
    </section>
  );
}

export default function ShipmentDetailsDrawer({ shipment, isOpen, isLoading, onClose }: ShipmentDetailsDrawerProps) {
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
        <div className="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label="Shipment details">
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
            className="absolute right-0 top-0 flex h-full w-full max-w-2xl flex-col border-l border-white/10 bg-[#0E1320] shadow-2xl"
          >
            <div className="flex items-start justify-between gap-4 border-b border-white/5 px-6 py-5">
              <div className="flex items-start gap-3">
                <span className="rounded-xl border border-secondary/20 bg-secondary/10 p-2 text-accent">
                  <Package className="h-5 w-5" />
                </span>
                <div>
                  <h2 className="text-lg font-bold text-white">Shipment Details</h2>
                  <p className="mt-1 text-xs text-slate-500">{shipment?.shipment_number ?? 'Loading shipment information'}</p>
                </div>
              </div>
              <button
                ref={closeRef}
                type="button"
                onClick={onClose}
                className="rounded-xl p-2 text-slate-500 transition-colors hover:bg-white/5 hover:text-white"
                aria-label="Close shipment details"
              >
                ×
              </button>
            </div>
            <div className="flex-1 overflow-y-auto p-6">
              {isLoading ? (
                <div className="flex h-64 items-center justify-center">
                  <span className="h-8 w-8 rounded-full border-2 border-[#0250C5] border-t-transparent animate-spin" />
                </div>
              ) : shipment ? (
                <div className="space-y-4">
                  <DetailSection
                    title="Shipment Overview"
                    rows={[
                      ['Shipment #', shipment.shipment_number],
                      ['Reference', shipment.reference_number],
                      ['Priority', shipment.priority],
                      ['Status', shipment.status?.name ?? shipment.status_id],
                      ['Customer', shipment.customer_company?.name ?? 'Unknown'],
                      ['Created At', shipment.created_at],
                    ]}
                  />
                  <DetailSection
                    title="Pickup"
                    rows={[
                      ['Company', shipment.pickup_company],
                      ['Contact', shipment.pickup_contact],
                      ['Phone', shipment.pickup_phone],
                      ['Address', shipment.pickup_address],
                      ['City', shipment.pickup_city],
                      ['Country', shipment.pickup_country],
                    ]}
                  />
                  <DetailSection
                    title="Delivery"
                    rows={[
                      ['Company', shipment.delivery_company],
                      ['Contact', shipment.delivery_contact],
                      ['Phone', shipment.delivery_phone],
                      ['Address', shipment.delivery_address],
                      ['City', shipment.delivery_city],
                      ['Country', shipment.delivery_country],
                    ]}
                  />
                  <DetailSection
                    title="Cargo & Dates"
                    rows={[
                      ['Cargo Type', shipment.cargo_type],
                      ['Cargo Weight', shipment.cargo_weight ?? 'N/A'],
                      ['Cargo Volume', shipment.cargo_volume ?? 'N/A'],
                      ['Package Count', shipment.package_count ?? 'N/A'],
                      ['Planned Pickup', shipment.planned_pickup_date],
                      ['Planned Delivery', shipment.planned_delivery_date],
                      ['Actual Pickup', shipment.actual_pickup_date],
                      ['Actual Delivery', shipment.actual_delivery_date],
                    ]}
                  />
                  <DetailSection
                    title="Notes"
                    rows={[
                      ['Instructions', shipment.special_instructions],
                      ['Notes', shipment.notes],
                    ]}
                  />
                  <DeliveryEvidenceConsole shipmentId={shipment.id} />
                </div>
              ) : (
                <div className="rounded-2xl border border-red-500/20 bg-red-500/10 p-5 text-sm text-red-200">
                  Shipment details could not be loaded.
                </div>
              )}
            </div>
          </motion.aside>
        </div>
      )}
    </AnimatePresence>
  );
}
