import { useState, useCallback, useEffect, useRef } from 'react';
import { trackingRepository } from '../services/tracking.repository';
import type { FleetVehicle, TrackingStats, TrackingEvent } from '../types/tracking.types';

interface UseTrackingDataReturn {
  vehicles: FleetVehicle[];
  stats: TrackingStats;
  events: TrackingEvent[];
  loading: boolean;
  error: string | null;
  refresh: () => Promise<void>;
}

export function useTrackingData(): UseTrackingDataReturn {
  const [vehicles, setVehicles] = useState<FleetVehicle[]>([]);
  const [stats, setStats] = useState<TrackingStats>({
    movingVehicles: 0,
    idleVehicles: 0,
    offlineVehicles: 0,
    activeAssignments: 0,
    openAlerts: 0,
    averageSpeed: 0,
  });
  const [events, setEvents] = useState<TrackingEvent[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const previousStatsRef = useRef<TrackingStats | null>(null);

  const calculateSecondaryMetrics = useCallback((vehicles: FleetVehicle[], baseStats: TrackingStats, previousStats: TrackingStats | null) => {
    const totalVehicles = vehicles.length;
    const movingVehicles = vehicles.filter(v => v.status === 'moving').length;
    
    // Calculate moving vehicles change
    const movingVehiclesChange = previousStats 
      ? movingVehicles - previousStats.movingVehicles 
      : 0;
    
    // Calculate moving vehicles percentage
    const movingVehiclesPercentage = totalVehicles > 0 
      ? Math.round((movingVehicles / totalVehicles) * 100) 
      : 0;
    
    // Calculate longest idle time (derived from lastUpdate for idle vehicles)
    const idleVehiclesWithTimestamp = vehicles.filter(v => v.status === 'idle' && v.lastUpdate);
    let longestIdle = '0 min';
    if (idleVehiclesWithTimestamp.length > 0) {
      const now = Date.now();
      const maxIdleMs = Math.max(...idleVehiclesWithTimestamp.map(v => 
        now - new Date(v.lastUpdate).getTime()
      ));
      const idleMinutes = Math.floor(maxIdleMs / 60000);
      longestIdle = idleMinutes > 60 
        ? `${Math.floor(idleMinutes / 60)}h ${idleMinutes % 60}m` 
        : `${idleMinutes} min`;
    }
    
    // Calculate last disconnect time for offline vehicles
    const offlineVehiclesWithTimestamp = vehicles.filter(v => v.status === 'offline' && v.lastUpdate);
    let lastDisconnect = 'N/A';
    if (offlineVehiclesWithTimestamp.length > 0) {
      const now = Date.now();
      const minDisconnectMs = Math.min(...offlineVehiclesWithTimestamp.map(v => 
        now - new Date(v.lastUpdate).getTime()
      ));
      const disconnectMinutes = Math.floor(minDisconnectMs / 60000);
      lastDisconnect = disconnectMinutes > 60 
        ? `${Math.floor(disconnectMinutes / 60)}h ago` 
        : `${disconnectMinutes} min ago`;
    }
    
    // Calculate fleet utilization (active assignments / total fleet capacity)
    const fleetUtilization = totalVehicles > 0 
      ? Math.round((baseStats.activeAssignments / totalVehicles) * 100) 
      : 0;
    
    // Calculate alert breakdown
    const criticalAlerts = vehicles.filter(v => v.status === 'offline').length;
    const warningAlerts = vehicles.filter(v => v.signalQuality === 'weak' || v.signalQuality === 'lost').length;
    
    // Calculate peak speed today (derived from current speeds)
    const speeds = vehicles.map(v => v.speed).filter(s => s > 0);
    const peakSpeed = speeds.length > 0 ? Math.max(...speeds) : 0;
    
    return {
      movingVehiclesChange,
      movingVehiclesPercentage,
      idleVehiclesLongestIdle: longestIdle,
      offlineVehiclesLastDisconnect: lastDisconnect,
      activeAssignmentsUtilization: fleetUtilization,
      openAlertsCritical: criticalAlerts,
      openAlertsWarning: warningAlerts,
      averageSpeedPeak: peakSpeed,
    };
  }, []);

  const fetchData = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      
      const data = await trackingRepository.getFleet();
      setVehicles(data.vehicles);
      
      // Calculate secondary metrics
      const secondaryMetrics = calculateSecondaryMetrics(data.vehicles, data.stats, previousStatsRef.current);
      
      const enrichedStats = {
        ...data.stats,
        ...secondaryMetrics,
      };
      
      setStats(enrichedStats);
      setEvents(data.events);
      
      previousStatsRef.current = enrichedStats;
    } catch (err) {
      setError('Failed to fetch tracking data');
      console.error('Error fetching tracking data:', err);
    } finally {
      setLoading(false);
    }
  }, [calculateSecondaryMetrics]);

  const refresh = useCallback(async () => {
    await fetchData();
  }, [fetchData]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  return {
    vehicles,
    stats,
    events,
    loading,
    error,
    refresh,
  };
}
