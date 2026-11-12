import { memo, useEffect, useState, useRef } from 'react';
import RouteMarkers from './RouteMarkers';
import RouteProgressRenderer from './RouteProgressRenderer';
import RouteMonitoringCard from './RouteMonitoringCard';
import { routeMonitoringService, type RouteMonitoringData } from '../../../services/route/route-monitoring.service';
import type { FleetVehicle } from '../../../types/tracking-dashboard.types';

interface RouteMonitoringOverlayProps {
  selectedVehicle: FleetVehicle | null;
  vehicleDetails: any; // VehicleDetails from tracking.types
}

function RouteMonitoringOverlayComponent({
  selectedVehicle,
  vehicleDetails,
}: RouteMonitoringOverlayProps) {
  const [routeData, setRouteData] = useState<RouteMonitoringData | null>(null);
  const [opacity, setOpacity] = useState(0);
  const [isLoading, setIsLoading] = useState(false);
  const updateTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Calculate initial route data when vehicle is selected
  useEffect(() => {
    if (!selectedVehicle || !vehicleDetails?.shipment) {
      setRouteData(null);
      return;
    }

    const calculateRoute = async () => {
      setIsLoading(true);
      try {
        const data = await routeMonitoringService.calculateRouteMonitoring(
          selectedVehicle.shipment,
          selectedVehicle.shipmentId,
          vehicleDetails.shipment.origin,
          vehicleDetails.shipment.destination,
          {
            latitude: selectedVehicle.latitude || 0,
            longitude: selectedVehicle.longitude || 0,
          },
          selectedVehicle.speed,
          selectedVehicle.speed
        );
        setRouteData(data);
      } catch (error) {
        console.error('Error calculating route:', error);
      } finally {
        setIsLoading(false);
      }
    };

    calculateRoute();
  }, [selectedVehicle, vehicleDetails]);

  // Update route data on vehicle position change (debounced)
  useEffect(() => {
    if (!selectedVehicle || !routeData || isLoading) return;

    // Clear previous timeout
    if (updateTimeoutRef.current) {
      clearTimeout(updateTimeoutRef.current);
    }

    // Debounce updates to prevent excessive recalculations
    updateTimeoutRef.current = setTimeout(async () => {
      try {
        const updatedData = await routeMonitoringService.updateRouteMonitoring(
          routeData,
          {
            latitude: selectedVehicle.latitude || 0,
            longitude: selectedVehicle.longitude || 0,
          },
          selectedVehicle.speed
        );
        setRouteData(updatedData);
      } catch (error) {
        console.error('Error updating route:', error);
      }
    }, 500); // 500ms debounce

    return () => {
      if (updateTimeoutRef.current) {
        clearTimeout(updateTimeoutRef.current);
      }
    };
  }, [selectedVehicle?.latitude, selectedVehicle?.longitude, selectedVehicle?.speed, routeData, isLoading]);

  // Fade in/out animation
  useEffect(() => {
    if (selectedVehicle && routeData) {
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
  }, [selectedVehicle, routeData]);

  const isVisible = selectedVehicle !== null && routeData !== null && opacity > 0;

  if (!isVisible) return null;

  if (!routeData || !routeData.routeData) return null;

  return (
    <>
      {/* Route Progress (Completed + Remaining segments) */}
      <RouteProgressRenderer
        routeData={routeData.routeData}
        isVisible={isVisible}
        opacity={opacity}
      />

      {/* Pickup and Destination Markers */}
      <RouteMarkers
        origin={routeData.routeData.origin}
        destination={routeData.routeData.destination}
        originCity={routeData.originCity}
        destinationCity={routeData.destinationCity}
        shipmentNumber={routeData.shipmentNumber}
        isVisible={isVisible}
      />

      {/* Route Monitoring Card */}
      <RouteMonitoringCard
        data={routeData}
        isVisible={isVisible}
        opacity={opacity}
      />
    </>
  );
}

export default memo(RouteMonitoringOverlayComponent);
