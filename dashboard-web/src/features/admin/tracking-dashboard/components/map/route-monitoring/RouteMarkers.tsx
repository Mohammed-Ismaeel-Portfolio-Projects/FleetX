import { memo } from 'react';
import { Marker, Tooltip } from 'react-leaflet';
import L from 'leaflet';
import type { RoutePoint } from '../../../services/route/route-provider.interface';

interface RouteMarkersProps {
  origin: RoutePoint;
  destination: RoutePoint;
  originCity: string;
  destinationCity: string;
  shipmentNumber: string;
  isVisible: boolean;
}

// Green warehouse icon for pickup location
const createOriginIcon = () => {
  return L.divIcon({
    className: 'route-origin-marker',
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
          <rect x="4" y="8" width="24" height="20" rx="2" fill="#22c55e" stroke="#0E1320" stroke-width="2"/>
          <rect x="8" y="4" width="16" height="8" rx="1" fill="#22c55e" stroke="#0E1320" stroke-width="2"/>
          <rect x="10" y="14" width="4" height="4" rx="1" fill="#0E1320"/>
          <rect x="18" y="14" width="4" height="4" rx="1" fill="#0E1320"/>
        </svg>
      </div>
    `,
    iconSize: [32, 32],
    iconAnchor: [16, 16],
  });
};

// Red destination icon for delivery location
const createDestinationIcon = () => {
  return L.divIcon({
    className: 'route-destination-marker',
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
          <path d="M16 4L16 28" stroke="#ef4444" stroke-width="3" stroke-linecap="round"/>
          <path d="M8 16L16 4L24 16" stroke="#ef4444" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
          <circle cx="16" cy="28" r="3" fill="#ef4444" stroke="#0E1320" stroke-width="2"/>
        </svg>
      </div>
    `,
    iconSize: [32, 32],
    iconAnchor: [16, 32],
  });
};

function RouteMarkersComponent({
  origin,
  destination,
  originCity,
  destinationCity,
  shipmentNumber,
  isVisible,
}: RouteMarkersProps) {
  if (!isVisible) return null;

  return (
    <>
      {/* Origin Marker */}
      <Marker
        position={[origin.latitude, origin.longitude]}
        icon={createOriginIcon()}
        {...({ icon: createOriginIcon() } as any)}
      >
        <Tooltip
          direction="right"
          offset={[10, 0]}
          opacity={1}
          {...({ direction: 'right', offset: [10, 0], opacity: 1 } as any)}
        >
          <div style={{
            background: 'rgba(14, 19, 32, 0.95)',
            border: '1px solid rgba(34, 197, 94, 0.3)',
            borderRadius: '8px',
            padding: '8px 12px',
            color: '#fff',
            fontSize: '11px',
            fontFamily: 'system-ui, sans-serif',
          }}>
            <div style={{ fontWeight: '600', color: '#22c55e', marginBottom: '2px' }}>
              Pickup Location
            </div>
            <div style={{ color: '#cbd5e1' }}>{originCity}</div>
            <div style={{ color: '#64748b', fontSize: '10px', marginTop: '2px' }}>
              {shipmentNumber}
            </div>
          </div>
        </Tooltip>
      </Marker>

      {/* Destination Marker */}
      <Marker
        position={[destination.latitude, destination.longitude]}
        icon={createDestinationIcon()}
        {...({ icon: createDestinationIcon() } as any)}
      >
        <Tooltip
          direction="right"
          offset={[10, 0]}
          opacity={1}
          {...({ direction: 'right', offset: [10, 0], opacity: 1 } as any)}
        >
          <div style={{
            background: 'rgba(14, 19, 32, 0.95)',
            border: '1px solid rgba(239, 68, 68, 0.3)',
            borderRadius: '8px',
            padding: '8px 12px',
            color: '#fff',
            fontSize: '11px',
            fontFamily: 'system-ui, sans-serif',
          }}>
            <div style={{ fontWeight: '600', color: '#ef4444', marginBottom: '2px' }}>
              Delivery Location
            </div>
            <div style={{ color: '#cbd5e1' }}>{destinationCity}</div>
            <div style={{ color: '#64748b', fontSize: '10px', marginTop: '2px' }}>
              {shipmentNumber}
            </div>
          </div>
        </Tooltip>
      </Marker>
    </>
  );
}

export default memo(RouteMarkersComponent);
