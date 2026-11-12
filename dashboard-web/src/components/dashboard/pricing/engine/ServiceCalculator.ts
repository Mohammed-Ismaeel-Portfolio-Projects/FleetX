import type { 
  PricingRuleResult, 
  ShipmentPricingInput, 
  PricingConfiguration 
} from './PricingModels';

/**
 * Service Calculator Service
 * Responsible for calculating loading/unloading service costs
 */
export class ServiceCalculator {
  /**
   * Calculate loading and unloading service costs
   */
  calculateServiceCost(
    input: ShipmentPricingInput,
    config: PricingConfiguration
  ): PricingRuleResult {
    const { selectedServices } = input;
    
    // Filter enabled services
    const enabledServices = selectedServices.filter(service => service.enabled);
    
    if (enabledServices.length === 0) {
      return {
        ruleName: 'Loading Services',
        calculationFormula: 'No services selected',
        appliedValue: 0,
        cost: 0,
        category: 'services',
      };
    }
    
    // Calculate total service cost
    const totalCost = enabledServices.reduce((sum, service) => {
      return sum + service.price;
    }, 0);
    
    // Build calculation formula
    const serviceNames = enabledServices
      .map(s => `${s.name} ($${s.price.toFixed(2)})`)
      .join(' + ');
    
    return {
      ruleName: 'Loading Services',
      calculationFormula: serviceNames,
      appliedValue: enabledServices.length,
      cost: totalCost,
      category: 'services',
    };
  }

  /**
   * Calculate weight adjustment charges
   */
  calculateWeightAdjustment(
    input: ShipmentPricingInput,
    config: PricingConfiguration
  ): PricingRuleResult {
    const { cargoWeight } = input;
    
    // Weight threshold for additional charges (e.g., 20 tons)
    const weightThreshold = 20;
    const chargePerTon = 50;
    
    if (cargoWeight <= weightThreshold) {
      return {
        ruleName: 'Weight Adjustment',
        calculationFormula: `${cargoWeight} tons (within threshold)`,
        appliedValue: 0,
        cost: 0,
        category: 'weight',
      };
    }
    
    const excessWeight = cargoWeight - weightThreshold;
    const adjustmentCost = excessWeight * chargePerTon;
    
    return {
      ruleName: 'Weight Adjustment',
      calculationFormula: `${cargoWeight} tons (${excessWeight} tons excess × $${chargePerTon}/ton)`,
      appliedValue: excessWeight,
      cost: adjustmentCost,
      category: 'weight',
    };
  }
}
