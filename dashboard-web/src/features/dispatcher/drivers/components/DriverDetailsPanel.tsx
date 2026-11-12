import { User, Phone, Mail, Truck, MapPin, FileText, Wifi, Calendar } from 'lucide-react';
import type { Driver } from '../types/driver-operations.types';

interface DriverDetailsPanelProps {
  driver: Driver | null;
}

function Field({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between gap-3">
      <span className="text-xs text-slate-400">{label}</span>
      <span className="text-sm font-semibold text-white text-right">{value}</span>
    </div>
  );
}

export default function DriverDetailsPanel({ driver }: DriverDetailsPanelProps) {
  if (!driver) {
    return (
      <div className="bg-[#0E1320] border border-white/5 rounded-2xl shadow-lg p-8 flex flex-col items-center justify-center h-full">
        <User className="w-16 h-16 text-slate-600 mb-4" />
        <p className="text-sm text-slate-400">Select a driver to view details</p>
      </div>
    );
  }

  const location =
    driver.tracking?.latitude != null && driver.tracking?.longitude != null
      ? `${driver.tracking.latitude.toFixed(4)}, ${driver.tracking.longitude.toFixed(4)}`
      : 'Unavailable';

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl shadow-lg flex flex-col h-full overflow-hidden">
      <div className="p-5 border-b border-white/5">
        <div className="flex items-center gap-4">
          <div className="w-14 h-14 rounded-2xl bg-gradient-to-br from-[#0250C5] to-[#00E5FF] flex items-center justify-center text-lg font-bold text-white">
            {driver.name.split(' ').map((n) => n[0]).join('').slice(0, 2)}
          </div>
          <div>
            <h2 className="text-lg font-bold text-white">{driver.name}</h2>
            <p className="text-sm text-slate-400">{driver.employeeNumber}</p>
            <p className="text-xs text-slate-500 mt-1">
              Status: {driver.status} · Availability: {driver.dispatchAvailability || 'Unavailable'}
            </p>
          </div>
        </div>
      </div>

      <div className="flex-1 overflow-y-auto p-5 space-y-4">
        <div className="bg-white/[0.02] border border-white/5 rounded-xl p-4 space-y-2">
          <h3 className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 flex items-center gap-2">
            <User className="w-3.5 h-3.5" /> Contact
          </h3>
          <div className="flex items-center gap-2 text-sm text-slate-300">
            <Phone className="w-3.5 h-3.5 text-slate-500" />
            {driver.phone || 'Unavailable'}
          </div>
          <div className="flex items-center gap-2 text-sm text-slate-300">
            <Mail className="w-3.5 h-3.5 text-slate-500" />
            {driver.email || 'Unavailable'}
          </div>
        </div>

        <div className="bg-white/[0.02] border border-white/5 rounded-xl p-4 space-y-2">
          <h3 className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 flex items-center gap-2">
            <Truck className="w-3.5 h-3.5" /> Assignment
          </h3>
          <Field label="Vehicle" value={driver.assignment?.vehicleCode || 'Unavailable'} />
          <Field label="Plate" value={driver.assignment?.vehiclePlate || 'Unavailable'} />
          <Field label="Shipment" value={driver.assignment?.shipmentNumber || 'Unavailable'} />
          <Field label="Assignment code" value={driver.assignment?.assignmentCode || 'Unavailable'} />
        </div>

        <div className="bg-white/[0.02] border border-white/5 rounded-xl p-4 space-y-2">
          <h3 className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 flex items-center gap-2">
            <MapPin className="w-3.5 h-3.5" /> Tracking
          </h3>
          <Field label="Location" value={location} />
          <Field label="Online" value={driver.tracking?.onlineStatus || 'Unavailable'} />
          <Field
            label="Signal quality"
            value={driver.tracking?.signalQuality != null ? String(driver.tracking.signalQuality) : 'Unavailable'}
          />
          <Field label="Speed" value={driver.tracking?.speed != null ? String(driver.tracking.speed) : 'Unavailable'} />
          <Field label="Last update" value={driver.tracking?.lastUpdateAt || 'Unavailable'} />
          <div className="flex items-center gap-2 text-xs text-slate-500 pt-1">
            <Wifi className="w-3.5 h-3.5" />
            Movement: {driver.tracking?.movementStatus || 'Unavailable'}
          </div>
        </div>

        <div className="bg-white/[0.02] border border-white/5 rounded-xl p-4 space-y-2">
          <h3 className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 flex items-center gap-2">
            <FileText className="w-3.5 h-3.5" /> Documents
          </h3>
          <Field label="License number" value={driver.licenseNumber || 'Unavailable'} />
          <Field label="License class" value={driver.licenseClass || 'Unavailable'} />
          <Field label="License expiry" value={driver.licenseExpiry || 'Unavailable'} />
          <Field label="Medical certificate" value={driver.medicalCertificateExpiry || 'Unavailable'} />
        </div>

        <div className="bg-white/[0.02] border border-white/5 rounded-xl p-4 space-y-2">
          <h3 className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 flex items-center gap-2">
            <Calendar className="w-3.5 h-3.5" /> Employment
          </h3>
          <Field
            label="Years of experience"
            value={driver.yearsOfExperience != null ? String(driver.yearsOfExperience) : 'Unavailable'}
          />
          <Field label="Employment date" value={driver.employmentDate || 'Unavailable'} />
          <Field label="Employment active" value={driver.isActive ? 'Yes' : 'No'} />
          <Field label="Emergency contact" value={driver.emergencyContactName || 'Unavailable'} />
          <Field label="Emergency phone" value={driver.emergencyContactPhone || 'Unavailable'} />
        </div>

        <div className="bg-white/[0.02] border border-white/5 rounded-xl p-4">
          <h3 className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Notes</h3>
          <p className="text-sm text-slate-300 whitespace-pre-wrap">{driver.notes || 'Unavailable'}</p>
        </div>
      </div>
    </div>
  );
}
