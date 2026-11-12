/**
 * Document Domain Types
 * 
 * Document-related domain model following FleetX enterprise architecture.
 * Placeholder interfaces for future implementation.
 */

// TODO: Implement ShipmentDocument interface
export interface ShipmentDocument {
  // TODO: Add document fields
  id: string;
  shipmentId: string;
  type: DocumentType;
  fileName: string;
  // ... additional fields
}

// TODO: Implement DocumentType enum
export type DocumentType = 
  | 'bill_of_lading'
  | 'proof_of_delivery'
  | 'commercial_invoice'
  | 'packing_list'
  | 'dangerous_goods_declaration'
  | 'certificate_of_origin'
  | 'insurance_certificate'
  | 'customs_declaration'
  | 'weight_certificate'
  | 'temperature_log';
