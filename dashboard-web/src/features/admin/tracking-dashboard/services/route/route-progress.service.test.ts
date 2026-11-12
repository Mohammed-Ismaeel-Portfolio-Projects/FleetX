/**
 * Route Progress Service Tests
 * 
 * Unit tests for RouteProgressService covering:
 * - Vehicle at origin
 * - Vehicle at destination
 * - Vehicle halfway
 * - Off-route position
 * - Empty geometry
 */

import { routeProgressService } from './route-progress.service';
import type { RoutePoint } from './route-provider.interface';

describe('RouteProgressService', () => {
  beforeEach(() => {
    // Clear caches before each test
    routeProgressService.clearCaches();
  });

  describe('Vehicle at origin', () => {
    it('should return 0% progress when vehicle is at origin', () => {
      const routePoints: RoutePoint[] = [
        { latitude: 52.5200, longitude: 13.4050 }, // Berlin (origin)
        { latitude: 52.0000, longitude: 14.0000 },
        { latitude: 51.5000, longitude: 14.5000 },
        { latitude: 51.0000, longitude: 15.0000 },
        { latitude: 50.8503, longitude: 4.3517 },  // Brussels (destination)
      ];

      const currentPosition: RoutePoint = {
        latitude: 52.5200,
        longitude: 13.4050,
      };

      const totalDistance = 650; // Approximate distance in km

      const result = routeProgressService.calculateProgress(
        routePoints,
        currentPosition,
        totalDistance
      );

      expect(result.progressPercent).toBe(0);
      expect(result.completedDistance).toBeCloseTo(0, 1);
      expect(result.remainingDistance).toBeCloseTo(totalDistance, 0);
      expect(result.nearestPointIndex).toBe(0);
      expect(result.isOffRoute).toBe(false);
      expect(result.completedSegment.points.length).toBeGreaterThan(0);
      expect(result.remainingSegment.points.length).toBe(routePoints.length);
    });
  });

  describe('Vehicle at destination', () => {
    it('should return 100% progress when vehicle is at destination', () => {
      const routePoints: RoutePoint[] = [
        { latitude: 52.5200, longitude: 13.4050 }, // Berlin (origin)
        { latitude: 52.0000, longitude: 14.0000 },
        { latitude: 51.5000, longitude: 14.5000 },
        { latitude: 51.0000, longitude: 15.0000 },
        { latitude: 50.8503, longitude: 4.3517 },  // Brussels (destination)
      ];

      const currentPosition: RoutePoint = {
        latitude: 50.8503,
        longitude: 4.3517,
      };

      const totalDistance = 650;

      const result = routeProgressService.calculateProgress(
        routePoints,
        currentPosition,
        totalDistance
      );

      expect(result.progressPercent).toBe(100);
      expect(result.completedDistance).toBeCloseTo(totalDistance, 0);
      expect(result.remainingDistance).toBeCloseTo(0, 1);
      expect(result.nearestPointIndex).toBe(routePoints.length - 1);
      expect(result.isOffRoute).toBe(false);
      expect(result.completedSegment.points.length).toBe(routePoints.length);
      expect(result.remainingSegment.points.length).toBeGreaterThan(0);
    });
  });

  describe('Vehicle halfway', () => {
    it('should return approximately 50% progress when vehicle is halfway', () => {
      const routePoints: RoutePoint[] = [
        { latitude: 52.5200, longitude: 13.4050 }, // Berlin
        { latitude: 52.0000, longitude: 14.0000 },
        { latitude: 51.5000, longitude: 14.5000 },
        { latitude: 51.0000, longitude: 15.0000 },
        { latitude: 50.8503, longitude: 4.3517 },  // Brussels
      ];

      const currentPosition: RoutePoint = {
        latitude: 51.5000,
        longitude: 14.5000,
      };

      const totalDistance = 650;

      const result = routeProgressService.calculateProgress(
        routePoints,
        currentPosition,
        totalDistance
      );

      expect(result.progressPercent).toBeGreaterThan(40);
      expect(result.progressPercent).toBeLessThan(60);
      expect(result.nearestPointIndex).toBe(2);
      expect(result.isOffRoute).toBe(false);
    });
  });

  describe('Off-route position', () => {
    it('should detect when vehicle is significantly off route', () => {
      const routePoints: RoutePoint[] = [
        { latitude: 52.5200, longitude: 13.4050 }, // Berlin
        { latitude: 52.0000, longitude: 14.0000 },
        { latitude: 51.5000, longitude: 14.5000 },
        { latitude: 51.0000, longitude: 15.0000 },
        { latitude: 50.8503, longitude: 4.3517 },  // Brussels
      ];

      // Position far from route (Munich instead of on route)
      const currentPosition: RoutePoint = {
        latitude: 48.1351,
        longitude: 11.5820,
      };

      const totalDistance = 650;

      const result = routeProgressService.calculateProgress(
        routePoints,
        currentPosition,
        totalDistance
      );

      expect(result.isOffRoute).toBe(true);
      expect(result.progressPercent).toBeGreaterThanOrEqual(0);
      expect(result.progressPercent).toBeLessThanOrEqual(100);
    });

    it('should not flag as off-route when vehicle is close to route', () => {
      const routePoints: RoutePoint[] = [
        { latitude: 52.5200, longitude: 13.4050 },
        { latitude: 52.0000, longitude: 14.0000 },
        { latitude: 51.5000, longitude: 14.5000 },
      ];

      // Position close to route (within 5km)
      const currentPosition: RoutePoint = {
        latitude: 51.5000,
        longitude: 14.6000, // Slightly off but within threshold
      };

      const totalDistance = 200;

      const result = routeProgressService.calculateProgress(
        routePoints,
        currentPosition,
        totalDistance
      );

      expect(result.isOffRoute).toBe(false);
    });
  });

  describe('Empty geometry', () => {
    it('should handle empty route points', () => {
      const routePoints: RoutePoint[] = [];
      const currentPosition: RoutePoint = {
        latitude: 52.5200,
        longitude: 13.4050,
      };
      const totalDistance = 100;

      const result = routeProgressService.calculateProgress(
        routePoints,
        currentPosition,
        totalDistance
      );

      expect(result.progressPercent).toBe(0);
      expect(result.completedDistance).toBe(0);
      expect(result.remainingDistance).toBe(0);
      expect(result.isOffRoute).toBe(true);
      expect(result.completedSegment.points).toEqual([currentPosition]);
      expect(result.remainingSegment.points).toEqual([currentPosition]);
    });

    it('should handle single route point', () => {
      const routePoints: RoutePoint[] = [
        { latitude: 52.5200, longitude: 13.4050 },
      ];
      const currentPosition: RoutePoint = {
        latitude: 52.5200,
        longitude: 13.4050,
      };
      const totalDistance = 100;

      const result = routeProgressService.calculateProgress(
        routePoints,
        currentPosition,
        totalDistance
      );

      expect(result.progressPercent).toBe(0);
      expect(result.completedDistance).toBe(0);
      expect(result.remainingDistance).toBe(0);
      expect(result.isOffRoute).toBe(true);
    });
  });

  describe('Memoization', () => {
    it('should cache nearest point calculations', () => {
      const routePoints: RoutePoint[] = [
        { latitude: 52.5200, longitude: 13.4050 },
        { latitude: 52.0000, longitude: 14.0000 },
        { latitude: 51.5000, longitude: 14.5000 },
      ];
      const currentPosition: RoutePoint = {
        latitude: 51.5000,
        longitude: 14.5000,
      };
      const totalDistance = 200;

      // First call
      const result1 = routeProgressService.calculateProgress(
        routePoints,
        currentPosition,
        totalDistance
      );

      // Second call with same parameters (should use cache)
      const result2 = routeProgressService.calculateProgress(
        routePoints,
        currentPosition,
        totalDistance
      );

      expect(result1.nearestPointIndex).toBe(result2.nearestPointIndex);
      expect(result1.progressPercent).toBe(result2.progressPercent);
    });

    it('should clear caches when requested', () => {
      const routePoints: RoutePoint[] = [
        { latitude: 52.5200, longitude: 13.4050 },
        { latitude: 52.0000, longitude: 14.0000 },
      ];
      const currentPosition: RoutePoint = {
        latitude: 52.0000,
        longitude: 14.0000,
      };
      const totalDistance = 100;

      routeProgressService.calculateProgress(routePoints, currentPosition, totalDistance);
      routeProgressService.clearCaches();

      // Should not throw and should work after cache clear
      const result = routeProgressService.calculateProgress(
        routePoints,
        currentPosition,
        totalDistance
      );

      expect(result).toBeDefined();
    });
  });

  describe('Distance calculations', () => {
    it('should calculate accurate distances between points', () => {
      const routePoints: RoutePoint[] = [
        { latitude: 52.5200, longitude: 13.4050 }, // Berlin
        { latitude: 50.8503, longitude: 4.3517 },  // Brussels
      ];
      const currentPosition: RoutePoint = {
        latitude: 52.5200,
        longitude: 13.4050,
      };
      const totalDistance = 650;

      const result = routeProgressService.calculateProgress(
        routePoints,
        currentPosition,
        totalDistance
      );

      // Berlin to Brussels is approximately 650km
      expect(result.completedDistance).toBeCloseTo(0, 1);
      expect(result.remainingDistance).toBeCloseTo(650, 10);
    });
  });

  describe('Segment splitting', () => {
    it('should correctly split route into completed and remaining segments', () => {
      const routePoints: RoutePoint[] = [
        { latitude: 52.5200, longitude: 13.4050 }, // 0: Berlin
        { latitude: 52.0000, longitude: 14.0000 }, // 1
        { latitude: 51.5000, longitude: 14.5000 }, // 2: halfway
        { latitude: 51.0000, longitude: 15.0000 }, // 3
        { latitude: 50.8503, longitude: 4.3517 },  // 4: Brussels
      ];

      const currentPosition: RoutePoint = {
        latitude: 51.5000,
        longitude: 14.5000,
      };

      const totalDistance = 650;

      const result = routeProgressService.calculateProgress(
        routePoints,
        currentPosition,
        totalDistance
      );

      // Completed segment should include points 0, 1, 2
      expect(result.completedSegment.points.length).toBe(3);
      expect(result.completedSegment.points[0]).toEqual(routePoints[0]);
      expect(result.completedSegment.points[2]).toEqual(routePoints[2]);

      // Remaining segment should include points 2, 3, 4
      expect(result.remainingSegment.points.length).toBe(3);
      expect(result.remainingSegment.points[0]).toEqual(routePoints[2]);
      expect(result.remainingSegment.points[2]).toEqual(routePoints[4]);
    });
  });
});
