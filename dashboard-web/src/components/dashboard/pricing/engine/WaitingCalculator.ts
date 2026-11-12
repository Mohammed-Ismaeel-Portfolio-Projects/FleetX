import type { 
  PricingRuleResult, 
  ShipmentPricingInput, 
  PricingConfiguration 
} from './PricingModels';

/**
 * Waiting Time Calculator Service
 * Responsible for calculating waiting time charges
 */
export class WaitingCalculator {
  /**
   * Calculate waiting time charges based on configured policy
   */
  calculateWaitingCharges(
    input: ShipmentPricingInput,
    config: PricingConfiguration
  ): PricingRuleResult {
    const { waitingTime } = input;
    const policy = config.waitingTimePolicy;
    
    if (!policy.enabled) {
      return {
        ruleName: 'Waiting Charges',
        calculationFormula: 'Waiting time policy disabled',
        appliedValue: 0,
        cost: 0,
        category: 'waiting',
      };
    }
    
    // Calculate excess waiting time beyond included time
    const excessLoadingTime = Math.max(0, waitingTime.loadingWaitingTime - policy.loadingIncludedTime);
    const excessUnloadingTime = Math.max(0, waitingTime.unloadingWaitingTime - policy.unloadingIncludedTime);
    
    // Calculate charges
    const loadingCharges = this.calculateChargeForExcessTime(excessLoadingTime, policy);
    const unloadingCharges = this.calculateChargeForExcessTime(excessUnloadingTime, policy);
    const trafficCharges = waitingTime.trafficDelay * (policy.extra15MinPrice / 15);
    const customerCharges = waitingTime.customerDelay * (policy.extra15MinPrice / 15);
    
    const totalCharges = loadingCharges + unloadingCharges + trafficCharges + customerCharges;
    
    // Cap at maximum waiting charge
    const finalCharges = Math.min(totalCharges, policy.maxWaitingCharge);
    
    const breakdown = [
      excessLoadingTime > 0 ? `Loading: ${excessLoadingTime}min excess` : '',
      excessUnloadingTime > 0 ? `Unloading: ${excessUnloadingTime}min excess` : '',
      waitingTime.trafficDelay > 0 ? `Traffic: ${waitingTime.trafficDelay}min` : '',
      waitingTime.customerDelay > 0 ? `Customer: ${waitingTime.customerDelay}min` : '',
    ].filter(Boolean).join(' + ');
    
    return {
      ruleName: 'Waiting Charges',
      calculationFormula: breakdown || 'No excess waiting time',
      appliedValue: totalCharges,
      cost: finalCharges,
      category: 'waiting',
    };
  }

  /**
   * Calculate charge for excess time based on policy
   */
  private calculateChargeForExcessTime(excessMinutes: number, policy: PricingConfiguration['waitingTimePolicy']): number {
    if (excessMinutes <= 0) return 0;
    
    // Charge per 15-minute increment
    const increments = Math.ceil(excessMinutes / 15);
    const charge = increments * policy.extra15MinPrice;
    
    return charge;
  }
}
