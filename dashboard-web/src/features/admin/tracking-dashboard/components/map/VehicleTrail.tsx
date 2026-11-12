import { memo } from 'react';
import { Polyline } from 'react-leaflet';
import type { TrailPoint } from '../../hooks/useVehicleTrail';
import type { VehicleStatus } from '../../types/tracking.types';

interface VehicleTrailProps {
  trail: TrailPoint[];
  status: VehicleStatus;
  isSelected: boolean;
}

function getTrailColor(status: VehicleStatus, isSelected: boolean): string {
  if (isSelected) return '#06b6d4'; // Cyan for selected
  
  switch (status) {
    case 'moving':
      return '#22c55e'; // Green
    case 'idle':
      return '#eab308'; // Yellow
    case 'offline':
      return '#64748b'; // Gray
    default:
      return '#64748b';
  }
}

function getTrailOpacity(status: VehicleStatus, isSelected: boolean): number {
  if (isSelected) return 0.7;
  
  switch (status) {
    case 'moving':
      return 0.5;
    case 'idle':
      return 0.4;
    case 'offline':
      return 0.25;
    default:
      return 0.25;
  }
}

function getTrailWeight(isSelected: boolean): number {
  return isSelected ? 3 : 2;
}

function VehicleTrailComponent({ trail, status, isSelected }: VehicleTrailProps) {
  if (trail.length < 2) return null;

  const positions: [number, number][] = trail.map(point => [point.lat, point.lng]);
  const color = getTrailColor(status, isSelected);
  const opacity = getTrailOpacity(status, isSelected);
  const weight = getTrailWeight(isSelected);

  return (
    <Polyline
      positions={positions}
      pathOptions={{
        color,
        weight,
        opacity,
        lineCap: 'round',
        lineJoin: 'round',
      }}
    />
  );
}

export default memo(VehicleTrailComponent);
