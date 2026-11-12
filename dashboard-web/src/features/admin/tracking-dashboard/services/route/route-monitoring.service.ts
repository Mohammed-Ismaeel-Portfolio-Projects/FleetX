/**
 * Route Monitoring Service
 * 
 * Business logic for route monitoring.
 * Now consumes backend Route API instead of generating routes locally.
 * No geometry calculation, interpolation, or Haversine distance in the frontend.
 */

import type { RouteData } from './route-provider.interface';
import { routeApiService, type RouteApiResponse } from './route-api.service';
import { adaptRouteData } from './route-data.adapter';

export type RouteMonitoringStatus =
  | 'ON_ROUTE'
  | 'DEVIATED'
  | 'REROUTING'
  | 'ARRIVED'
  | 'OFFLINE';

export interface RouteMonitoringData {
  shipmentNumber: string;
  originCity: string;
  destinationCity: string;
  assignedTruck?: string;
  driverName?: string;
  currentPosition: {
    latitude: number;
    longitude: number;
  };
  currentSpeed: number;
  averageSpeed: number;
  routeData: RouteData | null;
  progressPercent: number;
  eta: string;
  isLoading: boolean;
  error: string | null;
  /** Sprint 5.3 — enterprise monitoring fields */
  routeStatus?: RouteMonitoringStatus;
  deviationDistanceMeters?: number | null;
  lastGpsUpdate?: string | null;
  movementStatus?: string | null;
  isRerouting?: boolean;
}

export class RouteMonitoringService {
  /**
   * Calculate route monitoring data for a vehicle
   * 
   * Now requests route from backend API instead of generating it locally.
   * 
   * @param shipmentNumber - Shipment identifier
   * @param shipmentId - Shipment ID for API request
   * @param originCity - Origin city name (for display only)
   * @param destinationCity - Destination city name (for display only)
   * @param currentPosition - Current vehicle position
   * @param currentSpeed - Current vehicle speed
   * @param averageSpeed - Average vehicle speed
   * @returns Route monitoring data
   */
  async calculateRouteMonitoring(
    shipmentNumber: string,
    shipmentId: string,
    originCity: string,
    destinationCity: string,
    currentPosition: { latitude: number; longitude: number },
    currentSpeed: number,
    averageSpeed: number
  ): Promise<RouteMonitoringData> {
    try {
      // Request route from backend API
      const apiResponse: RouteApiResponse = await routeApiService.getShipmentRoute(shipmentId);

      if (!apiResponse.success || !apiResponse.data) {
        throw new Error(apiResponse.message || 'Failed to fetch route data');
      }

      // Transform backend response to frontend RouteData format
      const routeData = adaptRouteData(apiResponse.data, currentPosition);

      // Calculate progress and ETA (simplified for Sprint 3.1)
      // Full progress calculation will be implemented in Sprint 3.2
      const progressPercent = this.calculateProgress(routeData);
      const eta = this.calculateETA(routeData, currentSpeed, averageSpeed);

      return {
        shipmentNumber,
        originCity,
        destinationCity,
        currentPosition,
        currentSpeed,
        averageSpeed,
        routeData,
        progressPercent,
        eta,
        isLoading: false,
        error: null,
      };
    } catch (error) {
      console.error('Error fetching route from backend:', error);
      return {
        shipmentNumber,
        originCity,
        destinationCity,
        currentPosition,
        currentSpeed,
        averageSpeed,
        routeData: null,
        progressPercent: 0,
        eta: 'N/A',
        isLoading: false,
        error: 'Failed to fetch route from backend',
      };
    }
  }

  /**
   * Calculate progress percentage based on route data
   * 
   * Sprint 3.2: Progress is now calculated by RouteProgressService in the adapter.
   * This method simply returns the progress from the RouteData.
   */
  private calculateProgress(routeData: RouteData): number {
    if (routeData.totalDistance === 0) return 0;
    // Progress is calculated by RouteProgressService in route-data.adapter
    return Math.round((routeData.completedDistance / routeData.totalDistance) * 100);
  }

  /**
   * Calculate ETA based on remaining distance and speed
   * 
   * Note: Simplified for Sprint 3.1.
   * Uses backend duration if available, otherwise calculates from distance and speed.
   */
  private calculateETA(routeData: RouteData, currentSpeed: number, averageSpeed: number): string {
    if (routeData.remainingDistance === 0) return 'Arrived';

    // Use current speed if available, otherwise use average speed
    const speed = currentSpeed > 0 ? currentSpeed : averageSpeed;
    if (speed === 0) return 'N/A';

    const hours = routeData.remainingDistance / speed;
    const hoursInt = Math.floor(hours);
    const minutes = Math.round((hours - hoursInt) * 60);

    if (hoursInt === 0) {
      return `${minutes}m`;
    } else if (hoursInt < 24) {
      return `${hoursInt}h ${minutes}m`;
    } else {
      const days = Math.floor(hoursInt / 24);
      const remainingHours = hoursInt % 24;
      return `${days}d ${remainingHours}h`;
    }
  }

  /**
   * Update route monitoring with new vehicle position
   * 
   * Note: For Sprint 3.1, this re-fetches the route from backend.
   * Sprint 3.2 will implement efficient position-based updates.
   */
  async updateRouteMonitoring(
    existingData: RouteMonitoringData,
    newPosition: { latitude: number; longitude: number },
    newSpeed: number
  ): Promise<RouteMonitoringData> {
    if (!existingData.shipmentNumber) {
      return existingData;
    }

    // For Sprint 3.1, re-fetch route with new position
    // Sprint 3.2 will implement efficient position-based updates without re-fetching
    return this.calculateRouteMonitoring(
      existingData.shipmentNumber,
      '', // shipmentId not needed for update (will be extracted from context in Sprint 3.2)
      existingData.originCity,
      existingData.destinationCity,
      newPosition,
      newSpeed,
      existingData.averageSpeed
    );
  }
}

// Singleton instance
export const routeMonitoringService = new RouteMonitoringService();
