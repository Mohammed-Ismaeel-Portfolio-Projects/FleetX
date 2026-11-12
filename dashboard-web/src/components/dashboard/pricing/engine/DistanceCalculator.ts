import type { 
  PricingRuleResult, 
  ShipmentPricingInput, 
  PricingConfiguration,
  CargoType,
  TrailerType 
} from './PricingModels';

/**
 * Distance Calculator Service
 * Responsible for calculating distance-based freight costs
 */
export class DistanceCalculator {
  /**
   * Calculate base freight cost based on distance, cargo type, and trailer type
   */
  calculateBaseFreight(
    input: ShipmentPricingInput,
    config: PricingConfiguration
  ): PricingRuleResult {
    const { distance, cargoType, trailerType } = input;
    
    // Find applicable base rate
    const baseRate = this.findBaseRate(cargoType, trailerType, config.baseRates);
    
    const cost = distance * baseRate;
    
    return {
      ruleName: 'Base Freight',
      calculationFormula: `${distance} km × $${baseRate.toFixed(2)}/km`,
      appliedValue: baseRate,
      cost,
      category: 'base',
    };
  }

  /**
   * Calculate distance cost using tiered pricing
   */
  calculateDistanceCost(
    input: ShipmentPricingInput,
    config: PricingConfiguration
  ): PricingRuleResult {
    const { distance } = input;
    
    // Find applicable distance tier
    const tier = this.findDistanceTier(distance, config.distanceTiers);
    
    const cost = distance * tier.pricePerKm;
    
    return {
      ruleName: 'Distance Cost',
      calculationFormula: `${distance} km × $${tier.pricePerKm.toFixed(2)}/km (${tier.minDistance}-${tier.maxDistance || '∞'} km tier)`,
      appliedValue: tier.pricePerKm,
      cost,
      category: 'distance',
    };
  }

  /**
   * Find applicable base rate for cargo type and trailer type
   */
  private findBaseRate(
    cargoType: CargoType,
    trailerType: TrailerType,
    baseRates: PricingConfiguration['baseRates']
  ): number {
    // Try to find exact match
    const exactMatch = baseRates.find(
      rate => rate.cargoType === cargoType && 
              rate.trailerType === trailerType && 
              rate.status === 'active'
    );
    
    if (exactMatch) {
      return exactMatch.baseRate;
    }
    
    // Fallback to cargo type only
    const cargoMatch = baseRates.find(
      rate => rate.cargoType === cargoType && rate.status === 'active'
    );
    
    if (cargoMatch) {
      return cargoMatch.baseRate;
    }
    
    // Default fallback rates
    const defaultRates: Record<CargoType, number> = {
      general: 1.45,
      refrigerated: 1.95,
      heavy: 2.85,
      hazardous: 2.50,
      fragile: 1.75,
    };
    
    return defaultRates[cargoType] || 1.45;
  }

  /**
   * Find applicable distance tier
   */
  private findDistanceTier(
    distance: number,
    tiers: PricingConfiguration['distanceTiers']
  ): PricingConfiguration['distanceTiers'][0] {
    const activeTier = tiers.find(
      tier => tier.status === 'active' &&
              distance >= tier.minDistance &&
              (tier.maxDistance === null || distance <= tier.maxDistance)
    );
    
    if (activeTier) {
      return activeTier;
    }
    
    // Default tier pricing
    if (distance <= 100) return { minDistance: 0, maxDistance: 100, pricePerKm: 1.20, status: 'active' };
    if (distance <= 300) return { minDistance: 101, maxDistance: 300, pricePerKm: 0.95, status: 'active' };
    if (distance <= 700) return { minDistance: 301, maxDistance: 700, pricePerKm: 0.80, status: 'active' };
    return { minDistance: 701, maxDistance: null, pricePerKm: 0.65, status: 'active' };
  }
}
