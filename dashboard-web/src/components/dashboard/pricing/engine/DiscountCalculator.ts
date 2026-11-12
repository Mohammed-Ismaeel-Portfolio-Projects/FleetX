import type { 
  PricingRuleResult, 
  ShipmentPricingInput, 
  PricingConfiguration 
} from './PricingModels';

/**
 * Discount Calculator Service
 * Responsible for calculating customer discounts
 */
export class DiscountCalculator {
  /**
   * Calculate discount based on customer type and policies
   */
  calculateDiscount(
    input: ShipmentPricingInput,
    config: PricingConfiguration,
    subtotal: number
  ): PricingRuleResult {
    const { customerType } = input;
    
    // Find applicable discount policy
    const discountPolicy = config.discountPolicies.find(
      policy => policy.customerType === customerType && 
               policy.enabled
    );
    
    if (!discountPolicy) {
      // Apply default discounts based on customer type
      const defaultDiscounts: Record<ShipmentPricingInput['customerType'], number> = {
        standard: 0,
        corporate: 0.05,
        vip: 0.10,
        long_term: 0.08,
      };
      
      const discountRate = defaultDiscounts[customerType] || 0;
      const discountAmount = subtotal * discountRate;
      
      if (discountRate === 0) {
        return {
          ruleName: 'Discount',
          calculationFormula: 'No discount applicable',
          appliedValue: 0,
          cost: 0,
          category: 'discount',
        };
      }
      
      return {
        ruleName: 'Discount',
        calculationFormula: `${customerType} customer (${(discountRate * 100).toFixed(0)}% discount)`,
        appliedValue: discountRate,
        cost: discountAmount,
        category: 'discount',
      };
    }
    
    // Calculate discount based on policy
    let discountAmount = 0;
    let formula = '';
    
    if (discountPolicy.discountType === 'percentage') {
      discountAmount = subtotal * (discountPolicy.amount / 100);
      formula = `${discountPolicy.name}: ${discountPolicy.amount}% of $${subtotal.toFixed(2)}`;
    } else {
      discountAmount = discountPolicy.amount;
      formula = `${discountPolicy.name}: $${discountPolicy.amount.toFixed(2)} flat discount`;
    }
    
    return {
      ruleName: 'Discount',
      calculationFormula: formula,
      appliedValue: discountPolicy.amount,
      cost: discountAmount,
      category: 'discount',
    };
  }
}
