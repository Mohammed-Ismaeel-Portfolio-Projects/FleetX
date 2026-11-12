/**
 * Route Data Adapter
 *
 * Transforms backend Route API response into frontend RouteData format.
 * Frontend does NOT generate routes — it only decodes/adapts geometry
 * and splits progress using RouteProgressService.
 */

import type { RoutePoint, RouteData } from './route-provider.interface';
import type { RouteDataResponse } from './route-api.service';
import { routeProgressService } from './route-progress.service';

function extractCoordinates(backendResponse: RouteDataResponse): RoutePoint[] {
  const coords = backendResponse.geometry?.coordinates;

  if (!Array.isArray(coords) || coords.length === 0) {
    return [
      {
        latitude: backendResponse.origin.latitude,
        longitude: backendResponse.origin.longitude,
      },
      {
        latitude: backendResponse.destination.latitude,
        longitude: backendResponse.destination.longitude,
      },
    ];
  }

  return coords
    .map((coord) => ({
      latitude: Number(coord.latitude),
      longitude: Number(coord.longitude),
    }))
    .filter(
      (point) =>
        Number.isFinite(point.latitude) &&
        Number.isFinite(point.longitude) &&
        Math.abs(point.latitude) <= 90 &&
        Math.abs(point.longitude) <= 180
    );
}

/**
 * Transform backend route response to frontend RouteData format
 */
export function adaptRouteData(
  backendResponse: RouteDataResponse,
  currentPosition: { latitude: number; longitude: number }
): RouteData {
  const origin: RoutePoint = {
    latitude: backendResponse.origin.latitude,
    longitude: backendResponse.origin.longitude,
  };

  const destination: RoutePoint = {
    latitude: backendResponse.destination.latitude,
    longitude: backendResponse.destination.longitude,
  };

  const allPoints = extractCoordinates(backendResponse);
  const totalDistance = Number(backendResponse.distance?.kilometers ?? 0);

  const progressResult = routeProgressService.calculateProgress(
    allPoints,
    currentPosition,
    totalDistance
  );

  return {
    origin,
    destination,
    completedSegment: progressResult.completedSegment,
    remainingSegment: progressResult.remainingSegment,
    totalDistance,
    completedDistance: progressResult.completedDistance,
    remainingDistance: progressResult.remainingDistance,
  };
}

/**
 * Recalculate progress segments from an existing RouteData + new vehicle position
 * without re-fetching the backend route.
 */
export function updateRouteProgress(
  routeData: RouteData,
  currentPosition: { latitude: number; longitude: number }
): RouteData {
  const allPoints = [
    ...routeData.completedSegment.points.slice(0, -1),
    ...routeData.remainingSegment.points,
  ];

  // Prefer remaining+completed join; fall back to origin→destination if thin
  const points =
    allPoints.length >= 2
      ? allPoints
      : [routeData.origin, routeData.destination];

  const progressResult = routeProgressService.calculateProgress(
    points,
    currentPosition,
    routeData.totalDistance
  );

  return {
    ...routeData,
    completedSegment: progressResult.completedSegment,
    remainingSegment: progressResult.remainingSegment,
    completedDistance: progressResult.completedDistance,
    remainingDistance: progressResult.remainingDistance,
  };
}

export function calculateDistance(point1: RoutePoint, point2: RoutePoint): number {
  const R = 6371;
  const dLat = ((point2.latitude - point1.latitude) * Math.PI) / 180;
  const dLon = ((point2.longitude - point1.longitude) * Math.PI) / 180;
  const a =
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos((point1.latitude * Math.PI) / 180) *
      Math.cos((point2.latitude * Math.PI) / 180) *
      Math.sin(dLon / 2) *
      Math.sin(dLon / 2);
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  return R * c;
}
