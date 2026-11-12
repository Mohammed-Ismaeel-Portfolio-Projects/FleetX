import type { 
  ProfitAnalysis, 
  ShipmentPricingInput,
  PricingBreakdown 
} from './PricingModels';

/**
 * Profit Calculator Service
 * Responsible for calculating profit analysis and operating costs
 */
export class ProfitCalculator {
  /**
   * Calculate profit analysis for a shipment
   */
  calculateProfit(
    input: ShipmentPricingInput,
    breakdown: PricingBreakdown
  ): ProfitAnalysis {
    const { distance, cargoWeight } = input;
    const totalRevenue = breakdown.total;
    
    // Calculate operating costs
    const fuelCost = this.calculateFuelCost(distance);
    const driverCost = this.calculateDriverCost(distance);
    const equipmentCost = this.calculateEquipmentOperatingCost(breakdown);
    const otherCosts = this.calculateOtherCosts(totalRevenue);
    
    const estimatedOperatingCost = fuelCost + driverCost + equipmentCost + otherCosts;
    const estimatedProfit = totalRevenue - estimatedOperatingCost;
    const profitMargin = totalRevenue > 0 ? (estimatedProfit / totalRevenue) * 100 : 0;
    
    return {
      estimatedRevenue: totalRevenue,
      estimatedOperatingCost,
      estimatedProfit,
      profitMargin,
      fuelCost,
      driverCost,
      equipmentCost,
      otherCosts,
    };
  }

  /**
   * Calculate fuel cost based on distance
   */
  private calculateFuelCost(distance: number): number {
    // Average fuel consumption: 0.35 per km
    const fuelCostPerKm = 0.35;
    return distance * fuelCostPerKm;
  }

  /**
   * Calculate driver cost based on distance
   */
  private calculateDriverCost(distance: number): number {
    // Driver cost: 0.25 per km
    const driverCostPerKm = 0.25;
    return distance * driverCostPerKm;
  }

  /**
   * Calculate equipment operating cost
   */
  private calculateEquipmentOperatingCost(breakdown: PricingBreakdown): number {
    // Equipment operating cost is typically 30% of equipment service cost
    return breakdown.equipmentCost.cost * 0.3;
  }

  /**
   * Calculate other costs (maintenance, overhead, etc.)
   */
  private calculateOtherCosts(revenue: number): number {
    // Other costs are typically 10% of revenue
    return revenue * 0.10;
  }
}
