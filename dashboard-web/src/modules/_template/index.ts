/**
 * FleetX Module Template
 * 
 * This is the official template for all FleetX business modules.
 * 
 * Every module should follow this structure:
 * 
 * ```
 * src/modules/[module-name]/
 * ├── components/      # Module-specific UI components
 * ├── pages/          # Route pages
 * ├── hooks/          # Module-specific React hooks
 * ├── services/       # Business logic & API integration
 * ├── types/          # TypeScript definitions
 * ├── schemas/        # Zod validation schemas
 * ├── constants/      # Module constants
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
 * Example index.ts:
 * 
 * ```typescript
 * // Public components
 * export { ShipmentCard } from './components';
 * 
 * // Public pages
 * export { ShipmentsPage, ShipmentDetailsPage } from './pages';
 * 
 * // Public hooks
 * export { useShipments, useShipmentFilters } from './hooks';
 * 
 * // Public services
 * export { shipmentsService } from './services';
 * 
 * // Public types
 * export type { Shipment, ShipmentFilters } from './types';
 * 
 * // Public schemas
 * export { createShipmentSchema } from './schemas';
 * 
 * // Public constants
 * export { ShipmentStatus } from './constants';
 * ```
 * 
 * Module Naming Convention:
 * - Use kebab-case for folder names: `shipments`, `fleet-management`
 * - Use PascalCase for exports: `useShipments`, `ShipmentsPage`
 * - Be descriptive but concise
 * 
 * Module Dependencies:
 * - Use `src/core/api` for HTTP requests
 * - Use `src/core/auth` for authentication
 * - Use `src/shared/` for shared utilities
 * - Avoid circular dependencies between modules
 * 
 * This template ensures consistency across all FleetX modules.
 */
