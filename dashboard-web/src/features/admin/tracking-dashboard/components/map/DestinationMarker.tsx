import { memo } from 'react';
import { Marker, Tooltip } from 'react-leaflet';
import L from 'leaflet';

interface DestinationMarkerProps {
  position: [number, number];
  city: string;
}

function DestinationMarkerComponent({ position, city }: DestinationMarkerProps) {
  const icon = L.divIcon({
    className: 'destination-marker',
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
          <path d="M16 4L20 12H12L16 4Z" fill="#ef4444" stroke="#0E1320" stroke-width="2" stroke-linejoin="round"/>
          <rect x="12" y="12" width="8" height="12" rx="1" fill="#ef4444" stroke="#0E1320" stroke-width="2"/>
          <rect x="10" y="24" width="12" height="4" rx="1" fill="#ef4444" stroke="#0E1320" stroke-width="2"/>
          <circle cx="16" cy="18" r="2" fill="#0E1320"/>
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
          border: '1px solid rgba(239, 68, 68, 0.3)',
          borderRadius: '6px',
          padding: '6px 10px',
          color: '#fff',
          fontSize: '11px',
          fontWeight: '600',
          whiteSpace: 'nowrap',
        }}>
          <div style={{ color: '#ef4444', marginBottom: '2px' }}>Delivery location</div>
          <div style={{ color: '#cbd5e1', fontSize: '10px' }}>{city}</div>
        </div>
      </Tooltip>
    </Marker>
  );
}

export default memo(DestinationMarkerComponent);
