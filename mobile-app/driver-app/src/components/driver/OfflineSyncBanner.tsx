import React from 'react';
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import {
  AlertCircle,
  CheckCircle2,
  CloudOff,
  RefreshCw,
} from 'lucide-react-native';

import { useOfflineSyncStatus } from '@/lib/network/useOfflineSyncStatus';
import { syncManager } from '@/lib/network/syncManager';
import { DriverTheme } from './theme';

export function OfflineSyncBanner() {
  const status = useOfflineSyncStatus();
  const { uiState, pendingCount, inFlightCount, failedCount, completedCount, totalInSession, isOnline } = status;

  const totalPending = pendingCount + inFlightCount;

  if (uiState === 'idle' && isOnline && failedCount === 0 && totalPending === 0) {
    return null;
  }

  const handleRetry = () => {
    void syncManager.flushQueue();
  };

  if (uiState === 'offline' || (!isOnline && totalPending > 0)) {
    return (
      <View
        style={[styles.banner, styles.bannerOffline]}
        accessibilityRole="summary"
        accessibilityLabel={`Offline mode: ${totalPending} actions waiting to sync`}>
        <View style={styles.contentRow}>
          <CloudOff size={18} color="#F59E0B" />
          <View style={styles.textStack}>
            <Text style={styles.titleText}>Offline</Text>
            <Text style={styles.detailText}>
              {totalPending === 1
                ? '1 action waiting to sync'
                : `${totalPending} actions waiting to sync`}
            </Text>
          </View>
        </View>
      </View>
    );
  }

  if (uiState === 'syncing') {
    const totalToSync = totalInSession || totalPending || 1;
    const completed = Math.min(completedCount, totalToSync);
    return (
      <View
        style={[styles.banner, styles.bannerSyncing]}
        accessibilityRole="summary"
        accessibilityLabel={`Synchronizing: ${completed} of ${totalToSync} actions uploaded`}>
        <View style={styles.contentRow}>
          <ActivityIndicator size="small" color="#60A5FA" />
          <View style={styles.textStack}>
            <Text style={styles.titleTextSync}>Synchronizing...</Text>
            <Text style={styles.detailTextSync}>
              {`${completed} of ${totalToSync} actions uploaded`}
            </Text>
          </View>
        </View>
      </View>
    );
  }

  if (uiState === 'success') {
    return (
      <View
        style={[styles.banner, styles.bannerSuccess]}
        accessibilityRole="summary"
        accessibilityLabel="All actions synchronized successfully">
        <View style={styles.contentRow}>
          <CheckCircle2 size={18} color="#34D399" />
          <Text style={styles.titleTextSuccess}>All actions synchronized</Text>
        </View>
      </View>
    );
  }

  if (uiState === 'failed' || failedCount > 0) {
    return (
      <View
        style={[styles.banner, styles.bannerFailed]}
        accessibilityRole="summary"
        accessibilityLabel={`${failedCount} actions failed to sync. Tap to retry.`}>
        <View style={styles.contentRow}>
          <AlertCircle size={18} color="#F87171" />
          <View style={styles.textStack}>
            <Text style={styles.titleTextFailed}>
              {failedCount === 1 ? '1 action failed' : `${failedCount} actions failed`}
            </Text>
            <Text style={styles.detailTextFailed}>Tap to retry synchronization</Text>
          </View>
        </View>
        <Pressable
          style={styles.retryButton}
          onPress={handleRetry}
          accessibilityRole="button"
          accessibilityLabel="Retry synchronization">
          <RefreshCw size={14} color="#FFFFFF" />
          <Text style={styles.retryButtonText}>Retry</Text>
        </Pressable>
      </View>
    );
  }

  return null;
}

const styles = StyleSheet.create({
  banner: {
    paddingHorizontal: 16,
    paddingVertical: 10,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    zIndex: 100,
    borderBottomWidth: 1,
  },
  bannerOffline: {
    backgroundColor: '#1E1B18',
    borderColor: '#78350F',
  },
  bannerSyncing: {
    backgroundColor: '#0F172A',
    borderColor: '#1E3A8A',
  },
  bannerSuccess: {
    backgroundColor: '#062C1E',
    borderColor: '#065F46',
  },
  bannerFailed: {
    backgroundColor: '#2A0F0F',
    borderColor: '#7F1D1D',
  },
  contentRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    flex: 1,
  },
  textStack: {
    flexDirection: 'column',
    gap: 1,
  },
  titleText: {
    color: '#F59E0B',
    fontWeight: '700',
    fontSize: 13,
  },
  detailText: {
    color: '#D97706',
    fontSize: 12,
  },
  titleTextSync: {
    color: '#60A5FA',
    fontWeight: '700',
    fontSize: 13,
  },
  detailTextSync: {
    color: '#93C5FD',
    fontSize: 12,
  },
  titleTextSuccess: {
    color: '#34D399',
    fontWeight: '700',
    fontSize: 13,
  },
  titleTextFailed: {
    color: '#F87171',
    fontWeight: '700',
    fontSize: 13,
  },
  detailTextFailed: {
    color: '#FCA5A5',
    fontSize: 12,
  },
  retryButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    backgroundColor: '#991B1B',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#EF4444',
  },
  retryButtonText: {
    color: '#FFFFFF',
    fontWeight: '700',
    fontSize: 12,
  },
});
