/**
 * Route Provider Interface
 * 
 * This abstraction allows the route monitoring system to work with different routing providers
 * without modifying UI components. Future implementations can use OSRM, GraphHopper, OpenRouteService, etc.
 */

export interface RoutePoint {
  latitude: number;
  longitude: number;
}

export interface RouteSegment {
  points: RoutePoint[];
  distance: number; // in kilometers
}

export interface RouteData {
  origin: RoutePoint;
  destination: RoutePoint;
  completedSegment: RouteSegment;
  remainingSegment: RouteSegment;
  totalDistance: number; // in kilometers
  completedDistance: number; // in kilometers
  remainingDistance: number; // in kilometers
}

export interface RouteProvider {
  /**
   * Calculate route data based on shipment and current vehicle position
   */
  calculateRoute(
    shipmentId: string,
    originCity: string,
    destinationCity: string,
    currentPosition: RoutePoint
  ): Promise<RouteData>;

  /**
   * Get route coordinates for a shipment (full route from origin to destination)
   */
  getRouteCoordinates(
    shipmentId: string,
    originCity: string,
    destinationCity: string
  ): Promise<RoutePoint[]>;
}
