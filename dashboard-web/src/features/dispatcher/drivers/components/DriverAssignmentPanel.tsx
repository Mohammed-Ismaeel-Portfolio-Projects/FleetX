import { Truck } from 'lucide-react';
import type { Driver } from '../types/driver-operations.types';
import CurrentAssignmentPerformanceBlock from './CurrentAssignmentPerformanceBlock';

interface DriverAssignmentPanelProps {
  driver: Driver | null;
}

function Field({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between gap-3">
      <span className="text-xs text-slate-400">{label}</span>
      <span className="text-xs font-bold text-white text-right">{value}</span>
    </div>
  );
}

export default function DriverAssignmentPanel({ driver }: DriverAssignmentPanelProps) {
  if (!driver) {
    return (
      <div className="bg-[#0E1320] border border-white/5 rounded-2xl shadow-lg p-8 flex flex-col items-center justify-center h-full">
        <Truck className="w-16 h-16 text-slate-600 mb-4" />
        <p className="text-sm text-slate-400">Select a driver to view assignment</p>
      </div>
    );
  }

  const assignment = driver.assignment;

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl shadow-lg flex flex-col h-full">
      <div className="p-5 border-b border-white/5">
        <h2 className="text-sm font-bold text-white font-sans flex items-center gap-2">
          <Truck className="w-4 h-4 text-[#00E5FF]" />
          Assignment
        </h2>
      </div>
      <div className="flex-1 overflow-y-auto p-5 space-y-4">
        <div className="bg-white/[0.02] border border-white/5 rounded-xl p-4 space-y-2">
          <Field label="Official status" value={driver.status || 'Unavailable'} />
          <Field label="Dispatch availability" value={driver.dispatchAvailability || 'Unavailable'} />
        </div>

        {assignment ? (
          <div className="bg-blue-500/10 border border-blue-500/20 rounded-xl p-4 space-y-2">
            <h3 className="text-xs font-bold text-blue-400 uppercase tracking-wider mb-2">Active Assignment</h3>
            <Field label="Code" value={assignment.assignmentCode || 'Unavailable'} />
            <Field label="Active" value={assignment.isActive ? 'Yes' : 'No'} />
            <Field label="Vehicle" value={assignment.vehicleCode || 'Unavailable'} />
            <Field label="Plate" value={assignment.vehiclePlate || 'Unavailable'} />
            <Field label="Shipment" value={assignment.shipmentNumber || 'Unavailable'} />
            <Field
              label="Route"
              value={
                assignment.pickupCity || assignment.deliveryCity
                  ? `${assignment.pickupCity || 'Unavailable'} → ${assignment.deliveryCity || 'Unavailable'}`
                  : 'Unavailable'
              }
            />
          </div>
        ) : (
          <div className="bg-white/[0.02] border border-white/5 rounded-xl p-6 text-center">
            <p className="text-sm text-slate-400">Unavailable — no active assignment</p>
          </div>
        )}

        {/* DRIVER-EVAL-08C — current trip evaluation (selected assignment only) */}
        <CurrentAssignmentPerformanceBlock assignmentId={assignment?.id ?? null} />
      </div>
    </div>
  );
}
