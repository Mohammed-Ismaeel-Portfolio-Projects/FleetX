import { Truck } from 'lucide-react';
import type { VehicleDetails } from '../types/tracking-dashboard.types';
import VehicleOverview from './vehicle-details/VehicleOverview';
import TripProgress from './vehicle-details/TripProgress';
import LiveTracking from './vehicle-details/LiveTracking';
import OperationalHealth from './vehicle-details/OperationalHealth';
import QuickMetrics from './vehicle-details/QuickMetrics';
import AdminCompactDriverPerformance from '../../shared/components/AdminCompactDriverPerformance';

interface TrackingDetailsProps {
  details: VehicleDetails | null;
  isLoading?: boolean;
  /** Authoritative backend ids — never use mocked VehicleDetails.assignment.id */
  performanceDriverId?: string | null;
  performanceAssignmentId?: string | null;
}

export default function TrackingDetails({
  details,
  isLoading = false,
  performanceDriverId = null,
  performanceAssignmentId = null,
}: TrackingDetailsProps) {
  if (isLoading) {
    return (
      <div className="rounded-2xl border border-white/5 bg-[#0E1320] p-6 shadow-md">
        <div className="space-y-4">
          {Array.from({ length: 4 }).map((_, index) => (
            <div key={index} className="h-32 animate-pulse rounded-xl bg-white/5" />
          ))}
        </div>
      </div>
    );
  }

  if (!details) {
    return (
      <div className="rounded-2xl border border-white/5 bg-[#0E1320] p-12 text-center shadow-md">
        <Truck className="mx-auto h-12 w-12 text-slate-600" />
        <h3 className="mt-4 text-sm font-semibold text-white">No vehicle selected</h3>
        <p className="mt-2 text-xs text-slate-500">
          Select a vehicle from the fleet list to view details.
        </p>
      </div>
    );
  }

  return (
    <div data-details-panel className="rounded-2xl border border-white/5 bg-[#0E1320] p-6 shadow-md">
      <h2 className="mb-4 text-sm font-semibold text-white">Vehicle Operations</h2>
      
      <div className="space-y-4">
        <VehicleOverview details={details} />

        <AdminCompactDriverPerformance
          driverId={performanceDriverId}
          assignmentId={performanceAssignmentId}
          includeCurrentAssignment
        />
        
        <TripProgress details={details} />
        <LiveTracking details={details} />
        <OperationalHealth details={details} />
        <QuickMetrics details={details} />
      </div>
    </div>
  );
}
