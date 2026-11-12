import { useState, useEffect, useRef, useCallback } from 'react';
import { trackingRepository } from '../services/tracking.repository';
import { useVehicleTrail } from './useVehicleTrail';
import type { FleetVehicle, TrackingStats, TrackingEvent } from '../types/tracking.types';
import { trackingSubscriptionManager } from '../../../../core/realtime/services/tracking-subscription-manager.service';
import { realtimeConnectionService } from '../../../../core/realtime/services/realtime-connection.service';
import type { TrackingUpdateMessage } from '../../../../core/realtime/interfaces/tracking-subscription.interface';
import type { ConnectionStatus } from '../../../../core/realtime/interfaces/realtime-connection.interface';

interface UseLiveTrackingReturn {
  vehicles: FleetVehicle[];
  stats: TrackingStats;
  events: TrackingEvent[];
  loading: boolean;
  error: string | null;
  lastUpdate: Date | null;
  isLive: boolean;
  connectionStatus: ConnectionStatus;
  isRealtime: boolean;
  startPolling: () => void;
  stopPolling: () => void;
  refresh: () => Promise<void>;
  getVehicleTrail: (vehicleId: string) => import('./useVehicleTrail').TrailPoint[];
}

const POLLING_INTERVAL = 5000; // 5 seconds

export function useLiveTracking(): UseLiveTrackingReturn {
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
  const [lastUpdate, setLastUpdate] = useState<Date | null>(null);
  const [isLive, setIsLive] = useState(false);
  const [connectionStatus, setConnectionStatus] = useState<ConnectionStatus>('disconnected');
  const [isRealtime, setIsRealtime] = useState(false);
  
  const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const previousStatsRef = useRef<TrackingStats | null>(null);
  const previousEventsRef = useRef<Set<string>>(new Set());
  const previousVehiclesRef = useRef<Map<string, FleetVehicle>>(new Map());
  const connectionStatusRef = useRef<ConnectionStatus>('disconnected');
  const isRealtimeRef = useRef(false);
  
  const { updateVehiclePosition, getVehicleTrail } = useVehicleTrail();

  const calculateSecondaryMetrics = useCallback((vehicles: FleetVehicle[], baseStats: TrackingStats, previousStats: TrackingStats | null) => {
    const totalVehicles = vehicles.length;
    const movingVehicles = vehicles.filter(v => v.status === 'moving').length;
    
    const movingVehiclesChange = previousStats 
      ? movingVehicles - previousStats.movingVehicles 
      : 0;
    
    const movingVehiclesPercentage = totalVehicles > 0 
      ? Math.round((movingVehicles / totalVehicles) * 100) 
      : 0;
    
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
    
    const fleetUtilization = totalVehicles > 0 
      ? Math.round((baseStats.activeAssignments / totalVehicles) * 100) 
      : 0;
    
    const criticalAlerts = vehicles.filter(v => v.status === 'offline').length;
    const warningAlerts = vehicles.filter(v => v.signalQuality === 'weak' || v.signalQuality === 'lost').length;
    
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

  const fetchData = useCallback(async (showLoading = false) => {
    try {
      if (showLoading) {
        setLoading(true);
      }
      setError(null);
      
      const data = await trackingRepository.refreshFleet();
      
      // Update vehicle trails for vehicles with coordinates
      data.vehicles.forEach(vehicle => {
        if (vehicle.latitude !== undefined && vehicle.longitude !== undefined) {
          const prevVehicle = previousVehiclesRef.current.get(vehicle.id);
          
          // Only update trail if position changed significantly
          if (!prevVehicle || 
              (prevVehicle.latitude !== undefined && prevVehicle.longitude !== undefined &&
              (Math.abs(prevVehicle.latitude - vehicle.latitude) > 0.0001 ||
              Math.abs(prevVehicle.longitude - vehicle.longitude) > 0.0001))) {
            updateVehiclePosition(vehicle.id, vehicle.latitude, vehicle.longitude);
          }
        }
      });
      
      // Store current vehicles for next comparison
      previousVehiclesRef.current = new Map(data.vehicles.map(v => [v.id, v]));
      
      // Update vehicles
      setVehicles(data.vehicles);
      
      // Calculate and update stats
      const secondaryMetrics = calculateSecondaryMetrics(data.vehicles, data.stats, previousStatsRef.current);
      const enrichedStats = {
        ...data.stats,
        ...secondaryMetrics,
      };
      setStats(enrichedStats);
      previousStatsRef.current = enrichedStats;
      
      // Deduplicate and append only new events (limit to last 20 to prevent layout shifts)
      const currentEventIds = new Set(data.events.map(e => e.id));
      const newEvents = data.events.filter(e => !previousEventsRef.current.has(e.id));
      
      if (newEvents.length > 0) {
        setEvents(prev => {
          const combined = [...newEvents, ...prev];
          return combined.slice(0, 20); // Keep only last 20 events
        });
        previousEventsRef.current = new Set([...previousEventsRef.current, ...currentEventIds]);
      }
      
      setLastUpdate(new Date());
    } catch (err) {
      setError('Failed to fetch tracking data');
      console.error('Error fetching tracking data:', err);
    } finally {
      if (showLoading) {
        setLoading(false);
      }
    }
  }, [calculateSecondaryMetrics, updateVehiclePosition]);

  // Handle realtime tracking update
  const handleTrackingUpdate = useCallback((update: TrackingUpdateMessage) => {
    // Update vehicle position in state
    setVehicles(prevVehicles => {
      return prevVehicles.map(vehicle => {
        if (vehicle.id === update.vehicle_id) {
          // Update vehicle trail
          updateVehiclePosition(update.vehicle_id, update.location.latitude, update.location.longitude);
          
          return {
            ...vehicle,
            latitude: update.location.latitude,
            longitude: update.location.longitude,
            speed: update.telemetry.speed,
            heading: update.telemetry.heading,
            status: update.status.movement_status.toLowerCase() as any, // Type assertion for compatibility
            signalQuality: update.telemetry.signal_quality === 1 ? 'good' : update.telemetry.signal_quality === 2 ? 'weak' : 'lost',
            lastUpdate: update.timestamp,
          };
        }
        return vehicle;
      });
    });
    
    setLastUpdate(new Date());
  }, [updateVehiclePosition]);

  const startPolling = useCallback(() => {
    if (intervalRef.current) {
      return; // Already polling
    }
    
    setIsLive(true);
    
    // Initial fetch
    fetchData(true);
    
    // Set up polling interval
    intervalRef.current = setInterval(() => {
      fetchData(false); // Don't show loading during polling
    }, POLLING_INTERVAL);
  }, [fetchData]);

  const stopPolling = useCallback(() => {
    if (intervalRef.current) {
      clearInterval(intervalRef.current);
      intervalRef.current = null;
    }
    setIsLive(false);
  }, []);

  // Start realtime connection
  const startRealtime = useCallback(async () => {
    try {
      setConnectionStatus('connecting');
      await realtimeConnectionService.connect();
      
      // Subscribe to fleet channel
      await trackingSubscriptionManager.subscribeFleet({
        onUpdate: handleTrackingUpdate,
        onSubscribed: () => {
          setConnectionStatus('connected');
          setIsRealtime(true);
          isRealtimeRef.current = true;
          // Stop polling when realtime is active
          stopPolling();
        },
        onError: (error) => {
          console.error('Subscription error:', error);
          setConnectionStatus('error');
          // Fall back to polling
          startPolling();
        },
      });
    } catch (error) {
      console.error('Connection error:', error);
      setConnectionStatus('error');
      // Fall back to polling
      startPolling();
    }
  }, [handleTrackingUpdate, stopPolling, startPolling]);

  // Stop realtime connection
  const stopRealtime = useCallback(async () => {
    await trackingSubscriptionManager.unsubscribe();
    await realtimeConnectionService.disconnect();
    setConnectionStatus('disconnected');
    setIsRealtime(false);
    isRealtimeRef.current = false;
  }, []);


  const refresh = useCallback(async () => {
    if (isRealtimeRef.current) {
      // If realtime is active, just refresh the data
      await fetchData(true);
    } else {
      // If polling is active, refresh will trigger a fetch
      await fetchData(true);
    }
  }, [fetchData]);

  // Monitor connection status and handle fallback
  useEffect(() => {
    const checkInterval = setInterval(() => {
      const currentStatus = realtimeConnectionService.state.status;
      
      if (currentStatus !== connectionStatusRef.current) {
        connectionStatusRef.current = currentStatus;
        setConnectionStatus(currentStatus);
        
        // Handle connection state changes
        if (currentStatus === 'connected' && !isRealtimeRef.current) {
          // Connection recovered, try to restart realtime
          startRealtime();
        } else if (currentStatus === 'error' || currentStatus === 'disconnected') {
          if (isRealtimeRef.current) {
            // Connection lost, fall back to polling
            isRealtimeRef.current = false;
            setIsRealtime(false);
            startPolling();
          }
        }
      }
    }, 1000);
    
    return () => clearInterval(checkInterval);
  }, [startRealtime, startPolling]);

  // Start realtime connection on mount
  useEffect(() => {
    startRealtime();
    
    return () => {
      stopRealtime();
      stopPolling();
    };
  }, [startRealtime, stopRealtime, stopPolling]);

  return {
    vehicles,
    stats,
    events,
    loading,
    error,
    lastUpdate,
    isLive,
    connectionStatus,
    isRealtime,
    startPolling,
    stopPolling,
    refresh,
    getVehicleTrail,
  };
}
