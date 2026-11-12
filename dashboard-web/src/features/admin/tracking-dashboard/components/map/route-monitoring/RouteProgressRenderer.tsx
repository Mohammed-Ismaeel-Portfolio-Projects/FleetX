import { memo } from 'react';
import { Polyline } from 'react-leaflet';
import type { RouteData } from '../../../services/route/route-provider.interface';

interface RouteProgressRendererProps {
  routeData: RouteData;
  isVisible: boolean;
  opacity?: number;
}

function RouteProgressRendererComponent({
  routeData,
  isVisible,
  opacity = 1,
}: RouteProgressRendererProps) {
  if (!isVisible || !routeData) return null;

  // Convert route points to Leaflet format
  const completedPositions: [number, number][] = routeData.completedSegment.points.map(
    point => [point.latitude, point.longitude]
  );

  const remainingPositions: [number, number][] = routeData.remainingSegment.points.map(
    point => [point.latitude, point.longitude]
  );

  return (
    <>
      {/* Completed Segment - Green, slightly thicker */}
      {completedPositions.length > 1 && (
        <Polyline
          positions={completedPositions}
          pathOptions={{
            color: '#22c55e',
            weight: 4,
            opacity: 0.7 * opacity,
            lineCap: 'round',
            lineJoin: 'round',
          }}
        />
      )}

      {/* Remaining Segment - FleetX Cyan, professional appearance */}
      {remainingPositions.length > 1 && (
        <Polyline
          positions={remainingPositions}
          pathOptions={{
            color: '#06b6d4',
            weight: 3,
            opacity: 0.6 * opacity,
            lineCap: 'round',
            lineJoin: 'round',
            dashArray: '8, 8',
          }}
        />
      )}
    </>
  );
}

export default memo(RouteProgressRendererComponent);
