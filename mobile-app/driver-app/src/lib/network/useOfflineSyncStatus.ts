import { useEffect, useState } from 'react';
import { syncManager, type OfflineSyncStatus } from './syncManager';

export function useOfflineSyncStatus(): OfflineSyncStatus {
  const [status, setStatus] = useState<OfflineSyncStatus>({
    isOnline: true,
    uiState: 'idle',
    pendingCount: 0,
    inFlightCount: 0,
    failedCount: 0,
    syncedCount: 0,
    totalCount: 0,
    completedCount: 0,
    totalInSession: 0,
  });

  useEffect(() => {
    const unsubscribe = syncManager.subscribeStatus((newStatus) => {
      setStatus(newStatus);
    });
    return () => unsubscribe();
  }, []);

  return status;
}
