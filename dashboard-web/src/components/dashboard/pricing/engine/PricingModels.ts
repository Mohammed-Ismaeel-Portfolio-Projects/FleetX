// ============================================================================
// FleetX Pricing Engine - Data Models and Interfaces
// ============================================================================

/**
 * Input model for shipment pricing calculation
 */
export interface ShipmentPricingInput {
  // Route Information
  originCity: string;
  destinationCity: string;
  distance: number;
  
  // Cargo Information
  cargoType: CargoType;
  trailerType: TrailerType;
  cargoWeight: number; // in tons
  volume: number; // in m³
  
  // Shipment Details
  shipmentPriority: ShipmentPriority;
  customerType: CustomerType;
  
  // Special Requirements
  hazmat: boolean;
  borderCrossing: boolean;
  insuranceRequired: boolean;
  
  // Optional Services
  selectedServices: OptionalService[];
  
  // Waiting Time (in minutes)
  waitingTime: WaitingTimeInput;
  
  // Pricing Configuration (would come from backend in production)
  pricingConfig?: PricingConfiguration;
}

/**
 * Cargo type enumeration
 */
export type CargoType = 'general' | 'refrigerated' | 'heavy' | 'hazardous' | 'fragile';

/**
 * Trailer type enumeration
 */
export type TrailerType = 'dry_van' | 'reefer' | 'flatbed' | 'lowboy' | 'tanker';

/**
 * Shipment priority enumeration
 */
export type ShipmentPriority = 'standard' | 'express' | 'urgent' | 'emergency';

/**
 * Customer type enumeration
 */
export type CustomerType = 'standard' | 'corporate' | 'vip' | 'long_term';

/**
 * Optional service model
 */
export interface OptionalService {
  id: string;
  name: string;
  icon: string; // Icon name for UI display
  pricingType: 'flat' | 'hourly' | 'daily';
  price: number;
  enabled: boolean;
  category: 'loading' | 'unloading' | 'equipment';
}

/**
 * Waiting time input
 */
export interface WaitingTimeInput {
  loadingWaitingTime: number;
  unloadingWaitingTime: number;
  trafficDelay: number;
  customerDelay: number;
}

/**
 * Pricing configuration (would be loaded from backend)
 */
export interface PricingConfiguration {
  // Base freight rates
  baseRates: BaseFreightRate[];
  
  // Distance tiers
  distanceTiers: DistanceTier[];
  
  // Waiting time policy
  waitingTimePolicy: WaitingTimePolicy;
  
  // Additional charges
  additionalCharges: AdditionalCharge[];
  
  // Equipment pricing
  equipmentPricing: EquipmentPricing[];
  
  // Driver charges
  driverCharges: DriverCharge[];
  
  // Discount policies
  discountPolicies: DiscountPolicy[];
}

/**
 * Base freight rate configuration
 */
export interface BaseFreightRate {
  cargoType: CargoType;
  trailerType: TrailerType;
  baseRate: number; // per km
  status: 'active' | 'inactive';
}

/**
 * Distance tier configuration
 */
export interface DistanceTier {
  minDistance: number;
  maxDistance: number | null;
  pricePerKm: number;
  status: 'active' | 'inactive';
}

/**
 * Waiting time policy configuration
 */
export interface WaitingTimePolicy {
  loadingIncludedTime: number; // minutes
  unloadingIncludedTime: number; // minutes
  extra15MinPrice: number;
  extraHourPrice: number;
  maxWaitingCharge: number;
  enabled: boolean;
}

/**
 * Additional charge configuration
 */
export interface AdditionalCharge {
  id: string;
  name: string;
  price: number;
  chargeType: 'flat' | 'percentage';
  enabled: boolean;
  description: string;
  category: 'time' | 'special' | 'penalty' | 'insurance';
}

/**
 * Equipment pricing configuration
 */
export interface EquipmentPricing {
  id: string;
  name: string;
  price: number;
  pricingType: 'flat' | 'daily' | 'hourly';
  status: 'active' | 'inactive';
  description: string;
}

/**
 * Driver charge configuration
 */
export interface DriverCharge {
  id: string;
  name: string;
  price: number;
  chargeType: 'flat' | 'percentage';
  status: 'active' | 'inactive';
  description: string;
}

/**
 * Discount policy configuration
 */
export interface DiscountPolicy {
  id: string;
  name: string;
  customerType: CustomerType;
  discountType: 'flat' | 'percentage';
  amount: number;
  conditions: string;
  enabled: boolean;
}

/**
 * Individual pricing rule result
 */
export interface PricingRuleResult {
  ruleName: string;
  calculationFormula: string;
  appliedValue: number;
  cost: number;
  category: PricingCategory;
}

/**
 * Pricing category enumeration
 */
export type PricingCategory = 
  | 'base' 
  | 'distance' 
  | 'weight' 
  | 'equipment' 
  | 'services' 
  | 'waiting' 
  | 'border' 
  | 'insurance' 
  | 'extra' 
  | 'discount';

/**
 * Complete pricing breakdown
 */
export interface PricingBreakdown {
  baseFreight: PricingRuleResult;
  distanceCost: PricingRuleResult;
  weightAdjustment: PricingRuleResult;
  equipmentCost: PricingRuleResult;
  loadingServices: PricingRuleResult;
  waitingCharges: PricingRuleResult;
  borderCharges: PricingRuleResult;
  insuranceCharges: PricingRuleResult;
  extraCharges: PricingRuleResult;
  discount: PricingRuleResult;
  subtotal: number;
  total: number;
}

/**
 * Pricing summary
 */
export interface PricingSummary {
  subtotal: number;
  discount: number;
  taxes: number;
  total: number;
  currency: string;
}

/**
 * Profit analysis
 */
export interface ProfitAnalysis {
  estimatedRevenue: number;
  estimatedOperatingCost: number;
  estimatedProfit: number;
  profitMargin: number;
  fuelCost: number;
  driverCost: number;
  equipmentCost: number;
  otherCosts: number;
}

/**
 * Complete shipment quote
 */
export interface ShipmentQuote {
  quoteId: string;
  origin: string;
  destination: string;
  shipmentSummary: string;
  estimatedCost: number;
  taxes: number;
  total: number;
  validity: string;
  preparedBy: string;
  preparedAt: Date;
  breakdown: PricingBreakdown;
  profitAnalysis: ProfitAnalysis;
}

/**
 * Complete shipment pricing result
 */
export interface ShipmentPricingResult {
  input: ShipmentPricingInput;
  breakdown: PricingBreakdown;
  summary: PricingSummary;
  profitAnalysis: ProfitAnalysis;
  quote: ShipmentQuote;
  recommendations: PricingRecommendation[];
}

/**
 * Pricing recommendation
 */
export interface PricingRecommendation {
  id: string;
  type: 'warning' | 'suggestion' | 'info';
  message: string;
  action?: string;
  impact?: string;
}
