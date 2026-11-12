import { memo } from 'react';
import { Marker, Tooltip } from 'react-leaflet';
import L from 'leaflet';

interface OriginMarkerProps {
  position: [number, number];
  city: string;
}

function OriginMarkerComponent({ position, city }: OriginMarkerProps) {
  const icon = L.divIcon({
    className: 'origin-marker',
    html: `
      <div style="
        position: relative;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
      ">
        <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect x="4" y="8" width="24" height="16" rx="2" fill="#22c55e" stroke="#0E1320" stroke-width="2"/>
          <rect x="8" y="4" width="16" height="4" fill="#22c55e" stroke="#0E1320" stroke-width="2"/>
          <rect x="12" y="12" width="8" height="8" fill="#0E1320" stroke="#22c55e" stroke-width="1"/>
        </svg>
      </div>
    `,
    iconSize: [32, 32],
    iconAnchor: [16, 16],
  });

  return (
    <Marker position={position} {...({ icon } as any)}>
      <Tooltip {...({ direction: 'top', offset: [0, -20], className: 'route-tooltip' } as any)}>
        <div style={{
          background: 'rgba(14, 19, 32, 0.95)',
          border: '1px solid rgba(34, 197, 94, 0.3)',
          borderRadius: '6px',
          padding: '6px 10px',
          color: '#fff',
          fontSize: '11px',
          fontWeight: '600',
          whiteSpace: 'nowrap',
        }}>
          <div style={{ color: '#22c55e', marginBottom: '2px' }}>Pickup location</div>
          <div style={{ color: '#cbd5e1', fontSize: '10px' }}>{city}</div>
        </div>
      </Tooltip>
    </Marker>
  );
}

export default memo(OriginMarkerComponent);
