import { MapPin, Cpu, Wifi, FileText, Package } from 'lucide-react';
import type { VehicleDetails } from '../../types/tracking.types';

interface OperationalHealthProps {
  details: VehicleDetails;
}

export default function OperationalHealth({ details }: OperationalHealthProps) {
  const health = details.operationalHealth;

  if (!health) {
    return null;
  }

  const getHealthColor = (status: string) => {
    switch (status) {
      case 'healthy':
        return 'bg-green-500/10 text-green-400 border-green-500/20';
      case 'warning':
        return 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20';
      case 'critical':
        return 'bg-red-500/10 text-red-400 border-red-500/20';
      default:
        return 'bg-slate-500/10 text-slate-400 border-slate-500/20';
    }
  };

  const healthItems = [
    { key: 'gps', label: 'GPS', icon: MapPin, status: health.gps },
    { key: 'engine', label: 'Engine', icon: Cpu, status: health.engine },
    { key: 'network', label: 'Network', icon: Wifi, status: health.network },
    { key: 'assignment', label: 'Assignment', icon: FileText, status: health.assignment },
    { key: 'shipment', label: 'Shipment', icon: Package, status: health.shipment },
  ];

  return (
    <div className="space-y-3">
      <h3 className="text-xs font-semibold text-white uppercase tracking-wider">Operational Health</h3>
      
      <div className="grid grid-cols-5 gap-2">
        {healthItems.map((item) => {
          const Icon = item.icon;
          return (
            <div
              key={item.key}
              className={`flex flex-col items-center gap-2 rounded-lg border p-2 transition-all duration-300 ${getHealthColor(item.status)}`}
            >
              <Icon className="h-4 w-4" />
              <p className="text-[10px] font-bold uppercase tracking-wider">{item.label}</p>
              <p className="text-[9px] capitalize">{item.status}</p>
            </div>
          );
        })}
      </div>
    </div>
  );
}
