import { useState, useEffect } from 'react';
import { RefreshCw, FileText, Download } from 'lucide-react';
import ShipmentForm from './ShipmentForm';
import OptionalServices from './OptionalServices';
import WaitingTime from './WaitingTime';
import CostBreakdown from './CostBreakdown';
import ProfitEstimator from './ProfitEstimator';
import AIRecommendation from './AIRecommendation';
import QuotePreview from './QuotePreview';
import { pricingEngineService } from './engine/PricingEngineService';
import type { ShipmentPricingInput, OptionalService } from './engine/PricingModels';

export default function ShipmentSimulator() {
  // Shipment Form State
  const [shipmentData, setShipmentData] = useState({
    originCity: 'New York',
    destinationCity: 'Los Angeles',
    distance: 4500,
    cargoType: 'general',
    trailerType: 'dry_van',
    cargoWeight: 15,
    volume: 50,
    shipmentPriority: 'standard',
    hazmat: false,
    borderCrossing: false,
    insuranceRequired: true,
    customerType: 'corporate',
  });

  // Optional Services State
  const [optionalServices, setOptionalServices] = useState<OptionalService[]>([
    { id: '1', name: 'Loading Crew', icon: 'Package', price: 250, pricingType: 'flat', enabled: true, category: 'loading' },
    { id: '2', name: 'Unloading Crew', icon: 'Truck', price: 250, pricingType: 'flat', enabled: true, category: 'unloading' },
    { id: '3', name: 'Forklift', icon: 'Wrench', price: 75, pricingType: 'hourly', enabled: false, category: 'equipment' },
    { id: '4', name: 'Crane', icon: 'Wrench', price: 200, pricingType: 'hourly', enabled: false, category: 'equipment' },
    { id: '5', name: 'Tail Lift', icon: 'Wrench', price: 150, pricingType: 'flat', enabled: false, category: 'equipment' },
    { id: '6', name: 'Temperature Monitoring', icon: 'Thermometer', price: 25, pricingType: 'daily', enabled: false, category: 'equipment' },
    { id: '7', name: 'Escort Vehicle', icon: 'Car', price: 300, pricingType: 'daily', enabled: false, category: 'equipment' },
    { id: '8', name: 'GPS Seal', icon: 'MapPin', price: 15, pricingType: 'flat', enabled: true, category: 'equipment' },
  ]);

  // Waiting Time State
  const [waitingTimeData, setWaitingTimeData] = useState({
    loadingWaitingTime: 45,
    unloadingWaitingTime: 30,
    trafficDelay: 0,
    customerDelay: 0,
  });

  // Calculated State
  const [costBreakdown, setCostBreakdown] = useState<any[]>([]);
  const [totalCost, setTotalCost] = useState(0);
  const [profitData, setProfitData] = useState({
    estimatedRevenue: 0,
    estimatedOperatingCost: 0,
    estimatedProfit: 0,
    profitMargin: 0,
    fuelCost: 0,
    driverCost: 0,
    equipmentCost: 0,
  });
  const [recommendations, setRecommendations] = useState<any[]>([]);
  const [quoteData, setQuoteData] = useState({
    origin: '',
    destination: '',
    shipmentSummary: '',
    estimatedCost: 0,
    taxes: 0,
    total: 0,
    validity: '',
    preparedBy: 'FleetX Admin',
  });

  // Calculate pricing using PricingEngineService
  useEffect(() => {
    calculatePricing();
  }, [shipmentData, optionalServices, waitingTimeData]);

  const calculatePricing = () => {
    // Build pricing input from form state
    const pricingInput: ShipmentPricingInput = {
      originCity: shipmentData.originCity,
      destinationCity: shipmentData.destinationCity,
      distance: shipmentData.distance,
      cargoType: shipmentData.cargoType as any,
      trailerType: shipmentData.trailerType as any,
      cargoWeight: shipmentData.cargoWeight,
      volume: shipmentData.volume,
      shipmentPriority: shipmentData.shipmentPriority as any,
      customerType: shipmentData.customerType as any,
      hazmat: shipmentData.hazmat,
      borderCrossing: shipmentData.borderCrossing,
      insuranceRequired: shipmentData.insuranceRequired,
      selectedServices: optionalServices,
      waitingTime: waitingTimeData,
    };

    // Delegate all calculations to PricingEngineService
    const result = pricingEngineService.calculateShipmentPrice(pricingInput);

    // Transform breakdown to UI format
    const breakdown = [
      { id: '1', name: result.breakdown.baseFreight.ruleName, calculation: result.breakdown.baseFreight.calculationFormula, amount: result.breakdown.baseFreight.cost, category: result.breakdown.baseFreight.category },
      { id: '2', name: result.breakdown.distanceCost.ruleName, calculation: result.breakdown.distanceCost.calculationFormula, amount: result.breakdown.distanceCost.cost, category: result.breakdown.distanceCost.category },
      { id: '3', name: result.breakdown.weightAdjustment.ruleName, calculation: result.breakdown.weightAdjustment.calculationFormula, amount: result.breakdown.weightAdjustment.cost, category: result.breakdown.weightAdjustment.category },
      { id: '4', name: result.breakdown.equipmentCost.ruleName, calculation: result.breakdown.equipmentCost.calculationFormula, amount: result.breakdown.equipmentCost.cost, category: result.breakdown.equipmentCost.category },
      { id: '5', name: result.breakdown.loadingServices.ruleName, calculation: result.breakdown.loadingServices.calculationFormula, amount: result.breakdown.loadingServices.cost, category: result.breakdown.loadingServices.category },
      { id: '6', name: result.breakdown.waitingCharges.ruleName, calculation: result.breakdown.waitingCharges.calculationFormula, amount: result.breakdown.waitingCharges.cost, category: result.breakdown.waitingCharges.category },
      { id: '7', name: result.breakdown.borderCharges.ruleName, calculation: result.breakdown.borderCharges.calculationFormula, amount: result.breakdown.borderCharges.cost, category: result.breakdown.borderCharges.category },
      { id: '8', name: result.breakdown.insuranceCharges.ruleName, calculation: result.breakdown.insuranceCharges.calculationFormula, amount: result.breakdown.insuranceCharges.cost, category: result.breakdown.insuranceCharges.category },
      { id: '9', name: result.breakdown.extraCharges.ruleName, calculation: result.breakdown.extraCharges.calculationFormula, amount: result.breakdown.extraCharges.cost, category: result.breakdown.extraCharges.category },
      { id: '10', name: result.breakdown.discount.ruleName, calculation: result.breakdown.discount.calculationFormula, amount: result.breakdown.discount.cost, category: result.breakdown.discount.category },
    ];

    setCostBreakdown(breakdown);
    setTotalCost(result.breakdown.total);
    setProfitData({
      estimatedRevenue: result.profitAnalysis.estimatedRevenue,
      estimatedOperatingCost: result.profitAnalysis.estimatedOperatingCost,
      estimatedProfit: result.profitAnalysis.estimatedProfit,
      profitMargin: result.profitAnalysis.profitMargin,
      fuelCost: result.profitAnalysis.fuelCost,
      driverCost: result.profitAnalysis.driverCost,
      equipmentCost: result.profitAnalysis.equipmentCost,
    });
    setRecommendations(result.recommendations);
    setQuoteData({
      origin: result.quote.origin,
      destination: result.quote.destination,
      shipmentSummary: result.quote.shipmentSummary,
      estimatedCost: result.quote.estimatedCost,
      taxes: result.quote.taxes,
      total: result.quote.total,
      validity: result.quote.validity,
      preparedBy: result.quote.preparedBy,
    });
  };

  const handleToggleService = (id: string) => {
    setOptionalServices(prev => 
      prev.map(s => s.id === id ? { ...s, enabled: !s.enabled } : s)
    );
  };

  const handleReset = () => {
    setShipmentData({
      originCity: 'New York',
      destinationCity: 'Los Angeles',
      distance: 4500,
      cargoType: 'general',
      trailerType: 'dry_van',
      cargoWeight: 15,
      volume: 50,
      shipmentPriority: 'standard',
      hazmat: false,
      borderCrossing: false,
      insuranceRequired: true,
      customerType: 'corporate',
    });
    setOptionalServices(prev => prev.map(s => ({ ...s, enabled: s.id === '1' || s.id === '2' || s.id === '8' })));
    setWaitingTimeData({
      loadingWaitingTime: 45,
      unloadingWaitingTime: 30,
      trafficDelay: 0,
      customerDelay: 0,
    });
  };

  const handleGenerateQuote = () => {
    // In production, this would generate and download a PDF quote
    console.log('Generating quote...', quoteData);
  };

  const handleExportPDF = () => {
    // In production, this would export the quote as PDF
    console.log('Exporting PDF...');
  };

  return (
    <div className="flex flex-col gap-6 w-full">
      {/* Section Header */}
      <div className="flex flex-col gap-1.5">
        <h2 className="text-lg font-semibold tracking-tight text-white font-sans">
          Shipment Pricing Simulator
        </h2>
        <p className="text-slate-500 text-xs font-normal font-sans">
          Test shipment pricing rules, estimate costs, and generate quotations without affecting production pricing.
        </p>
      </div>

      {/* Module 1: Shipment Information */}
      <ShipmentForm data={shipmentData} onChange={setShipmentData} />

      {/* Module 2: Optional Services */}
      <OptionalServices services={optionalServices} onToggleService={handleToggleService} />

      {/* Module 3: Waiting Time */}
      <WaitingTime data={waitingTimeData} onChange={setWaitingTimeData} />

      {/* Modules 4-7: Results Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Module 4: Live Cost Breakdown */}
        <CostBreakdown costs={costBreakdown} total={totalCost} />

        {/* Module 5: Profit Estimation */}
        <ProfitEstimator data={profitData} />
      </div>

      {/* Module 6: AI Recommendations */}
      <AIRecommendation recommendations={recommendations} />

      {/* Module 7: Quote Preview */}
      <QuotePreview data={quoteData} />

      {/* Module 8: Action Buttons */}
      <div className="flex flex-wrap items-center justify-end gap-3">
        <button
          type="button"
          onClick={handleReset}
          className="inline-flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-4 py-2 text-xs font-medium text-slate-300 transition-colors hover:bg-white/10"
        >
          <RefreshCw className="h-3 w-3" />
          Reset Simulation
        </button>
        <button
          type="button"
          onClick={handleExportPDF}
          className="inline-flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-4 py-2 text-xs font-medium text-slate-300 transition-colors hover:bg-white/10"
        >
          <Download className="h-3 w-3" />
          Export PDF
        </button>
        <button
          type="button"
          onClick={handleGenerateQuote}
          className="inline-flex items-center gap-2 rounded-lg border border-cyan-500/30 bg-cyan-500/10 px-4 py-2 text-xs font-medium text-cyan-400 transition-colors hover:bg-cyan-500/20"
        >
          <FileText className="h-3 w-3" />
          Generate Quote
        </button>
      </div>
    </div>
  );
}
