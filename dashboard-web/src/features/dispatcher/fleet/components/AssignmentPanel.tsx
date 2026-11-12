import { Truck } from 'lucide-react';
import type { Vehicle } from '../types/fleet-operations.types';

interface AssignmentPanelProps {
  vehicle: Vehicle | null;
}

function Field({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between gap-3">
      <span className="text-xs text-slate-400">{label}</span>
      <span className="text-xs font-bold text-white text-right">{value}</span>
    </div>
  );
}

export default function AssignmentPanel({ vehicle }: AssignmentPanelProps) {
  if (!vehicle) {
    return (
      <div className="bg-[#0E1320] border border-white/5 rounded-2xl shadow-lg p-8 flex flex-col items-center justify-center h-full">
        <Truck className="w-16 h-16 text-slate-600 mb-4" />
        <p className="text-sm text-slate-400">Select a vehicle to view assignment</p>
      </div>
    );
  }

  const assignment = vehicle.assignment;

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl shadow-lg flex flex-col h-full">
      <div className="p-5 border-b border-white/5">
        <h2 className="text-sm font-bold text-white font-sans flex items-center gap-2">
          <Truck className="w-4 h-4 text-[#00E5FF]" />
          Assignment Panel
        </h2>
      </div>

      <div className="flex-1 overflow-y-auto p-5 space-y-5">
        {assignment ? (
          <div className="bg-blue-500/10 border border-blue-500/20 rounded-xl p-4">
            <h3 className="text-xs font-bold text-blue-400 uppercase tracking-wider mb-3">Current Assignment</h3>
            <div className="space-y-2">
              <Field
                label="Assignment"
                value={assignment.assignmentCode || 'Unavailable'}
              />
              <Field
                label="Assignment State"
                value={assignment.isActive ? 'Active' : 'Inactive'}
              />
              <Field
                label="Driver"
                value={assignment.driverName || 'Unavailable'}
              />
              <Field
                label="Shipment"
                value={assignment.shipmentNumber || 'Unavailable'}
              />
              <Field
                label="Trailer Type"
                value={assignment.trailerType || 'Unavailable'}
              />
              <Field
                label="Trailer Plate"
                value={assignment.trailerPlate || 'Unavailable'}
              />
              <Field
                label="Vehicle Status"
                value={vehicle.status || 'Unavailable'}
              />
              <Field
                label="Availability"
                value={vehicle.availability}
              />
            </div>
          </div>
        ) : (
          <div className="bg-white/[0.02] border border-white/5 rounded-xl p-6 flex flex-col items-center justify-center text-center space-y-3">
            <p className="text-sm text-slate-400">No active assignment</p>
            <div className="w-full space-y-2 text-left">
              <Field label="Driver" value="Unavailable" />
              <Field label="Shipment" value="Unavailable" />
              <Field label="Trailer" value="Unavailable" />
              <Field label="Vehicle Status" value={vehicle.status || 'Unavailable'} />
              <Field label="Availability" value={vehicle.availability} />
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
