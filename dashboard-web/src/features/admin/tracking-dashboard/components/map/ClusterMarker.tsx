import { memo, useCallback } from 'react';
import { Marker, useMap } from 'react-leaflet';
import L from 'leaflet';

interface ClusterMarkerProps {
  vehicles: any[];
  position: [number, number];
  count: number;
  onClick: (vehicles: any[]) => void;
}

function ClusterMarkerComponent({ vehicles, position, count, onClick }: ClusterMarkerProps) {
  const map = useMap();

  const handleClick = useCallback(() => {
    onClick(vehicles);
    map.flyTo(position, 12, {
      duration: 1,
    });
  }, [vehicles, position, onClick, map]);

  const size = Math.min(60, 30 + count * 2);
  const color = count > 20 ? '#EF4444' : count > 10 ? '#F59E0B' : '#22C55E';

  const icon = L.divIcon({
    className: 'cluster-marker',
    html: `
      <div style="
        width: ${size}px;
        height: ${size}px;
        border-radius: 50%;
        background: ${color};
        border: 3px solid #0E1320;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 0 20px ${color}40;
        cursor: pointer;
        transition: transform 0.2s;
      " onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
        <span style="
          color: white;
          font-weight: bold;
          font-size: ${Math.max(12, 16 - count * 0.1)}px;
          font-family: monospace;
        ">${count}</span>
      </div>
    `,
    iconSize: [size, size],
    iconAnchor: [size / 2, size / 2],
  });

  return (
    <Marker
      position={position}
      icon={icon}
      eventHandlers={{ click: handleClick }}
      {...({ icon } as any)}
    />
  );
}

export default memo(ClusterMarkerComponent);
