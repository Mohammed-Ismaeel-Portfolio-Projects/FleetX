import { useState } from 'react';
import { Check } from 'lucide-react';
import LoadingUnloadingServices from './LoadingUnloadingServices';
import AdditionalCharges from './AdditionalCharges';
import EquipmentPricing from './EquipmentPricing';
import DriverExtraCharges from './DriverExtraCharges';

export default function AdditionalPricingPolicies() {
  const [loadingUnloadingServices, setLoadingUnloadingServices] = useState([
    {
      id: '1',
      name: 'FleetX Loading Crew',
      category: 'loading' as const,
      pricingType: 'flat' as const,
      price: 250,
      status: 'active' as const,
    },
    {
      id: '2',
      name: 'FleetX Unloading Crew',
      category: 'unloading' as const,
      pricingType: 'flat' as const,
      price: 250,
      status: 'active' as const,
    },
    {
      id: '3',
      name: 'Forklift',
      category: 'equipment' as const,
      pricingType: 'hourly' as const,
      price: 75,
      status: 'active' as const,
    },
    {
      id: '4',
      name: 'Tail Lift',
      category: 'equipment' as const,
      pricingType: 'flat' as const,
      price: 150,
      status: 'active' as const,
    },
    {
      id: '5',
      name: 'Crane',
      category: 'equipment' as const,
      pricingType: 'hourly' as const,
      price: 200,
      status: 'active' as const,
    },
    {
      id: '6',
      name: 'Pallet Jack',
      category: 'equipment' as const,
      pricingType: 'flat' as const,
      price: 50,
      status: 'active' as const,
    },
    {
      id: '7',
      name: 'Hand Loading',
      category: 'loading' as const,
      pricingType: 'hourly' as const,
      price: 45,
      status: 'active' as const,
    },
    {
      id: '8',
      name: 'Hand Unloading',
      category: 'unloading' as const,
      pricingType: 'hourly' as const,
      price: 45,
      status: 'active' as const,
    },
  ]);

  const [additionalCharges, setAdditionalCharges] = useState([
    {
      id: '1',
      name: 'Night Delivery',
      price: 50,
      chargeType: 'flat' as const,
      enabled: true,
      description: 'Surcharge for deliveries between 10PM-6AM',
      category: 'time' as const,
    },
    {
      id: '2',
      name: 'Weekend Delivery',
      price: 35,
      chargeType: 'flat' as const,
      enabled: true,
      description: 'Surcharge for Saturday and Sunday deliveries',
      category: 'time' as const,
    },
    {
      id: '3',
      name: 'Holiday Delivery',
      price: 75,
      chargeType: 'flat' as const,
      enabled: true,
      description: 'Surcharge for public holiday deliveries',
      category: 'time' as const,
    },
    {
      id: '4',
      name: 'Border Crossing',
      price: 150,
      chargeType: 'flat' as const,
      enabled: true,
      description: 'Fee for international border crossings',
      category: 'special' as const,
    },
    {
      id: '5',
      name: 'Hazmat Shipment',
      price: 25,
      chargeType: 'percentage' as const,
      enabled: true,
      description: 'Additional charge for hazardous materials',
      category: 'special' as const,
    },
    {
      id: '6',
      name: 'Cargo Insurance',
      price: 2,
      chargeType: 'percentage' as const,
      enabled: true,
      description: 'Insurance coverage for cargo value',
      category: 'insurance' as const,
    },
    {
      id: '7',
      name: 'Traffic Delay',
      price: 25,
      chargeType: 'flat' as const,
      enabled: true,
      description: 'Charge for traffic-related delays over 2 hours',
      category: 'penalty' as const,
    },
    {
      id: '8',
      name: 'Customer Delay',
      price: 50,
      chargeType: 'flat' as const,
      enabled: true,
      description: 'Charge for delays caused by customer',
      category: 'penalty' as const,
    },
    {
      id: '9',
      name: 'Route Change',
      price: 75,
      chargeType: 'flat' as const,
      enabled: true,
      description: 'Fee for route changes after dispatch',
      category: 'penalty' as const,
    },
    {
      id: '10',
      name: 'Failed Delivery',
      price: 100,
      chargeType: 'flat' as const,
      enabled: true,
      description: 'Charge for failed delivery attempts',
      category: 'penalty' as const,
    },
    {
      id: '11',
      name: 'Emergency Dispatch',
      price: 200,
      chargeType: 'flat' as const,
      enabled: true,
      description: 'Surcharge for emergency same-day dispatch',
      category: 'special' as const,
    },
  ]);

  const [equipment, setEquipment] = useState([
    {
      id: '1',
      name: 'Forklift',
      price: 75,
      pricingType: 'hourly' as const,
      status: 'active' as const,
      description: 'Heavy-duty forklift for cargo handling',
    },
    {
      id: '2',
      name: 'Crane',
      price: 200,
      pricingType: 'hourly' as const,
      status: 'active' as const,
      description: 'Mobile crane for heavy lifting',
    },
    {
      id: '3',
      name: 'Tail Lift',
      price: 150,
      pricingType: 'flat' as const,
      status: 'active' as const,
      description: 'Tail lift for loading/unloading',
    },
    {
      id: '4',
      name: 'Temperature Monitoring',
      price: 25,
      pricingType: 'daily' as const,
      status: 'active' as const,
      description: 'Real-time temperature monitoring system',
    },
    {
      id: '5',
      name: 'GPS Seal',
      price: 15,
      pricingType: 'flat' as const,
      status: 'active' as const,
      description: 'GPS-enabled security seal for cargo',
    },
    {
      id: '6',
      name: 'Escort Vehicle',
      price: 300,
      pricingType: 'daily' as const,
      status: 'active' as const,
      description: 'Escort vehicle for oversized cargo',
    },
    {
      id: '7',
      name: 'Refrigerated Generator',
      price: 100,
      pricingType: 'daily' as const,
      status: 'active' as const,
      description: 'Backup generator for refrigerated trailers',
    },
  ]);

  const [driverCharges, setDriverCharges] = useState([
    {
      id: '1',
      name: 'Second Driver',
      price: 150,
      chargeType: 'flat' as const,
      status: 'active' as const,
      description: 'Additional driver for long-haul routes',
    },
    {
      id: '2',
      name: 'Night Shift',
      price: 25,
      chargeType: 'flat' as const,
      status: 'active' as const,
      description: 'Surcharge for night shift operations',
    },
    {
      id: '3',
      name: 'Weekend Shift',
      price: 30,
      chargeType: 'flat' as const,
      status: 'active' as const,
      description: 'Surcharge for weekend shift operations',
    },
    {
      id: '4',
      name: 'Holiday Shift',
      price: 50,
      chargeType: 'flat' as const,
      status: 'active' as const,
      description: 'Surcharge for holiday shift operations',
    },
    {
      id: '5',
      name: 'Border Crossing Allowance',
      price: 75,
      chargeType: 'flat' as const,
      status: 'active' as const,
      description: 'Allowance for international border crossings',
    },
    {
      id: '6',
      name: 'Long Distance Bonus',
      price: 0.15,
      chargeType: 'percentage' as const,
      status: 'active' as const,
      description: 'Bonus for trips over 1000 km',
    },
  ]);

  const [hasChanges, setHasChanges] = useState(false);

  const handleUpdateLoadingUnloadingService = (id: string, updates: any) => {
    setLoadingUnloadingServices(prev => prev.map(s => s.id === id ? { ...s, ...updates } : s));
    setHasChanges(true);
  };

  const handleCreateLoadingUnloadingService = () => {
    // In production, this would open a modal to create a new service
    console.log('Create new loading/unloading service');
  };

  const handleUpdateAdditionalCharge = (id: string, updates: any) => {
    setAdditionalCharges(prev => prev.map(c => c.id === id ? { ...c, ...updates } : c));
    setHasChanges(true);
  };

  const handleCreateAdditionalCharge = () => {
    // In production, this would open a modal to create a new charge
    console.log('Create new additional charge');
  };

  const handleUpdateEquipment = (id: string, updates: any) => {
    setEquipment(prev => prev.map(e => e.id === id ? { ...e, ...updates } : e));
    setHasChanges(true);
  };

  const handleCreateEquipment = () => {
    // In production, this would open a modal to create new equipment
    console.log('Create new equipment');
  };

  const handleUpdateDriverCharge = (id: string, updates: any) => {
    setDriverCharges(prev => prev.map(c => c.id === id ? { ...c, ...updates } : c));
    setHasChanges(true);
  };

  const handleCreateDriverCharge = () => {
    // In production, this would open a modal to create a new driver charge
    console.log('Create new driver charge');
  };

  const handleSaveConfiguration = () => {
    setHasChanges(false);
    // In production, this would save all changes to backend
    console.log('Saving additional pricing policies configuration...');
  };

  return (
    <div className="flex flex-col gap-6 w-full">
      {/* Section Header */}
      <div className="flex flex-col gap-1.5">
        <h2 className="text-lg font-semibold tracking-tight text-white font-sans">
          Additional Pricing Policies
        </h2>
        <p className="text-slate-500 text-xs font-normal font-sans">
          Configure optional services, surcharges, equipment costs, and driver charges for comprehensive freight pricing.
        </p>
      </div>

      {/* Module 1: Loading & Unloading Services */}
      <LoadingUnloadingServices
        services={loadingUnloadingServices}
        onUpdateService={handleUpdateLoadingUnloadingService}
        onCreateService={handleCreateLoadingUnloadingService}
      />

      {/* Module 2: Additional Charges */}
      <AdditionalCharges
        charges={additionalCharges}
        onUpdateCharge={handleUpdateAdditionalCharge}
        onCreateCharge={handleCreateAdditionalCharge}
      />

      {/* Module 3: Equipment Pricing */}
      <EquipmentPricing
        equipment={equipment}
        onUpdateEquipment={handleUpdateEquipment}
        onCreateEquipment={handleCreateEquipment}
      />

      {/* Module 4: Driver Extra Charges */}
      <DriverExtraCharges
        charges={driverCharges}
        onUpdateCharge={handleUpdateDriverCharge}
        onCreateCharge={handleCreateDriverCharge}
      />

      {/* Save Configuration Button */}
      {hasChanges && (
        <div className="flex justify-end">
          <button
            type="button"
            onClick={handleSaveConfiguration}
            className="inline-flex items-center gap-2 rounded-lg border border-cyan-500/30 bg-cyan-500/10 px-6 py-2.5 text-sm font-medium text-cyan-400 transition-colors hover:bg-cyan-500/20"
          >
            <Check className="h-4 w-4" />
            Save Configuration
          </button>
        </div>
      )}
    </div>
  );
}
