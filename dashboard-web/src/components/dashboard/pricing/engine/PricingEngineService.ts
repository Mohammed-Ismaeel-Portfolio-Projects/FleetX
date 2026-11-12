import type { 
  ShipmentPricingInput, 
  ShipmentPricingResult,
  PricingBreakdown,
  PricingSummary,
  PricingConfiguration,
  PricingRecommendation,
  ProfitAnalysis 
} from './PricingModels';
import { DistanceCalculator } from './DistanceCalculator';
import { WaitingCalculator } from './WaitingCalculator';
import { ServiceCalculator } from './ServiceCalculator';
import { EquipmentCalculator } from './EquipmentCalculator';
import { DiscountCalculator } from './DiscountCalculator';
import { ProfitCalculator } from './ProfitCalculator';
import { QuoteGenerator } from './QuoteGenerator';

/**
 * FleetX Pricing Engine Service
 * Single source of truth for all shipment pricing calculations
 */
export class PricingEngineService {
  private distanceCalculator: DistanceCalculator;
  private waitingCalculator: WaitingCalculator;
  private serviceCalculator: ServiceCalculator;
  private equipmentCalculator: EquipmentCalculator;
  private discountCalculator: DiscountCalculator;
  private profitCalculator: ProfitCalculator;
  private quoteGenerator: QuoteGenerator;

  constructor() {
    this.distanceCalculator = new DistanceCalculator();
    this.waitingCalculator = new WaitingCalculator();
    this.serviceCalculator = new ServiceCalculator();
    this.equipmentCalculator = new EquipmentCalculator();
    this.discountCalculator = new DiscountCalculator();
    this.profitCalculator = new ProfitCalculator();
    this.quoteGenerator = new QuoteGenerator();
  }

  /**
   * Calculate complete shipment pricing
   * Main entry point for pricing calculations
   */
  calculateShipmentPrice(
    input: ShipmentPricingInput,
    config?: PricingConfiguration
  ): ShipmentPricingResult {
    // Use provided config or default configuration
    const pricingConfig = config || this.getDefaultConfiguration();

    // Calculate all pricing components
    const breakdown = this.calculateBreakdown(input, pricingConfig);
    const summary = this.calculateSummary(breakdown);
    const profitAnalysis = this.profitCalculator.calculateProfit(input, breakdown);
    const quote = this.quoteGenerator.generateQuote(input, breakdown, profitAnalysis);
    const recommendations = this.generateRecommendations(input, breakdown, profitAnalysis);

    return {
      input,
      breakdown,
      summary,
      profitAnalysis,
      quote,
      recommendations,
    };
  }

  /**
   * Calculate distance cost only
   */
  calculateDistanceCost(
    input: ShipmentPricingInput,
    config?: PricingConfiguration
  ) {
    const pricingConfig = config || this.getDefaultConfiguration();
    return this.distanceCalculator.calculateBaseFreight(input, pricingConfig);
  }

  /**
   * Calculate waiting time charges only
   */
  calculateWaitingCost(
    input: ShipmentPricingInput,
    config?: PricingConfiguration
  ) {
    const pricingConfig = config || this.getDefaultConfiguration();
    return this.waitingCalculator.calculateWaitingCharges(input, pricingConfig);
  }

  /**
   * Calculate service costs only
   */
  calculateServiceCost(
    input: ShipmentPricingInput,
    config?: PricingConfiguration
  ) {
    const pricingConfig = config || this.getDefaultConfiguration();
    return this.serviceCalculator.calculateServiceCost(input, pricingConfig);
  }

  /**
   * Calculate equipment costs only
   */
  calculateEquipmentCost(
    input: ShipmentPricingInput,
    config?: PricingConfiguration
  ) {
    const pricingConfig = config || this.getDefaultConfiguration();
    return this.equipmentCalculator.calculateEquipmentCost(input, pricingConfig);
  }

  /**
   * Calculate discount only
   */
  calculateDiscount(
    input: ShipmentPricingInput,
    subtotal: number,
    config?: PricingConfiguration
  ) {
    const pricingConfig = config || this.getDefaultConfiguration();
    return this.discountCalculator.calculateDiscount(input, pricingConfig, subtotal);
  }

  /**
   * Calculate profit analysis only
   */
  calculateProfit(
    input: ShipmentPricingInput,
    breakdown: PricingBreakdown
  ) {
    return this.profitCalculator.calculateProfit(input, breakdown);
  }

  /**
   * Calculate complete pricing breakdown
   */
  private calculateBreakdown(
    input: ShipmentPricingInput,
    config: PricingConfiguration
  ): PricingBreakdown {
    // Calculate individual components
    const baseFreight = this.distanceCalculator.calculateBaseFreight(input, config);
    const distanceCost = this.distanceCalculator.calculateDistanceCost(input, config);
    const weightAdjustment = this.serviceCalculator.calculateWeightAdjustment(input, config);
    const equipmentCost = this.equipmentCalculator.calculateEquipmentCost(input, config);
    const loadingServices = this.serviceCalculator.calculateServiceCost(input, config);
    const waitingCharges = this.waitingCalculator.calculateWaitingCharges(input, config);
    const borderCharges = this.equipmentCalculator.calculateBorderCharges(input, config);
    const insuranceCharges = this.equipmentCalculator.calculateInsuranceCharges(input, config, baseFreight.cost);
    const extraCharges = this.equipmentCalculator.calculateExtraCharges(input, config, baseFreight.cost);

    // Calculate subtotal before discount
    const subtotal = 
      baseFreight.cost +
      distanceCost.cost +
      weightAdjustment.cost +
      equipmentCost.cost +
      loadingServices.cost +
      waitingCharges.cost +
      borderCharges.cost +
      insuranceCharges.cost +
      extraCharges.cost;

    // Calculate discount
    const discount = this.discountCalculator.calculateDiscount(input, config, subtotal);

    // Calculate total
    const total = subtotal - discount.cost;

    return {
      baseFreight,
      distanceCost,
      weightAdjustment,
      equipmentCost,
      loadingServices,
      waitingCharges,
      borderCharges,
      insuranceCharges,
      extraCharges,
      discount,
      subtotal,
      total,
    };
  }

  /**
   * Calculate pricing summary
   */
  private calculateSummary(breakdown: PricingBreakdown): PricingSummary {
    const taxes = breakdown.total * 0.08; // 8% tax rate

    return {
      subtotal: breakdown.subtotal,
      discount: breakdown.discount.cost,
      taxes,
      total: breakdown.total + taxes,
      currency: 'USD',
    };
  }

  /**
   * Generate AI pricing recommendations
   * (Mock implementation - ready for AI integration)
   */
  private generateRecommendations(
    input: ShipmentPricingInput,
    breakdown: PricingBreakdown,
    profitAnalysis: ProfitAnalysis
  ): PricingRecommendation[] {
    const recommendations: PricingRecommendation[] = [];

    // Low profit margin warning
    if (profitAnalysis.profitMargin < 15) {
      recommendations.push({
        id: '1',
        type: 'warning',
        message: 'Current price is below market average. Increase by 4%.',
        action: 'Adjust base rate or add surcharges',
        impact: 'Profit margin will increase to ~19%',
      });
    }

    // Heavy cargo with reefer suggestion
    if (input.cargoWeight > 20 && input.trailerType === 'reefer') {
      recommendations.push({
        id: '2',
        type: 'suggestion',
        message: 'Heavy cargo with refrigerated trailer. Suggested surcharge: +6%.',
        action: 'Apply heavy cargo surcharge',
        impact: `Additional revenue: $${(breakdown.total * 0.06).toFixed(2)}`,
      });
    }

    // High waiting charges warning
    if (breakdown.waitingCharges.cost > 100) {
      recommendations.push({
        id: '3',
        type: 'warning',
        message: 'High waiting charges detected. Recommend increasing loading fee.',
        action: 'Update waiting time policy',
        impact: 'Reduce customer disputes',
      });
    }

    // No issues
    if (recommendations.length === 0) {
      recommendations.push({
        id: '4',
        type: 'info',
        message: 'Pricing is optimized based on current parameters.',
        action: 'No changes recommended',
      });
    }

    return recommendations;
  }

  /**
   * Generate quote
   */
  generateQuote(
    input: ShipmentPricingInput,
    breakdown: PricingBreakdown,
    profitAnalysis: ProfitAnalysis
  ) {
    return this.quoteGenerator.generateQuote(input, breakdown, profitAnalysis);
  }

  /**
   * Get default pricing configuration
   * In production, this would be loaded from backend
   */
  private getDefaultConfiguration(): PricingConfiguration {
    return {
      baseRates: [
        { cargoType: 'general', trailerType: 'dry_van', baseRate: 1.45, status: 'active' },
        { cargoType: 'refrigerated', trailerType: 'reefer', baseRate: 1.95, status: 'active' },
        { cargoType: 'heavy', trailerType: 'lowboy', baseRate: 2.85, status: 'active' },
      ],
      distanceTiers: [
        { minDistance: 0, maxDistance: 100, pricePerKm: 1.20, status: 'active' },
        { minDistance: 101, maxDistance: 300, pricePerKm: 0.95, status: 'active' },
        { minDistance: 301, maxDistance: 700, pricePerKm: 0.80, status: 'active' },
        { minDistance: 701, maxDistance: null, pricePerKm: 0.65, status: 'active' },
      ],
      waitingTimePolicy: {
        loadingIncludedTime: 30,
        unloadingIncludedTime: 30,
        extra15MinPrice: 15,
        extraHourPrice: 45,
        maxWaitingCharge: 200,
        enabled: true,
      },
      additionalCharges: [
        { id: '1', name: 'Border Crossing', price: 150, chargeType: 'flat', enabled: true, description: 'International border crossing fee', category: 'special' },
        { id: '2', name: 'Hazmat Shipment', price: 25, chargeType: 'percentage', enabled: true, description: 'Hazardous materials surcharge', category: 'special' },
        { id: '3', name: 'Cargo Insurance', price: 2, chargeType: 'percentage', enabled: true, description: 'Insurance coverage', category: 'insurance' },
      ],
      equipmentPricing: [],
      driverCharges: [],
      discountPolicies: [
        { id: '1', name: 'Corporate Discount', customerType: 'corporate', discountType: 'percentage', amount: 5, conditions: 'Corporate customers', enabled: true },
        { id: '2', name: 'VIP Discount', customerType: 'vip', discountType: 'percentage', amount: 10, conditions: 'VIP customers', enabled: true },
      ],
    };
  }
}

// Export singleton instance
export const pricingEngineService = new PricingEngineService();
