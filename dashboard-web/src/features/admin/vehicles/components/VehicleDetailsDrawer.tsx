import { useEffect, useRef } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { Truck, User, X } from 'lucide-react';
import type { Vehicle } from '../types/vehicle.types';

interface VehicleDetailsDrawerProps {
  vehicle: Vehicle | null;
  isOpen: boolean;
  isLoading: boolean;
  onClose: () => void;
  onPairDriver?: (vehicle: Vehicle) => void;
  vehicleTypeNameById: Record<number, string>;
  fuelTypeNameById: Record<number, string>;
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

export default function VehicleDetailsDrawer({
  vehicle,
  isOpen,
  isLoading,
  onClose,
  vehicleTypeNameById,
  fuelTypeNameById,
  statusNameById,
}: VehicleDetailsDrawerProps) {
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
        <div className="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label="Vehicle details">
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
                <span className="rounded-xl border border-[#0250C5]/20 bg-[#0250C5]/10 p-2 text-[#00E5FF]">
                  <Truck className="h-5 w-5" />
                </span>
                <div>
                  <h2 className="text-lg font-bold text-white">Vehicle Details</h2>
                  <p className="mt-1 text-xs text-slate-500">{vehicle?.operational_code ?? 'Loading vehicle information'}</p>
                </div>
              </div>
              <button
                ref={closeRef}
                type="button"
                onClick={onClose}
                className="rounded-xl p-2 text-slate-500 transition-colors hover:bg-white/5 hover:text-white"
                aria-label="Close vehicle details"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="flex-1 overflow-y-auto p-6">
              {isLoading ? (
                <div className="flex h-64 items-center justify-center">
                  <span className="h-8 w-8 rounded-full border-2 border-[#0250C5] border-t-transparent animate-spin" />
                </div>
              ) : vehicle ? (
                <div className="flex flex-col gap-4">
                  <div className="rounded-2xl border border-cyan-400/20 bg-cyan-500/10 p-4">
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-2.5">
                        <User className="h-5 w-5 text-cyan-400 shrink-0" />
                        <div>
                          <h3 className="text-xs font-bold uppercase tracking-wider text-cyan-300">Driver Pairing (Master Data)</h3>
                          {vehicle.driver ? (
                            <p className="text-sm font-semibold text-white">
                              {vehicle.driver.full_name || `${vehicle.driver.first_name} ${vehicle.driver.last_name}`} (#{vehicle.driver.employee_number})
                            </p>
                          ) : (
                            <p className="text-xs text-slate-400 italic">No driver paired to this vehicle</p>
                          )}
                        </div>
                      </div>
                      {onPairDriver ? (
                        <button
                          type="button"
                          onClick={() => onPairDriver(vehicle)}
                          className="rounded-xl border border-cyan-400/30 bg-cyan-500/20 px-3 py-1.5 text-xs font-bold text-cyan-200 hover:bg-cyan-500/30 transition"
                        >
                          {vehicle.driver ? 'Change Driver' : 'Pair Driver'}
                        </button>
                      ) : null}
                    </div>
                  </div>

                  <DetailSection
                    title="General"
                    rows={[
                      ['Operational Code', vehicle.operational_code],
                      ['Fleet Number', vehicle.fleet_number],
                      ['Plate Number', vehicle.plate_number],
                      ['VIN', vehicle.vin],
                      ['Manufacturer', vehicle.manufacturer],
                      ['Model', vehicle.model],
                      ['Production Year', vehicle.production_year],
                      ['Transmission', vehicle.transmission],
                    ]}
                  />
                  <DetailSection
                    title="Configuration"
                    rows={[
                      ['Vehicle Type', vehicleTypeNameById[vehicle.vehicle_type_id] || `Type #${vehicle.vehicle_type_id}`],
                      ['Fuel Type', fuelTypeNameById[vehicle.fuel_type_id] || `Fuel #${vehicle.fuel_type_id}`],
                      ['Status', statusNameById[vehicle.status_id] || `Status #${vehicle.status_id}`],
                      ['Active', vehicle.is_active],
                      ['Current Odometer', vehicle.current_odometer != null ? `${vehicle.current_odometer.toLocaleString()} km` : null],
                      ['Axle Count', vehicle.axle_count],
                      ['Payload Capacity', vehicle.payload_capacity_kg],
                      ['Gross Weight', vehicle.gross_weight_kg],
                      ['Fuel Tank Capacity', vehicle.fuel_tank_capacity_l],
                    ]}
                  />
                  <DetailSection
                    title="Dates"
                    rows={[
                      ['Insurance Expiry', vehicle.insurance_expiry_date],
                      ['Registration Expiry', vehicle.registration_expiry_date],
                      ['Inspection Expiry', vehicle.inspection_expiry_date],
                      ['Created At', new Date(vehicle.created_at).toLocaleDateString()],
                      ['Updated At', new Date(vehicle.updated_at).toLocaleDateString()],
                    ]}
                  />
                  <DetailSection
                    title="Additional"
                    rows={[
                      ['GPS Device Identifier', vehicle.gps_device_identifier],
                      ['Notes', vehicle.notes],
                    ]}
                  />
                </div>
              ) : (
                <div className="rounded-2xl border border-red-500/20 bg-red-500/10 p-5 text-sm text-red-200">
                  Vehicle details could not be loaded.
                </div>
              )}
            </div>
          </motion.aside>
        </div>
      )}
    </AnimatePresence>
  );
}
