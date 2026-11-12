import { useState } from 'react';
import type { ShipmentStatus, ShipmentPriority } from '../types/shipment.types';

export function useShipmentFilters() {
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState<ShipmentStatus | 'All'>('All');
  const [priorityFilter, setPriorityFilter] = useState<ShipmentPriority | 'All'>('All');
  const [regionFilter, setRegionFilter] = useState<string | 'All'>('All');

  const resetFilters = () => {
    setSearchQuery('');
    setStatusFilter('All');
    setPriorityFilter('All');
    setRegionFilter('All');
  };

  return {
    searchQuery,
    setSearchQuery,
    statusFilter,
    setStatusFilter,
    priorityFilter,
    setPriorityFilter,
    regionFilter,
    setRegionFilter,
    resetFilters
  };
}
export type UseShipmentFiltersReturn = ReturnType<typeof useShipmentFilters>;
