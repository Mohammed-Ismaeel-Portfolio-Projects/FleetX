/**
 * Cargo Domain Types
 * 
 * Cargo-related domain model following FleetX enterprise architecture.
 * Placeholder interfaces for future implementation.
 */

// TODO: Implement Cargo interface
export interface Cargo {
  // TODO: Add cargo fields
  id: string;
  description: string;
  type: CargoType;
  category: CargoCategory;
  weightKg: number;
  volumeM3: number;
  // ... additional fields
}

// TODO: Implement CargoType enum
export type CargoType = 
  | 'building_materials'
  | 'industrial_equipment'
  | 'food'
  | 'fresh_produce'
  | 'vehicles'
  | 'commercial_goods'
  | 'heavy_cargo';

// TODO: Implement CargoCategory enum
export type CargoCategory = 
  | 'dry'
  | 'refrigerated'
  | 'liquid'
  | 'hazardous'
  | 'oversized';
