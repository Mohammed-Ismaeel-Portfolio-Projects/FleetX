import { memo } from 'react';
import { MapContainer, TileLayer } from 'react-leaflet';

interface MiniMapProps {
  bounds: [[number, number], [number, number]] | null;
}

function MiniMapComponent({ bounds }: MiniMapProps) {
  if (!bounds) return null;

  const center: [number, number] = [
    (bounds[0][0] + bounds[1][0]) / 2,
    (bounds[0][1] + bounds[1][1]) / 2,
  ];

  return (
    <div className="absolute bottom-4 right-4 z-[1000] w-40 h-32 rounded-xl overflow-hidden border border-white/10 shadow-lg">
      <MapContainer
        style={{ height: '100%', width: '100%' }}
        center={center}
        zoom={4}
        {...({ center, zoom: 4 } as any)}
        attributionControl={false}
        zoomControl={false}
        scrollWheelZoom={false}
        dragging={false}
        doubleClickZoom={false}
        touchZoom={false}
      >
        <TileLayer
          url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
        />
      </MapContainer>
    </div>
  );
}

export default memo(MiniMapComponent);
