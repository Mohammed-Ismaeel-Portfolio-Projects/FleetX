import type { 
  PricingRuleResult, 
  ShipmentPricingInput, 
  PricingConfiguration 
} from './PricingModels';

/**
 * Equipment Calculator Service
 * Responsible for calculating equipment-related costs
 */
export class EquipmentCalculator {
  /**
   * Calculate equipment costs based on selected services
   */
  calculateEquipmentCost(
    input: ShipmentPricingInput,
    config: PricingConfiguration
  ): PricingRuleResult {
    const { selectedServices } = input;
    
    // Filter equipment services
    const equipmentServices = selectedServices.filter(
      service => service.enabled && service.category === 'equipment'
    );
    
    if (equipmentServices.length === 0) {
      return {
        ruleName: 'Equipment Cost',
        calculationFormula: 'No equipment selected',
        appliedValue: 0,
        cost: 0,
        category: 'equipment',
      };
    }
    
    // Calculate total equipment cost
    const totalCost = equipmentServices.reduce((sum, service) => {
      return sum + service.price;
    }, 0);
    
    // Build calculation formula
    const equipmentNames = equipmentServices
      .map(s => `${s.name} ($${s.price.toFixed(2)})`)
      .join(' + ');
    
    return {
      ruleName: 'Equipment Cost',
      calculationFormula: equipmentNames,
      appliedValue: equipmentServices.length,
      cost: totalCost,
      category: 'equipment',
    };
  }

  /**
   * Calculate border crossing charges
   */
  calculateBorderCharges(
    input: ShipmentPricingInput,
    config: PricingConfiguration
  ): PricingRuleResult {
    const { borderCrossing } = input;
    
    if (!borderCrossing) {
      return {
        ruleName: 'Border Crossing',
        calculationFormula: 'No border crossing',
        appliedValue: 0,
        cost: 0,
        category: 'border',
      };
    }
    
    // Find border crossing charge in additional charges
    const borderCharge = config.additionalCharges.find(
      charge => charge.name.toLowerCase().includes('border') && 
               charge.enabled &&
               charge.category === 'special'
    );
    
    const charge = borderCharge?.chargeType === 'percentage' 
      ? 150 // Default flat charge for now
      : (borderCharge?.price || 150);
    
    return {
      ruleName: 'Border Crossing',
      calculationFormula: 'International border crossing fee',
      appliedValue: 1,
      cost: charge,
      category: 'border',
    };
  }

  /**
   * Calculate insurance charges
   */
  calculateInsuranceCharges(
    input: ShipmentPricingInput,
    config: PricingConfiguration,
    baseFreightCost: number
  ): PricingRuleResult {
    const { insuranceRequired } = input;
    
    if (!insuranceRequired) {
      return {
        ruleName: 'Insurance',
        calculationFormula: 'Insurance not required',
        appliedValue: 0,
        cost: 0,
        category: 'insurance',
      };
    }
    
    // Find insurance charge in additional charges
    const insuranceCharge = config.additionalCharges.find(
      charge => charge.name.toLowerCase().includes('insurance') && 
               charge.enabled &&
               charge.category === 'insurance'
    );
    
    let cost = 0;
    let formula = '';
    
    if (insuranceCharge) {
      if (insuranceCharge.chargeType === 'percentage') {
        cost = baseFreightCost * (insuranceCharge.price / 100);
        formula = `${insuranceCharge.price}% of freight ($${baseFreightCost.toFixed(2)})`;
      } else {
        cost = insuranceCharge.price;
        formula = `Flat insurance fee`;
      }
    } else {
      // Default: 2% of freight
      cost = baseFreightCost * 0.02;
      formula = '2% of freight (default)';
    }
    
    return {
      ruleName: 'Insurance',
      calculationFormula: formula,
      appliedValue: insuranceCharge?.price || 2,
      cost,
      category: 'insurance',
    };
  }

  /**
   * Calculate extra charges (hazmat, priority, etc.)
   */
  calculateExtraCharges(
    input: ShipmentPricingInput,
    config: PricingConfiguration,
    baseFreightCost: number
  ): PricingRuleResult {
    const { hazmat, shipmentPriority, cargoType, trailerType } = input;
    
    const charges: string[] = [];
    let totalCost = 0;
    
    // Hazmat surcharge
    if (hazmat) {
      const hazmatCharge = config.additionalCharges.find(
        charge => charge.name.toLowerCase().includes('hazmat') && 
                 charge.enabled &&
                 charge.category === 'special'
      );
      
      if (hazmatCharge) {
        const cost = hazmatCharge.chargeType === 'percentage'
          ? baseFreightCost * (hazmatCharge.price / 100)
          : hazmatCharge.price;
        totalCost += cost;
        charges.push(`Hazmat: $${cost.toFixed(2)}`);
      } else {
        // Default: 25% of freight
        const cost = baseFreightCost * 0.25;
        totalCost += cost;
        charges.push(`Hazmat: $${cost.toFixed(2)}`);
      }
    }
    
    // Priority surcharge
    if (shipmentPriority !== 'standard') {
      const priorityMultiplier = {
        express: 1.1,
        urgent: 1.2,
        emergency: 1.3,
      }[shipmentPriority] || 1;
      
      const priorityCost = baseFreightCost * (priorityMultiplier - 1);
      totalCost += priorityCost;
      charges.push(`${shipmentPriority} priority: $${priorityCost.toFixed(2)}`);
    }
    
    // Heavy cargo with reefer surcharge
    if (cargoType === 'heavy' && trailerType === 'reefer') {
      const surcharge = baseFreightCost * 0.06;
      totalCost += surcharge;
      charges.push(`Heavy reefer surcharge: $${surcharge.toFixed(2)}`);
    }
    
    if (charges.length === 0) {
      return {
        ruleName: 'Extra Charges',
        calculationFormula: 'No extra charges',
        appliedValue: 0,
        cost: 0,
        category: 'extra',
      };
    }
    
    return {
      ruleName: 'Extra Charges',
      calculationFormula: charges.join(' + '),
      appliedValue: charges.length,
      cost: totalCost,
      category: 'extra',
    };
  }
}
