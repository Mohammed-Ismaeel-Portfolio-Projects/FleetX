import { motion } from 'framer-motion';
import { useAuth } from '../../../../core/auth';
import { DispatcherSurface } from '../../../../shared/components/dispatcher/DispatcherSurface';
import { PageHeader } from '../../../../shared/components/dispatcher/PageHeader';
import { FilterToolbar } from '../../../../shared/components/dispatcher/FilterToolbar';
import { useLiveTracking } from '../hooks/useLiveTracking';
import TrackingKPIs from '../components/TrackingKPIs';
import FleetMap from '../components/FleetMap';
import FleetSidebar from '../components/FleetSidebar';
import VehicleDetailsPanel from '../components/VehicleDetailsPanel';
import ActiveShipmentTrackingPanel from '../components/ActiveShipmentTrackingPanel';
import TimelineWidget from '../components/TimelineWidget';
import AlertCenter from '../components/AlertCenter';

export default function ActiveTrackingPage() {
  const { user } = useAuth();
  const isPartner = Boolean(user?.isPartnerCarrier);
  const liveTracking = useLiveTracking();

  const itemVariants = {
    hidden: { opacity: 0, y: 15 },
    visible: {
      opacity: 1,
      y: 0,
      transition: { duration: 0.6, ease: [0.16, 1, 0.3, 1] as const },
    },
  };

  const handleFilterReset = () => {
    liveTracking.setSearchQuery('');
    liveTracking.setStatusFilter('All');
    liveTracking.setRegionFilter('All');
  };

  const handleVehicleSelect = (vehicle: { id: string; vehicleId?: string }) => {
    liveTracking.setSelectedVehicleId(vehicle.vehicleId ?? vehicle.id);
  };

  return (
    <DispatcherSurface className="w-full text-left font-sans select-none">
      
      {/* 1. Operations Header */}
      <motion.div variants={itemVariants}>
        <PageHeader
          title="Live Tracking"
          subtitle={
            isPartner
              ? `Partner Fleet Workspace (${user?.companyName || 'Carrier Fleet'}) · Live tracking for active executing shipments.`
              : 'Monitor route progress, vehicle posture, and exception alerts in real time.'
          }
          badge="LIVE"
        />
      </motion.div>

      {/* 2. Tracking KPIs */}
      <motion.div variants={itemVariants}>
        <TrackingKPIs kpis={liveTracking.kpis} />
      </motion.div>

      {/* 3. Filter Toolbar */}
      <motion.div variants={itemVariants}>
        <FilterToolbar searchValue={liveTracking.searchQuery} onSearchChange={liveTracking.setSearchQuery} onReset={handleFilterReset}>
          <select aria-label="Vehicle status" value={liveTracking.statusFilter} onChange={(event) => liveTracking.setStatusFilter(event.target.value)} className="rounded-2xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-300 outline-none">
            <option value="All">All statuses</option>
            <option value="In Transit">In Transit</option>
            <option value="Delayed">Delayed</option>
            <option value="Idle">Idle</option>
          </select>
          <select aria-label="Region filter" value={liveTracking.regionFilter} onChange={(event) => liveTracking.setRegionFilter(event.target.value)} className="rounded-2xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-300 outline-none">
            <option value="All">All regions</option>
            <option value="North">North</option>
            <option value="South">South</option>
            <option value="East">East</option>
            <option value="West">West</option>
          </select>
        </FilterToolbar>
      </motion.div>

      {/* 4. Main Grid */}
      <motion.div 
        variants={itemVariants} 
        className="grid grid-cols-1 lg:grid-cols-12 gap-6 w-full items-stretch"
      >
        {/* Left Fleet Sidebar (col-span-3) */}
        <div className="lg:col-span-3 h-full">
          <FleetSidebar
            vehicles={liveTracking.vehicles}
            selectedVehicleId={liveTracking.selectedVehicleId}
            onVehicleSelect={handleVehicleSelect}
          />
        </div>

        {/* Center Fleet Map & Tracking Engine Workspace (col-span-6) */}
        <div className="tracking-workspace relative lg:col-span-6 h-full min-h-[500px]">
          <FleetMap
            vehicles={liveTracking.vehicles}
            selectedVehicle={liveTracking.selectedVehicle}
            onVehicleSelect={handleVehicleSelect}
          />
          {liveTracking.selectedVehicle && (
            <div className="absolute top-4 left-4 z-[1000] max-w-[calc(100%-2rem)]">
              <ActiveShipmentTrackingPanel
                vehicle={liveTracking.selectedVehicle}
                onClose={() => liveTracking.setSelectedVehicleId(null)}
              />
            </div>
          )}
        </div>

        {/* Right Vehicle Details (col-span-3) */}
        <div className="lg:col-span-3 h-full">
          <VehicleDetailsPanel vehicle={liveTracking.selectedVehicle} />
        </div>
      </motion.div>

      {/* 5. Bottom Grid */}
      <motion.div 
        variants={itemVariants} 
        className="grid grid-cols-1 lg:grid-cols-2 gap-6 w-full"
      >
        {/* Timeline Widget */}
        <div>
          <TimelineWidget />
        </div>

        {/* Alert Center */}
        <div>
          <AlertCenter />
        </div>
      </motion.div>

    </DispatcherSurface>
  );
}
