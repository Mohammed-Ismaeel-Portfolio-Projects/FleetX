import { memo } from 'react';
import { Polyline } from 'react-leaflet';
import type { RouteData } from '../../../../features/admin/tracking-dashboard/services/route/route-provider.interface';

interface RouteProgressRendererProps {
  routeData: RouteData;
  isVisible: boolean;
  opacity?: number;
  variant?: 'default' | 'alert';
}

function RouteProgressRendererComponent({
  routeData,
  isVisible,
  opacity = 1,
  variant = 'default',
}: RouteProgressRendererProps) {
  if (!isVisible || !routeData) return null;

  const completedPositions: [number, number][] = routeData.completedSegment.points.map(
    (point) => [point.latitude, point.longitude]
  );

  const remainingPositions: [number, number][] = routeData.remainingSegment.points.map(
    (point) => [point.latitude, point.longitude]
  );

  const remainingColor = variant === 'alert' ? '#f97316' : '#06b6d4';
  const completedColor = variant === 'alert' ? '#fb7185' : '#22c55e';

  return (
    <>
      {completedPositions.length > 1 && (
        <Polyline
          positions={completedPositions}
          pathOptions={{
            color: completedColor,
            weight: 4,
            opacity: 0.75 * opacity,
            lineCap: 'round',
            lineJoin: 'round',
          }}
        />
      )}

      {remainingPositions.length > 1 && (
        <Polyline
          positions={remainingPositions}
          pathOptions={{
            color: remainingColor,
            weight: variant === 'alert' ? 4 : 3,
            opacity: 0.65 * opacity,
            lineCap: 'round',
            lineJoin: 'round',
            dashArray: variant === 'alert' ? '6, 10' : '8, 8',
          }}
        />
      )}
    </>
  );
}

export default memo(RouteProgressRendererComponent);
