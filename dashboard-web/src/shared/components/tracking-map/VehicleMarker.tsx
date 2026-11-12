import { memo, useCallback, useRef, useEffect } from 'react';
import { Marker } from 'react-leaflet';
import L from 'leaflet';
import { getMarkerColor } from './utils/map.utils';
import type { TrackingMapVehicle } from './types';

interface VehicleMarkerProps {
  vehicle: TrackingMapVehicle;
  isSelected: boolean;
  hasAlert: boolean;
  onClick: (vehicle: TrackingMapVehicle) => void;
}

function VehicleMarkerComponent({ vehicle, isSelected, hasAlert, onClick }: VehicleMarkerProps) {
  const markerRef = useRef<L.Marker>(null);
  const prevPositionRef = useRef<[number, number] | null>(null);
  const prevHeadingRef = useRef<number>(vehicle.heading);
  
  const color = getMarkerColor(vehicle.status, isSelected, hasAlert);
  const isMoving = vehicle.status === 'moving';
  const isOffline = vehicle.status === 'offline';
  const size = isSelected ? 54 : hasAlert ? 42 : 36;
  
  const glow = isSelected 
    ? `0 0 22px rgba(6, 182, 212, 0.45)` 
    : hasAlert 
      ? `0 0 16px rgba(239, 68, 68, 0.35)` 
      : 'none';
  
  const pulse = isSelected 
    ? 'selectedPulse 1.6s infinite ease-in-out' 
    : hasAlert 
      ? 'alertPulse 1.2s infinite ease-in-out' 
      : isMoving 
        ? 'movingPulse 2s infinite ease-in-out' 
        : 'none';
  
  const opacity = isOffline ? 0.6 : 1;
  const zIndex = isSelected ? 1000 : (hasAlert ? 500 : 100);
  const rotationTransition = 'transform 0.3s ease-out';

  useEffect(() => {
    const marker = markerRef.current;
    if (!marker) return;

    const newPosition: [number, number] = [vehicle.latitude || 0, vehicle.longitude || 0];
    const prevPosition = prevPositionRef.current;

    if (prevPosition) {
      const distance = Math.sqrt(
        Math.pow(newPosition[0] - prevPosition[0], 2) +
        Math.pow(newPosition[1] - prevPosition[1], 2)
      );

      if (distance > 0.0001) {
        const startTime = performance.now();
        const duration = 4500;

        const animate = (currentTime: number) => {
          const elapsed = currentTime - startTime;
          const progress = Math.min(elapsed / duration, 1);
          const easeProgress = 1 - Math.pow(1 - progress, 3);
          
          const interpolatedLat = prevPosition[0] + (newPosition[0] - prevPosition[0]) * easeProgress;
          const interpolatedLng = prevPosition[1] + (newPosition[1] - prevPosition[1]) * easeProgress;
          
          marker.setLatLng([interpolatedLat, interpolatedLng]);
          
          if (progress < 1) {
            requestAnimationFrame(animate);
          } else {
            marker.setLatLng(newPosition);
          }
        };

        requestAnimationFrame(animate);
      }
    } else {
      marker.setLatLng(newPosition);
    }

    prevPositionRef.current = newPosition;
  }, [vehicle.latitude, vehicle.longitude]);

  useEffect(() => {
    const marker = markerRef.current;
    if (!marker) return;

    const prevHeading = prevHeadingRef.current;
    const headingDiff = Math.abs(vehicle.heading - prevHeading);

    if (headingDiff > 1) {
      const icon = marker.getIcon();
      if (icon instanceof L.DivIcon) {
        const html = icon.options.html as string;
        const updatedHtml = html.replace(
          /transform: rotate\(\d+deg\)/,
          `transform: rotate(${vehicle.heading}deg)`
        );
        
        const newIcon = L.divIcon({
          ...icon.options,
          html: updatedHtml,
        });
        
        marker.setIcon(newIcon);
      }
    }

    prevHeadingRef.current = vehicle.heading;
  }, [vehicle.heading]);

  const handleClick = useCallback(() => {
    onClick(vehicle);
  }, [vehicle, onClick]);

  const icon = L.divIcon({
    className: 'vehicle-marker',
    html: `
      <div style="
        position: relative;
        width: ${size}px;
        height: ${size}px;
        transform: rotate(${vehicle.heading}deg);
        filter: ${glow};
        opacity: ${opacity};
        animation: ${pulse};
        z-index: ${zIndex};
        transition: ${rotationTransition};
      ">
        <svg width="${size}" height="${size}" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
          <defs>
            <style>
              @keyframes selectedPulse {
                0%, 100% { transform: scale(1); opacity: 1; }
                50% { transform: scale(1.1); opacity: 0.9; }
              }
              @keyframes alertPulse {
                0%, 100% { opacity: 1; transform: scale(1); }
                50% { opacity: 0.8; transform: scale(1.05); }
              }
              @keyframes movingPulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.9; }
              }
            </style>
          </defs>
          <path d="M8 16H32V28H8V16Z" fill="${color}" stroke="#0E1320" stroke-width="2" stroke-linejoin="round"/>
          <path d="M32 16H40V24H32V16Z" fill="${color}" stroke="#0E1320" stroke-width="2" stroke-linejoin="round"/>
          <path d="M40 18H44V22H40V18Z" fill="${color}" stroke="#0E1320" stroke-width="2" stroke-linejoin="round"/>
          <circle cx="14" cy="28" r="4" fill="#1a1a2e" stroke="#0E1320" stroke-width="2"/>
          <circle cx="26" cy="28" r="4" fill="#1a1a2e" stroke="#0E1320" stroke-width="2"/>
          <circle cx="38" cy="28" r="4" fill="#1a1a2e" stroke="#0E1320" stroke-width="2"/>
          <path d="M24 4L28 12H20L24 4Z" fill="${color}" stroke="#0E1320" stroke-width="2" stroke-linejoin="round"/>
        </svg>
        ${isSelected ? `
          <div style="
            position: absolute;
            left: ${size + 10}px;
            top: 50%;
            transform: translateY(-50%);
            white-space: nowrap;
            pointer-events: none;
          ">
            <div style="
              background: rgba(14, 19, 32, 0.95);
              border: 1px solid rgba(6, 182, 212, 0.3);
              border-radius: 8px;
              padding: 8px 12px;
              box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4), 0 0 20px rgba(6, 182, 212, 0.1);
            ">
              <div style="display: flex; flex-direction: column; gap: 3px;">
                <div style="display: flex; align-items: center; gap: 6px;">
                  <span style="font-size: 11px; font-weight: 700; color: #06b6d4; font-family: 'Courier New', monospace; letter-spacing: 0.5px;">
                    ${vehicle.fleetNumber}
                  </span>
                  <span style="font-size: 8px; font-weight: 600; padding: 2px 6px; border-radius: 4px; color: white; background-color: #06b6d4; text-transform: uppercase; letter-spacing: 0.5px;">
                    ${vehicle.status}
                  </span>
                </div>
                <span style="font-size: 9px; color: #cbd5e1; font-weight: 500; font-family: 'Courier New', monospace;">
                  ${vehicle.speed.toFixed(0)} km/h
                </span>
              </div>
            </div>
          </div>
        ` : ''}
      </div>
    `,
    iconSize: [size + (isSelected ? 100 : 0), size],
    iconAnchor: [size / 2, size / 2],
  });

  return (
    <Marker
      position={[vehicle.latitude || 0, vehicle.longitude || 0]}
      ref={markerRef}
      icon={icon}
      eventHandlers={{ click: handleClick }}
      {...({ icon } as any)}
    />
  );
}

export default memo(VehicleMarkerComponent);
