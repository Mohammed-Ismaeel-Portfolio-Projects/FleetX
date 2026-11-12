import { Shield } from 'lucide-react';
import type { Driver } from '../types/driver-operations.types';

interface DriverComplianceCardProps {
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

/** Documents-only view — no fabricated HOS/compliance metrics. */
export default function DriverComplianceCard({ driver }: DriverComplianceCardProps) {
  if (!driver) {
    return (
      <div className="bg-[#0E1320] border border-white/5 rounded-2xl shadow-lg p-8 flex flex-col items-center justify-center h-full">
        <Shield className="w-16 h-16 text-slate-600 mb-4" />
        <p className="text-sm text-slate-400">Select a driver to view documents</p>
      </div>
    );
  }

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl shadow-lg flex flex-col h-full">
      <div className="p-5 border-b border-white/5">
        <h2 className="text-sm font-bold text-white font-sans flex items-center gap-2">
          <Shield className="w-4 h-4 text-[#00E5FF]" />
          Documents
        </h2>
      </div>
      <div className="flex-1 overflow-y-auto p-5 space-y-4">
        <div className="bg-white/[0.02] border border-white/5 rounded-xl p-4 space-y-2">
          <Field label="License number" value={driver.licenseNumber || 'Unavailable'} />
          <Field label="License class" value={driver.licenseClass || 'Unavailable'} />
          <Field label="License expiry" value={driver.licenseExpiry || 'Unavailable'} />
          <Field label="Medical certificate" value={driver.medicalCertificateExpiry || 'Unavailable'} />
        </div>
        <p className="text-xs text-slate-500">
          Hours of service and safety alert metrics: Unavailable
        </p>
      </div>
    </div>
  );
}
