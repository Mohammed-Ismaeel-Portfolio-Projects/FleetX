/**
 * Simulator Route Provider
 * 
 * This is a temporary implementation that uses the simulator data.
 * In production, this should be replaced with a real routing service (OSRM, GraphHopper, etc.)
 */

import type { RouteProvider, RoutePoint, RouteData } from './route-provider.interface';

// Simple city coordinate mapping for demo (in production, use a proper geocoding service)
const CITY_COORDINATES: Record<string, RoutePoint> = {
  'Berlin, DE': { latitude: 52.5200, longitude: 13.4050 },
  'Paris, FR': { latitude: 48.8566, longitude: 2.3522 },
  'Munich, DE': { latitude: 48.1351, longitude: 11.5820 },
  'Hamburg, DE': { latitude: 53.5511, longitude: 9.9937 },
  'Frankfurt, DE': { latitude: 50.1109, longitude: 8.6821 },
  'Amsterdam, NL': { latitude: 52.3676, longitude: 4.9041 },
  'Brussels, BE': { latitude: 50.8503, longitude: 4.3517 },
  'Zurich, CH': { latitude: 47.3769, longitude: 8.5417 },
  'Vienna, AT': { latitude: 48.2082, longitude: 16.3738 },
  'Prague, CZ': { latitude: 50.0755, longitude: 14.4378 },
};

function getCityCoords(city: string): RoutePoint {
  return CITY_COORDINATES[city] || { latitude: 50.0, longitude: 10.0 };
}

function calculateDistance(point1: RoutePoint, point2: RoutePoint): number {
  const R = 6371; // Earth's radius in kilometers
  const dLat = (point2.latitude - point1.latitude) * (Math.PI / 180);
  const dLon = (point2.longitude - point1.longitude) * (Math.PI / 180);
  const a =
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos(point1.latitude * (Math.PI / 180)) *
      Math.cos(point2.latitude * (Math.PI / 180)) *
      Math.sin(dLon / 2) *
      Math.sin(dLon / 2);
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  return R * c;
}

function interpolateRoute(origin: RoutePoint, destination: RoutePoint, numPoints: number): RoutePoint[] {
  const points: RoutePoint[] = [];
  for (let i = 0; i <= numPoints; i++) {
    const t = i / numPoints;
    points.push({
      latitude: origin.latitude + (destination.latitude - origin.latitude) * t,
      longitude: origin.longitude + (destination.longitude - origin.longitude) * t,
    });
  }
  return points;
}

function findClosestPointOnRoute(current: RoutePoint, routePoints: RoutePoint[]): number {
  let minDistance = Infinity;
  let closestIndex = 0;

  routePoints.forEach((point, index) => {
    const distance = calculateDistance(current, point);
    if (distance < minDistance) {
      minDistance = distance;
      closestIndex = index;
    }
  });

  return closestIndex;
}

export class SimulatorRouteProvider implements RouteProvider {
  async calculateRoute(
    _shipmentId: string,
    originCity: string,
    destinationCity: string,
    currentPosition: RoutePoint
  ): Promise<RouteData> {
    const origin = getCityCoords(originCity);
    const destination = getCityCoords(destinationCity);

    // Generate route points (in production, this would come from a routing service)
    const routePoints = interpolateRoute(origin, destination, 50);

    // Find where the current position is on the route
    const currentIndex = findClosestPointOnRoute(currentPosition, routePoints);

    // Split route into completed and remaining segments
    const completedPoints = routePoints.slice(0, currentIndex + 1);
    const remainingPoints = routePoints.slice(currentIndex);

    // Calculate distances
    let completedDistance = 0;
    for (let i = 0; i < completedPoints.length - 1; i++) {
      completedDistance += calculateDistance(completedPoints[i], completedPoints[i + 1]);
    }

    let remainingDistance = 0;
    for (let i = 0; i < remainingPoints.length - 1; i++) {
      remainingDistance += calculateDistance(remainingPoints[i], remainingPoints[i + 1]);
    }

    const totalDistance = completedDistance + remainingDistance;

    return {
      origin,
      destination,
      completedSegment: {
        points: completedPoints,
        distance: completedDistance,
      },
      remainingSegment: {
        points: remainingPoints,
        distance: remainingDistance,
      },
      totalDistance,
      completedDistance,
      remainingDistance,
    };
  }

  async getRouteCoordinates(
    _shipmentId: string,
    originCity: string,
    destinationCity: string
  ): Promise<RoutePoint[]> {
    const origin = getCityCoords(originCity);
    const destination = getCityCoords(destinationCity);
    return interpolateRoute(origin, destination, 50);
  }
}

// Singleton instance
export const simulatorRouteProvider = new SimulatorRouteProvider();
