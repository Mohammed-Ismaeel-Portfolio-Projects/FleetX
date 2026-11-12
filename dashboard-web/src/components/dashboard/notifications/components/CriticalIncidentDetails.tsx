import { motion } from 'framer-motion';
import { Truck, Package, User, MapPin, Clock, DollarSign, Brain } from 'lucide-react';
import type { CriticalIncident } from '../../../../features/dispatcher/notifications/services/notification-center.service';

interface CriticalIncidentDetailsProps {
  incident: CriticalIncident;
}

export default function CriticalIncidentDetails({ incident }: CriticalIncidentDetailsProps) {
  if (!incident.exists) {
    return (
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.2 }}
        className="flex flex-col gap-4"
      >
        <div className="flex items-center gap-6">
          <div className="flex items-center gap-2">
            <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
              System Availability
            </span>
            <span className="text-sm font-mono font-semibold text-green-400">
              {incident.systemAvailability}
            </span>
          </div>
          <div className="h-4 w-px bg-white/10" />
          <div className="flex items-center gap-2">
            <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
              Active Alerts
            </span>
            <span className="text-sm font-mono font-semibold text-white">
              {incident.activeAlerts}
            </span>
          </div>
          <div className="h-4 w-px bg-white/10" />
          <div className="flex items-center gap-2">
            <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
              Last Critical Incident
            </span>
            <span className="text-sm font-mono text-slate-400">
              {incident.lastCriticalIncident}
            </span>
          </div>
        </div>
      </motion.div>
    );
  }

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.2 }}
      className="flex flex-col gap-4"
    >
      {/* Incident Details Grid */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div className="flex flex-col gap-1">
          <div className="flex items-center gap-2">
            <Truck className="h-3 w-3 text-slate-500" />
            <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
              Vehicle
            </span>
          </div>
          <span className="text-sm font-mono font-semibold text-white">
            {incident.vehicle}
          </span>
        </div>

        <div className="flex flex-col gap-1">
          <div className="flex items-center gap-2">
            <User className="h-3 w-3 text-slate-500" />
            <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
              Driver
            </span>
          </div>
          <span className="text-sm font-semibold text-white">
            {incident.driver}
          </span>
        </div>

        <div className="flex flex-col gap-1">
          <div className="flex items-center gap-2">
            <Package className="h-3 w-3 text-slate-500" />
            <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
              Shipment
            </span>
          </div>
          <span className="text-sm font-mono text-slate-400">
            {incident.shipment}
          </span>
        </div>

        <div className="flex flex-col gap-1">
          <div className="flex items-center gap-2">
            <User className="h-3 w-3 text-slate-500" />
            <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
              Customer
            </span>
          </div>
          <span className="text-sm font-semibold text-white">
            {incident.customer}
          </span>
        </div>

        <div className="flex flex-col gap-1">
          <div className="flex items-center gap-2">
            <MapPin className="h-3 w-3 text-slate-500" />
            <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
              Origin
            </span>
          </div>
          <span className="text-sm font-semibold text-white">
            {incident.origin}
          </span>
        </div>

        <div className="flex flex-col gap-1">
          <div className="flex items-center gap-2">
            <MapPin className="h-3 w-3 text-slate-500" />
            <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
              Destination
            </span>
          </div>
          <span className="text-sm font-semibold text-white">
            {incident.destination}
          </span>
        </div>

        <div className="flex flex-col gap-1">
          <div className="flex items-center gap-2">
            <MapPin className="h-3 w-3 text-slate-500" />
            <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
              Current Location
            </span>
          </div>
          <span className="text-sm font-semibold text-white">
            {incident.currentLocation}
          </span>
        </div>

        <div className="flex flex-col gap-1">
          <div className="flex items-center gap-2">
            <Clock className="h-3 w-3 text-slate-500" />
            <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
              Estimated Delay
            </span>
          </div>
          <span className="text-sm font-mono font-semibold text-red-400">
            {incident.estimatedDelay}
          </span>
        </div>
      </div>

      {/* Additional Metrics */}
      <div className="flex flex-wrap items-center gap-6">
        <div className="flex items-center gap-2">
          <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
            Affected Deliveries
          </span>
          <span className="text-sm font-mono font-semibold text-white">
            {incident.affectedDeliveries}
          </span>
        </div>
        <div className="h-4 w-px bg-white/10" />
        <div className="flex items-center gap-2">
          <DollarSign className="h-3 w-3 text-slate-500" />
          <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
            Financial Impact
          </span>
          <span className="text-sm font-mono font-semibold text-orange-400">
            {incident.estimatedFinancialImpact}
          </span>
        </div>
        <div className="h-4 w-px bg-white/10" />
        <div className="flex items-center gap-2">
          <Brain className="h-3 w-3 text-slate-500" />
          <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
            AI Confidence
          </span>
          <span className="text-sm font-mono font-semibold text-purple-400">
            {incident.aiConfidence}
          </span>
        </div>
        <div className="h-4 w-px bg-white/10" />
        <div className="flex items-center gap-2">
          <Clock className="h-3 w-3 text-slate-500" />
          <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
            Time Since Triggered
          </span>
          <span className="text-sm font-mono text-slate-400">
            {incident.timeSinceTriggered}
          </span>
        </div>
      </div>

      {/* AI Summary */}
      <div className="rounded-lg border border-purple-500/20 bg-purple-500/5 p-4">
        <div className="flex items-center gap-2 mb-2">
          <Brain className="h-4 w-4 text-purple-400" />
          <span className="text-[10px] font-semibold uppercase tracking-wider text-purple-400">
            AI Summary
          </span>
        </div>
        <p className="text-sm text-slate-300 leading-relaxed">
          {incident.aiSummary}
        </p>
      </div>
    </motion.div>
  );
}
