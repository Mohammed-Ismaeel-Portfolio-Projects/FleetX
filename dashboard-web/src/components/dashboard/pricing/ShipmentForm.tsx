import { MapPin, Package, Truck, AlertTriangle, Shield } from 'lucide-react';

interface ShipmentFormData {
  originCity: string;
  destinationCity: string;
  distance: number;
  cargoType: string;
  trailerType: string;
  cargoWeight: number;
  volume: number;
  shipmentPriority: string;
  hazmat: boolean;
  borderCrossing: boolean;
  insuranceRequired: boolean;
  customerType: string;
}

interface ShipmentFormProps {
  data: ShipmentFormData;
  onChange: (data: ShipmentFormData) => void;
}

export default function ShipmentForm({ data, onChange }: ShipmentFormProps) {
  const handleChange = (field: keyof ShipmentFormData, value: any) => {
    onChange({ ...data, [field]: value });
  };

  return (
    <div className="rounded-xl border border-white/5 bg-[#0E1320] p-5 shadow-sm">
      <div className="mb-4 flex items-center gap-2">
        <Package className="h-4 w-4 text-cyan-400" />
        <h3 className="text-sm font-semibold text-white">Shipment Information</h3>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        {/* Origin City */}
        <div>
          <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-500">
            Origin City
          </label>
          <div className="relative">
            <MapPin className="absolute left-3 top-1/2 h-3 w-3 -translate-y-1/2 text-slate-400" />
            <input
              type="text"
              value={data.originCity}
              onChange={(e) => handleChange('originCity', e.target.value)}
              className="w-full rounded border border-white/10 bg-[#0A0E17] pl-9 pr-3 py-2 text-xs text-white outline-none focus:border-cyan-500/50"
              placeholder="Enter origin city"
            />
          </div>
        </div>

        {/* Destination City */}
        <div>
          <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-500">
            Destination City
          </label>
          <div className="relative">
            <MapPin className="absolute left-3 top-1/2 h-3 w-3 -translate-y-1/2 text-slate-400" />
            <input
              type="text"
              value={data.destinationCity}
              onChange={(e) => handleChange('destinationCity', e.target.value)}
              className="w-full rounded border border-white/10 bg-[#0A0E17] pl-9 pr-3 py-2 text-xs text-white outline-none focus:border-cyan-500/50"
              placeholder="Enter destination city"
            />
          </div>
        </div>

        {/* Distance */}
        <div>
          <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-500">
            Distance (KM)
          </label>
          <input
            type="number"
            value={data.distance}
            onChange={(e) => handleChange('distance', parseFloat(e.target.value) || 0)}
            className="w-full rounded border border-white/10 bg-[#0A0E17] px-3 py-2 text-xs text-white outline-none focus:border-cyan-500/50"
            placeholder="Enter distance"
          />
        </div>

        {/* Cargo Type */}
        <div>
          <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-500">
            Cargo Type
          </label>
          <select
            value={data.cargoType}
            onChange={(e) => handleChange('cargoType', e.target.value)}
            className="w-full rounded border border-white/10 bg-[#0A0E17] px-3 py-2 text-xs text-white outline-none focus:border-cyan-500/50"
          >
            <option value="">Select cargo type</option>
            <option value="general">General Cargo</option>
            <option value="refrigerated">Refrigerated</option>
            <option value="heavy">Heavy Equipment</option>
            <option value="hazardous">Hazardous Materials</option>
            <option value="fragile">Fragile Goods</option>
          </select>
        </div>

        {/* Trailer Type */}
        <div>
          <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-500">
            Trailer Type
          </label>
          <div className="relative">
            <Truck className="absolute left-3 top-1/2 h-3 w-3 -translate-y-1/2 text-slate-400" />
            <select
              value={data.trailerType}
              onChange={(e) => handleChange('trailerType', e.target.value)}
              className="w-full rounded border border-white/10 bg-[#0A0E17] pl-9 pr-3 py-2 text-xs text-white outline-none focus:border-cyan-500/50"
            >
              <option value="">Select trailer type</option>
              <option value="dry_van">Dry Van</option>
              <option value="reefer">Reefer</option>
              <option value="flatbed">Flatbed</option>
              <option value="lowboy">Lowboy</option>
              <option value="tanker">Tanker</option>
            </select>
          </div>
        </div>

        {/* Cargo Weight */}
        <div>
          <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-500">
            Cargo Weight (tons)
          </label>
          <input
            type="number"
            step="0.1"
            value={data.cargoWeight}
            onChange={(e) => handleChange('cargoWeight', parseFloat(e.target.value) || 0)}
            className="w-full rounded border border-white/10 bg-[#0A0E17] px-3 py-2 text-xs text-white outline-none focus:border-cyan-500/50"
            placeholder="Enter weight"
          />
        </div>

        {/* Volume */}
        <div>
          <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-500">
            Volume (m³)
          </label>
          <input
            type="number"
            step="0.1"
            value={data.volume}
            onChange={(e) => handleChange('volume', parseFloat(e.target.value) || 0)}
            className="w-full rounded border border-white/10 bg-[#0A0E17] px-3 py-2 text-xs text-white outline-none focus:border-cyan-500/50"
            placeholder="Enter volume"
          />
        </div>

        {/* Shipment Priority */}
        <div>
          <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-500">
            Shipment Priority
          </label>
          <select
            value={data.shipmentPriority}
            onChange={(e) => handleChange('shipmentPriority', e.target.value)}
            className="w-full rounded border border-white/10 bg-[#0A0E17] px-3 py-2 text-xs text-white outline-none focus:border-cyan-500/50"
          >
            <option value="standard">Standard</option>
            <option value="express">Express</option>
            <option value="urgent">Urgent</option>
            <option value="emergency">Emergency</option>
          </select>
        </div>

        {/* Customer Type */}
        <div>
          <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-500">
            Customer Type
          </label>
          <select
            value={data.customerType}
            onChange={(e) => handleChange('customerType', e.target.value)}
            className="w-full rounded border border-white/10 bg-[#0A0E17] px-3 py-2 text-xs text-white outline-none focus:border-cyan-500/50"
          >
            <option value="standard">Standard</option>
            <option value="corporate">Corporate</option>
            <option value="vip">VIP</option>
            <option value="long_term">Long-term Partner</option>
          </select>
        </div>

        {/* Checkbox Options */}
        <div className="col-span-1 sm:col-span-2 lg:col-span-3 grid grid-cols-1 sm:grid-cols-3 gap-4">
          <label className="flex items-center gap-2 rounded-lg border border-white/5 bg-[#0A0E17] p-3 cursor-pointer hover:border-white/10 transition-colors">
            <input
              type="checkbox"
              checked={data.hazmat}
              onChange={(e) => handleChange('hazmat', e.target.checked)}
              className="rounded border-white/10 bg-[#0E1320] text-cyan-500 focus:ring-cyan-500/50"
            />
            <div className="flex items-center gap-2">
              <AlertTriangle className="h-3 w-3 text-orange-400" />
              <span className="text-xs text-white">Hazardous Materials</span>
            </div>
          </label>

          <label className="flex items-center gap-2 rounded-lg border border-white/5 bg-[#0A0E17] p-3 cursor-pointer hover:border-white/10 transition-colors">
            <input
              type="checkbox"
              checked={data.borderCrossing}
              onChange={(e) => handleChange('borderCrossing', e.target.checked)}
              className="rounded border-white/10 bg-[#0E1320] text-cyan-500 focus:ring-cyan-500/50"
            />
            <div className="flex items-center gap-2">
              <MapPin className="h-3 w-3 text-purple-400" />
              <span className="text-xs text-white">Border Crossing</span>
            </div>
          </label>

          <label className="flex items-center gap-2 rounded-lg border border-white/5 bg-[#0A0E17] p-3 cursor-pointer hover:border-white/10 transition-colors">
            <input
              type="checkbox"
              checked={data.insuranceRequired}
              onChange={(e) => handleChange('insuranceRequired', e.target.checked)}
              className="rounded border-white/10 bg-[#0E1320] text-cyan-500 focus:ring-cyan-500/50"
            />
            <div className="flex items-center gap-2">
              <Shield className="h-3 w-3 text-green-400" />
              <span className="text-xs text-white">Insurance Required</span>
            </div>
          </label>
        </div>
      </div>
    </div>
  );
}
