import { motion } from 'framer-motion';
import { Truck, User, Package, Building2, MapPin } from 'lucide-react';
import type { IncidentDetails } from '../../../../../features/dispatcher/notifications/services/notification-center.service';

interface IncidentAssetsProps {
  incident: IncidentDetails;
}

export default function IncidentAssets({ incident }: IncidentAssetsProps) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.2 }}
      className="rounded-lg border border-white/5 bg-white/[0.02] p-4"
    >
      <h3 className="text-[10px] font-semibold uppercase tracking-wider text-slate-500 mb-4">
        Affected Assets
      </h3>

      <div className="space-y-3">
        <div className="flex items-center gap-3">
          <Truck className="h-4 w-4 text-slate-500" />
          <div className="flex-1">
            <span className="text-[10px] text-slate-500 uppercase tracking-wider">Vehicle</span>
            <p className="text-sm font-semibold text-white">{incident.vehicle}</p>
          </div>
        </div>

        <div className="flex items-center gap-3">
          <User className="h-4 w-4 text-slate-500" />
          <div className="flex-1">
            <span className="text-[10px] text-slate-500 uppercase tracking-wider">Driver</span>
            <p className="text-sm font-semibold text-white">{incident.driver}</p>
          </div>
        </div>

        <div className="flex items-center gap-3">
          <Package className="h-4 w-4 text-slate-500" />
          <div className="flex-1">
            <span className="text-[10px] text-slate-500 uppercase tracking-wider">Shipment</span>
            <p className="text-sm font-mono text-slate-300">{incident.shipment}</p>
          </div>
        </div>

        <div className="flex items-center gap-3">
          <Building2 className="h-4 w-4 text-slate-500" />
          <div className="flex-1">
            <span className="text-[10px] text-slate-500 uppercase tracking-wider">Customer</span>
            <p className="text-sm font-semibold text-white">{incident.customer}</p>
          </div>
        </div>

        <div className="flex items-center gap-3">
          <MapPin className="h-4 w-4 text-slate-500" />
          <div className="flex-1">
            <span className="text-[10px] text-slate-500 uppercase tracking-wider">Current Location</span>
            <p className="text-sm text-slate-300">{incident.currentLocation}</p>
          </div>
        </div>

        <div className="pt-2 border-t border-white/5">
          <span className="text-[10px] text-slate-500 uppercase tracking-wider">Route</span>
          <p className="text-sm text-slate-300 mt-1">{incident.route}</p>
        </div>
      </div>
    </motion.div>
  );
}
