import type { 
  ShipmentQuote, 
  ShipmentPricingInput,
  PricingBreakdown,
  ProfitAnalysis 
} from './PricingModels';

/**
 * Quote Generator Service
 * Responsible for generating shipment quotes
 */
export class QuoteGenerator {
  /**
   * Generate a complete shipment quote
   */
  generateQuote(
    input: ShipmentPricingInput,
    breakdown: PricingBreakdown,
    profitAnalysis: ProfitAnalysis
  ): ShipmentQuote {
    const quoteId = this.generateQuoteId();
    const taxes = this.calculateTaxes(breakdown.total);
    const total = breakdown.total + taxes;
    
    return {
      quoteId,
      origin: input.originCity,
      destination: input.destinationCity,
      shipmentSummary: this.generateShipmentSummary(input),
      estimatedCost: breakdown.total,
      taxes,
      total,
      validity: 'Valid for 30 days',
      preparedBy: 'FleetX Admin',
      preparedAt: new Date(),
      breakdown,
      profitAnalysis,
    };
  }

  /**
   * Generate unique quote ID
   */
  private generateQuoteId(): string {
    const timestamp = Date.now();
    const random = Math.random().toString(36).substring(2, 8).toUpperCase();
    return `QT-${timestamp}-${random}`;
  }

  /**
   * Generate shipment summary
   */
  private generateShipmentSummary(input: ShipmentPricingInput): string {
    const { cargoType, trailerType, cargoWeight, shipmentPriority } = input;
    
    const cargoTypeLabels: Record<ShipmentPricingInput['cargoType'], string> = {
      general: 'General Cargo',
      refrigerated: 'Refrigerated',
      heavy: 'Heavy Equipment',
      hazardous: 'Hazardous Materials',
      fragile: 'Fragile Goods',
    };
    
    const trailerTypeLabels: Record<ShipmentPricingInput['trailerType'], string> = {
      dry_van: 'Dry Van',
      reefer: 'Reefer',
      flatbed: 'Flatbed',
      lowboy: 'Lowboy',
      tanker: 'Tanker',
    };
    
    return `${cargoTypeLabels[cargoType]}, ${cargoWeight} tons, ${trailerTypeLabels[trailerType]}, ${shipmentPriority} priority`;
  }

  /**
   * Calculate taxes (future-ready for different tax jurisdictions)
   */
  private calculateTaxes(subtotal: number): number {
    // Default tax rate: 8%
    const taxRate = 0.08;
    return subtotal * taxRate;
  }
}
