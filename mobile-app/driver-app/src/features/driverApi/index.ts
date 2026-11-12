/**
 * D1 — Driver API boundary.
 *
 * Screens must import from `@/features/driverApi` (or submodules), not Ops/Client APIs.
 * Work / assignment / execution contracts call real `/api/v1/driver/*` endpoints.
 */

export * as driverAuthApi from './auth';
export * as driverIdentityApi from './identity';
export * as driverWorkApi from './work';
export * as driverMessagingApi from './messaging';
export * as driverAccountApi from './account';
export * as driverNotificationsApi from './notifications';
export * as driverHistoryApi from './history';
export * as driverDevicesApi from './devices';
export * as driverAvailabilityApi from './availability';
export * as driverPerformanceApi from './performance';
