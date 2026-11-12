/**
 * FleetX Shipments Module
 * 
 * This is the first official business module following the FleetX Module Template.
 * 
 * Module Structure:
 * 
 * ```
 * src/modules/shipments/
 * ├── components/      # Shipment-specific UI components
 * ├── pages/          # Route pages
 * ├── hooks/          # Shipment-specific React hooks
 * ├── services/       # Business logic & API integration
 * ├── types/          # TypeScript definitions
 * ├── schemas/        # Zod validation schemas
 * ├── constants/      # Shipment constants
 * └── index.ts        # Public API exports
 * ```
 * 
 * Module Export Guidelines:
 * 
 * 1. Export only what needs to be public
 * 2. Keep internal implementation private
 * 3. Use barrel exports (index.ts) for clean imports
 * 4. Export types separately from implementations
 * 
 * Example index.ts (when implemented):
 * 
 * ```typescript
 * // Public components
 * export { ShipmentCard, ShipmentList } from './components';
 * 
 * // Public pages
 * export { ShipmentsPage, ShipmentDetailsPage } from './pages';
 * 
 * // Public hooks
 * export { useShipments, useShipment, useShipmentFilters } from './hooks';
 * 
 * // Public services
 * export { shipmentsService } from './services';
 * 
 * // Public types
 * export type { Shipment, ShipmentFilters, ShipmentStatus } from './types';
 * 
 * // Public schemas
 * export { createShipmentSchema, updateShipmentSchema } from './schemas';
 * 
 * // Public constants
 * export { ShipmentStatus, Priority, CargoType } from './constants';
 * ```
 * 
 * This module serves as the reference implementation for all future FleetX modules.
 */
