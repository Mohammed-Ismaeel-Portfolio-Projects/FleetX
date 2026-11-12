/**
 * Core API Module
 * 
 * Central exports for the API layer.
 */

// Client
export { default as axiosClient } from './client/axios';
export { default as axiosClientWithInterceptors } from './client/interceptors';

// Helpers
export { buildRequest, validateRequest } from './client/request';
export { extractData, handleError } from './client/response';
export { cleanParams } from './client/utils';
