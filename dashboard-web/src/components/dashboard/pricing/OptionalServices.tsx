import { Package, Truck, Wrench, Thermometer, Car, MapPin, Shield } from 'lucide-react';

interface OptionalService {
  id: string;
  name: string;
  icon: any;
  price: number;
  pricingType: 'flat' | 'hourly' | 'daily';
  enabled: boolean;
}

interface OptionalServicesProps {
  services: OptionalService[];
  onToggleService: (id: string) => void;
}

export default function OptionalServices({ services, onToggleService }: OptionalServicesProps) {
  const getServiceIcon = (iconName: string) => {
    switch (iconName) {
      case 'Package':
        return Package;
      case 'Truck':
        return Truck;
      case 'Wrench':
        return Wrench;
      case 'Thermometer':
        return Thermometer;
      case 'Car':
        return Car;
      case 'MapPin':
        return MapPin;
      case 'Shield':
        return Shield;
      default:
        return Package;
    }
  };

  return (
    <div className="rounded-xl border border-white/5 bg-[#0E1320] p-5 shadow-sm">
      <div className="mb-4 flex items-center gap-2">
        <Wrench className="h-4 w-4 text-cyan-400" />
        <h3 className="text-sm font-semibold text-white">Optional Services</h3>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        {services.map((service) => {
          const Icon = getServiceIcon(service.icon);
          
          return (
            <label
              key={service.id}
              className={`flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-all ${
                service.enabled
                  ? 'border-cyan-500/30 bg-cyan-500/10'
                  : 'border-white/5 bg-[#0A0E17] hover:border-white/10'
              }`}
            >
              <input
                type="checkbox"
                checked={service.enabled}
                onChange={() => onToggleService(service.id)}
                className="rounded border-white/10 bg-[#0E1320] text-cyan-500 focus:ring-cyan-500/50"
              />
              <div className={`rounded-lg border p-1.5 ${service.enabled ? 'border-cyan-500/20 bg-cyan-500/10 text-cyan-400' : 'border-white/10 bg-white/5 text-slate-400'}`}>
                <Icon className="h-4 w-4" />
              </div>
              <div className="flex-1">
                <div className="text-xs font-semibold text-white">{service.name}</div>
                <div className="text-[10px] text-slate-400">
                  {service.pricingType === 'flat' ? `$${service.price.toFixed(2)}` : `$${service.price.toFixed(2)}/hr`}
                </div>
              </div>
            </label>
          );
        })}
      </div>
    </div>
  );
}
