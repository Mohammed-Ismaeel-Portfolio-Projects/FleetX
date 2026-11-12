import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { communicationService } from '../services/communication.service';
import type { CommunicationViewModel } from '../types/communication.types';
import type { Driver } from '../../drivers/types/driver-operations.types';
import { shipmentsService } from '../../shipments/services/shipments.service';
import type { Shipment } from '../../shipments/types/shipment.types';

const POLL_INTERVAL_MS = 15_000;

export function useCommunication(options?: { contextShipmentId?: string | null }) {
  const contextShipmentId = options?.contextShipmentId ?? null;  const [viewModel, setViewModel] = useState<CommunicationViewModel | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [search, setSearch] = useState('');
  const [availabilityFilter, setAvailabilityFilter] = useState('');
  const [selectedDriverId, setSelectedDriverId] = useState<string | null>(null);
  const [shipmentContext, setShipmentContext] = useState<Shipment | null>(null);
  const [shipmentContextError, setShipmentContextError] = useState<string | null>(null);
  const [shipmentContextLoading, setShipmentContextLoading] = useState(false);

  const requestIdRef = useRef(0);
  const inFlightRef = useRef(false);
  const pendingRef = useRef<{ silent: boolean } | null>(null);
  const hasLoadedRef = useRef(false);
  const viewModelRef = useRef<CommunicationViewModel | null>(null);
  const shipmentRequestRef = useRef(0);
  viewModelRef.current = viewModel;

  const loadData = useCallback(async (options?: { silent?: boolean }) => {
    if (inFlightRef.current) {
      const nextSilent = Boolean(options?.silent);
      const pending = pendingRef.current;
      pendingRef.current = {
        silent: pending ? pending.silent && nextSilent : nextSilent,
      };
      return;
    }

    inFlightRef.current = true;
    const requestId = ++requestIdRef.current;
    const hadData = viewModelRef.current != null;
    const silent = Boolean(options?.silent) && hasLoadedRef.current;

    if (!silent) {
      if (!hadData) setIsLoading(true);
      else setIsRefreshing(true);
    }

    try {
      const data = await communicationService.getViewModel();
      if (requestId !== requestIdRef.current) return;

      setViewModel(data);
      setError(null);
      hasLoadedRef.current = true;

      // Preserve selection only — never auto-select first driver (avoids surprise messaging).
      setSelectedDriverId((current) => {
        if (current && data.drivers.some((d) => d.id === current)) return current;
        if (current && !data.drivers.some((d) => d.id === current)) return null;
        return current;
      });
    } catch (err) {
      if (requestId !== requestIdRef.current) return;
      const message = err instanceof Error ? err.message : 'Unable to load communication center';
      setError(message);
      if (!viewModelRef.current) setViewModel(null);
    } finally {
      if (requestId === requestIdRef.current) {
        inFlightRef.current = false;
        setIsLoading(false);
        setIsRefreshing(false);
        const pending = pendingRef.current;
        if (pending) {
          pendingRef.current = null;
          void loadData({ silent: pending.silent });
        }
      } else {
        inFlightRef.current = false;
      }
    }
  }, []);

  useEffect(() => {
    void loadData({ silent: false });
  }, [loadData]);

  useEffect(() => {
    let intervalId: number | null = null;

    const clearPolling = () => {
      if (intervalId !== null) {
        window.clearInterval(intervalId);
        intervalId = null;
      }
    };

    const triggerPoll = () => void loadData({ silent: true });

    const startPolling = () => {
      if (intervalId !== null || document.hidden) return;
      intervalId = window.setInterval(() => {
        if (!document.hidden) triggerPoll();
      }, POLL_INTERVAL_MS);
    };

    const onVisibilityChange = () => {
      if (document.hidden) {
        clearPolling();
        return;
      }
      triggerPoll();
      startPolling();
    };

    startPolling();
    document.addEventListener('visibilitychange', onVisibilityChange);
    return () => {
      clearPolling();
      document.removeEventListener('visibilitychange', onVisibilityChange);
      requestIdRef.current += 1;
    };
  }, [loadData]);

  const filteredDrivers = useMemo(() => {
    if (!viewModel) return [] as Driver[];
    const q = search.trim().toLowerCase();
    return viewModel.drivers.filter((driver) => {
      if (availabilityFilter) {
        const avail = (driver.dispatchAvailability || driver.status || '').toLowerCase();
        if (avail !== availabilityFilter.toLowerCase()) return false;
      }
      if (!q) return true;
      const hay = [
        driver.name,
        driver.employeeNumber,
        driver.phone,
        driver.email,
        driver.assignment?.shipmentNumber,
        driver.assignment?.vehiclePlate,
      ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();
      return hay.includes(q);
    });
  }, [availabilityFilter, search, viewModel]);

  const selectedDriver = useMemo(() => {
    if (!viewModel || !selectedDriverId) return null;
    return viewModel.drivers.find((d) => d.id === selectedDriverId) ?? null;
  }, [selectedDriverId, viewModel]);

  // Shipment site contacts: prefer active conversation shipment, else selected driver assignment.
  useEffect(() => {
    const shipmentId =
      contextShipmentId || selectedDriver?.assignment?.shipmentId || null;
    const requestId = ++shipmentRequestRef.current;

    if (!shipmentId) {
      setShipmentContext(null);
      setShipmentContextError(null);
      setShipmentContextLoading(false);
      return;
    }

    setShipmentContextLoading(true);
    // Keep prior error cleared only when switching ids; preserve LKG body on soft reload.
    setShipmentContextError(null);

    void shipmentsService
      .getShipmentById(shipmentId)
      .then((shipment) => {
        if (requestId !== shipmentRequestRef.current) return;
        if (!shipment) {
          setShipmentContext((prev) => (prev?.id === shipmentId ? prev : null));
          setShipmentContextError('Shipment context unavailable');
          return;
        }
        setShipmentContext(shipment);
        setShipmentContextError(null);
      })
      .catch((err) => {
        if (requestId !== shipmentRequestRef.current) return;
        setShipmentContext((prev) => (prev?.id === shipmentId ? prev : null));
        setShipmentContextError(
          err instanceof Error ? err.message : 'Failed to load shipment contacts'
        );
      })
      .finally(() => {
        if (requestId === shipmentRequestRef.current) {
          setShipmentContextLoading(false);
        }
      });
  }, [contextShipmentId, selectedDriver?.assignment?.shipmentId]);

  return {
    viewModel,
    isLoading,
    isRefreshing,
    error,
    search,
    setSearch,
    availabilityFilter,
    setAvailabilityFilter,
    selectedDriverId,
    setSelectedDriverId,
    selectedDriver,
    filteredDrivers,
    shipmentContext,
    shipmentContextError,
    shipmentContextLoading,
    refreshData: () => void loadData({ silent: false }),
  };
}
