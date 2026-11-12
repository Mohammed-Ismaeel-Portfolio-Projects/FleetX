/**
 * Route Progress Service
 * 
 * Calculates vehicle progress along a route.
 * Determines nearest point on route, splits into completed/remaining segments,
 * calculates progress percentage, remaining distance, and ETA.
 * 
 * This service does NOT generate routes or calculate geometry.
 * Geometry is provided by the backend Route API.
 */

import type { RoutePoint, RouteSegment } from './route-provider.interface';

export interface RouteProgressResult {
  completedSegment: RouteSegment;
  remainingSegment: RouteSegment;
  progressPercent: number;
  completedDistance: number;
  remainingDistance: number;
  nearestPointIndex: number;
  isOffRoute: boolean;
}

export class RouteProgressService {
  // Cache for memoization
  private distanceCache = new Map<string, number>();
  private nearestPointCache = new Map<string, number>();

  /**
   * Calculate vehicle progress along a route
   * 
   * @param routePoints - Array of route points from backend
   * @param currentPosition - Current vehicle position
   * @param totalDistance - Total route distance in kilometers
   * @returns Route progress result
   */
  calculateProgress(
    routePoints: RoutePoint[],
    currentPosition: RoutePoint,
    totalDistance: number
  ): RouteProgressResult {
    if (routePoints.length < 2) {
      return this.getEmptyProgress(currentPosition);
    }

    // Find nearest point on route to current position
    const nearestPointIndex = this.findNearestPointIndex(routePoints, currentPosition);

    // Check if vehicle is significantly off route (> 5km from route)
    const isOffRoute = this.isOffRoute(routePoints, currentPosition, nearestPointIndex);

    // Split route into completed and remaining segments
    const completedPoints = routePoints.slice(0, nearestPointIndex + 1);
    const remainingPoints = routePoints.slice(nearestPointIndex);

    // Calculate distances
    const completedDistance = this.calculateSegmentDistance(completedPoints);
    const remainingDistance = this.calculateSegmentDistance(remainingPoints);

    // Calculate progress percentage
    const progressPercent = totalDistance > 0 
      ? Math.round((completedDistance / totalDistance) * 100)
      : 0;

    return {
      completedSegment: {
        points: completedPoints,
        distance: completedDistance,
      },
      remainingSegment: {
        points: remainingPoints,
        distance: remainingDistance,
      },
      progressPercent,
      completedDistance,
      remainingDistance,
      nearestPointIndex,
      isOffRoute,
    };
  }

  /**
   * Find the index of the nearest point on the route to the current position
   * 
   * Uses spatial optimization: checks every Nth point first, then refines
   * 
   * @param routePoints - Route points
   * @param currentPosition - Current vehicle position
   * @returns Index of nearest point
   */
  private findNearestPointIndex(routePoints: RoutePoint[], currentPosition: RoutePoint): number {
    const cacheKey = this.getCacheKey(routePoints, currentPosition);
    
    if (this.nearestPointCache.has(cacheKey)) {
      return this.nearestPointCache.get(cacheKey)!;
    }

    // Optimization: Check every 10th point first for long routes
    const step = Math.max(1, Math.floor(routePoints.length / 50));
    let minDistance = Infinity;
    let nearestIndex = 0;

    // Coarse search
    for (let i = 0; i < routePoints.length; i += step) {
      const distance = this.calculateDistance(routePoints[i], currentPosition);
      if (distance < minDistance) {
        minDistance = distance;
        nearestIndex = i;
      }
    }

    // Fine search around the coarse result
    const searchStart = Math.max(0, nearestIndex - step * 2);
    const searchEnd = Math.min(routePoints.length - 1, nearestIndex + step * 2);

    minDistance = Infinity;
    for (let i = searchStart; i <= searchEnd; i++) {
      const distance = this.calculateDistance(routePoints[i], currentPosition);
      if (distance < minDistance) {
        minDistance = distance;
        nearestIndex = i;
      }
    }

    this.nearestPointCache.set(cacheKey, nearestIndex);
    
    // Limit cache size
    if (this.nearestPointCache.size > 100) {
      const firstKey = this.nearestPointCache.keys().next().value;
      this.nearestPointCache.delete(firstKey);
    }

    return nearestIndex;
  }

  /**
   * Check if vehicle is significantly off route
   * 
   * @param routePoints - Route points
   * @param currentPosition - Current vehicle position
   * @param nearestPointIndex - Index of nearest point
   * @returns True if off route (> 5km)
   */
  private isOffRoute(
    routePoints: RoutePoint[],
    currentPosition: RoutePoint,
    nearestPointIndex: number
  ): boolean {
    const nearestPoint = routePoints[nearestPointIndex];
    const distance = this.calculateDistance(nearestPoint, currentPosition);
    return distance > 5; // 5km threshold
  }

  /**
   * Calculate total distance of a route segment
   * 
   * @param points - Route segment points
   * @returns Total distance in kilometers
   */
  private calculateSegmentDistance(points: RoutePoint[]): number {
    if (points.length < 2) return 0;

    const cacheKey = points.map(p => `${p.latitude},${p.longitude}`).join('|');
    if (this.distanceCache.has(cacheKey)) {
      return this.distanceCache.get(cacheKey)!;
    }

    let totalDistance = 0;
    for (let i = 0; i < points.length - 1; i++) {
      totalDistance += this.calculateDistance(points[i], points[i + 1]);
    }

    this.distanceCache.set(cacheKey, totalDistance);
    
    // Limit cache size
    if (this.distanceCache.size > 100) {
      const firstKey = this.distanceCache.keys().next().value;
      this.distanceCache.delete(firstKey);
    }

    return totalDistance;
  }

  /**
   * Calculate distance between two points using Haversine formula
   * 
   * @param point1 - First point
   * @param point2 - Second point
   * @returns Distance in kilometers
   */
  private calculateDistance(point1: RoutePoint, point2: RoutePoint): number {
    const R = 6371; // Earth's radius in kilometers
    const dLat = this.toRadians(point2.latitude - point1.latitude);
    const dLon = this.toRadians(point2.longitude - point1.longitude);
    
    const a =
      Math.sin(dLat / 2) * Math.sin(dLat / 2) +
      Math.cos(this.toRadians(point1.latitude)) *
        Math.cos(this.toRadians(point2.latitude)) *
        Math.sin(dLon / 2) *
        Math.sin(dLon / 2);
    
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
  }

  /**
   * Convert degrees to radians
   */
  private toRadians(degrees: number): number {
    return degrees * (Math.PI / 180);
  }

  /**
   * Generate cache key for memoization
   */
  private getCacheKey(routePoints: RoutePoint[], currentPosition: RoutePoint): string {
    return `${routePoints.length}_${currentPosition.latitude}_${currentPosition.longitude}`;
  }

  /**
   * Get empty progress result for invalid routes
   */
  private getEmptyProgress(currentPosition: RoutePoint): RouteProgressResult {
    return {
      completedSegment: {
        points: [currentPosition],
        distance: 0,
      },
      remainingSegment: {
        points: [currentPosition],
        distance: 0,
      },
      progressPercent: 0,
      completedDistance: 0,
      remainingDistance: 0,
      nearestPointIndex: 0,
      isOffRoute: true,
    };
  }

  /**
   * Clear caches (useful for testing or memory management)
   */
  clearCaches(): void {
    this.distanceCache.clear();
    this.nearestPointCache.clear();
  }
}

// Singleton instance
export const routeProgressService = new RouteProgressService();
