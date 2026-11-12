import { memo, useMemo, useEffect, useState } from 'react';
import { Polyline } from 'react-leaflet';
import OriginMarker from './OriginMarker';
import DestinationMarker from './DestinationMarker';
import RouteInfoCard from './RouteInfoCard';

interface RouteOverlayProps {
  originCity: string;
  destinationCity: string;
  originCoords?: [number, number];
  destinationCoords?: [number, number];
  progressPercent: number;
  distanceRemaining: number;
  estimatedDistance: number;
  eta: string;
  averageSpeed: number;
  currentSpeed: number;
  isVisible: boolean;
}

// Simple city coordinate mapping for demo (in production, use a proper geocoding service)
const CITY_COORDS: Record<string, [number, number]> = {
  'Berlin, DE': [52.52, 13.405],
  'Paris, FR': [48.8566, 2.3522],
  'Munich, DE': [48.1351, 11.582],
  'Hamburg, DE': [53.5511, 9.9937],
  'Frankfurt, DE': [50.1109, 8.6821],
  'Vienna, AT': [48.2082, 16.3738],
  'Prague, CZ': [50.0755, 14.4378],
  'Warsaw, PL': [52.2297, 21.0122],
  'Amsterdam, NL': [52.3676, 4.9041],
  'Brussels, BE': [50.8503, 4.3517],
  'Milan, IT': [45.4642, 9.19],
  'Rome, IT': [41.9028, 12.4964],
  'Madrid, ES': [40.4168, -3.7038],
  'Barcelona, ES': [41.3851, 2.1734],
  'Lyon, FR': [45.764, 4.8357],
};

function getCityCoords(city: string): [number, number] {
  return CITY_COORDS[city] || [50, 10]; // Default to central Europe
}

function RouteOverlayComponent({
  originCity,
  destinationCity,
  originCoords,
  destinationCoords,
  progressPercent,
  distanceRemaining,
  estimatedDistance,
  eta,
  averageSpeed,
  currentSpeed,
  isVisible,
}: RouteOverlayProps) {
  const [opacity, setOpacity] = useState(0);

  const actualOriginCoords = useMemo(() => {
    if (originCoords) return originCoords;
    return getCityCoords(originCity);
  }, [originCity, originCoords]);

  const actualDestinationCoords = useMemo(() => {
    if (destinationCoords) return destinationCoords;
    return getCityCoords(destinationCity);
  }, [destinationCity, destinationCoords]);

  // Fade in/out animation
  useEffect(() => {
    if (isVisible) {
      // Fade in
      const fadeIn = setInterval(() => {
        setOpacity(prev => {
          if (prev >= 1) {
            clearInterval(fadeIn);
            return 1;
          }
          return prev + 0.1;
        });
      }, 30);
      return () => clearInterval(fadeIn);
    } else {
      // Fade out
      const fadeOut = setInterval(() => {
        setOpacity(prev => {
          if (prev <= 0) {
            clearInterval(fadeOut);
            return 0;
          }
          return prev - 0.1;
        });
      }, 30);
      return () => clearInterval(fadeOut);
    }
  }, [isVisible]);

  if (!isVisible && opacity === 0) return null;

  const routePositions: [number, number][] = [actualOriginCoords, actualDestinationCoords];

  return (
    <>
      {/* Route Line */}
      <Polyline
        positions={routePositions}
        pathOptions={{
          color: '#06b6d4',
          weight: 3,
          opacity: 0.6 * opacity,
          lineCap: 'round',
          lineJoin: 'round',
          dashArray: '8, 8',
        }}
      />

      {/* Origin Marker */}
      <div style={{ opacity, transition: 'opacity 0.3s ease-out' }}>
        <OriginMarker position={actualOriginCoords} city={originCity} />
      </div>

      {/* Destination Marker */}
      <div style={{ opacity, transition: 'opacity 0.3s ease-out' }}>
        <DestinationMarker position={actualDestinationCoords} city={destinationCity} />
      </div>

      {/* Route Info Card */}
      <div style={{ opacity, transition: 'opacity 0.3s ease-out' }}>
        <RouteInfoCard
          origin={originCity}
          destination={destinationCity}
          progressPercent={progressPercent}
          distanceRemaining={distanceRemaining}
          estimatedDistance={estimatedDistance}
          eta={eta}
          averageSpeed={averageSpeed}
          currentSpeed={currentSpeed}
        />
      </div>
    </>
  );
}

export default memo(RouteOverlayComponent);
