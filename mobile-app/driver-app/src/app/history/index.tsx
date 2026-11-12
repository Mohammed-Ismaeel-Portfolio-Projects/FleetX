import { useCallback, useState } from 'react';
import { ActivityIndicator, FlatList, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { useFocusEffect, useRouter, type Href } from 'expo-router';
import { ClipboardCheck } from 'lucide-react-native';

import { OfflineBanner } from '@/components/OfflineBanner';
import {
  DriverHeader,
  DriverScreen,
  EmptyState,
  HistoryCard,
  PrimaryButton,
} from '@/components/driver';
import { DriverTheme } from '@/components/driver/theme';
import { listAssignmentHistory } from '@/features/driverApi/history';
import type { DriverHistoryListItem } from '@/features/driverApi/historyTypes';
import { DriverWorkApiError, isRetryableWorkError } from '@/features/driverApi/workTypes';
import { useAuth } from '@/lib/auth/AuthContext';
import { useNetworkStatus } from '@/lib/network/useNetworkStatus';
import { ScreenEmpty, ScreenError, ScreenLoading } from '@/lib/ux/ScreenState';

const uxTheme = {
  text: DriverTheme.colors.textPrimary,
  textSecondary: DriverTheme.colors.textSecondary,
  primary: DriverTheme.colors.primary,
};

/**
 * Assignment History list (D11) — read-only completed/cancelled trips.
 */
export default function AssignmentHistoryScreen() {
  const router = useRouter();
  const { canOperate } = useAuth();
  const { online } = useNetworkStatus();

  const [status, setStatus] = useState<'loading' | 'ready' | 'error'>('loading');
  const [items, setItems] = useState<DriverHistoryListItem[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [error, setError] = useState<string | null>(null);
  const [retryable, setRetryable] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [loadingMore, setLoadingMore] = useState(false);

  const load = useCallback(
    async (nextPage: number, replace: boolean) => {
      if (!canOperate) {
        setStatus('error');
        setRetryable(false);
        setError('Assignment history is unavailable for this account.');
        return;
      }
      if (replace) setStatus('loading');
      setError(null);
      try {
        const result = await listAssignmentHistory(nextPage);
        setItems((prev) => (replace ? result.items : [...prev, ...result.items]));
        setPage(result.page);
        setLastPage(result.lastPage);
        setStatus('ready');
      } catch (e) {
        setRetryable(isRetryableWorkError(e));
        setError(
          e instanceof DriverWorkApiError ? e.message : 'Could not load assignment history.'
        );
        if (replace) setStatus('error');
      }
    },
    [canOperate]
  );

  useFocusEffect(
    useCallback(() => {
      void load(1, true);
    }, [load])
  );

  const onRefresh = async () => {
    setRefreshing(true);
    try {
      await load(1, true);
    } finally {
      setRefreshing(false);
    }
  };

  const onEndReached = () => {
    if (loadingMore || status !== 'ready' || page >= lastPage) return;
    setLoadingMore(true);
    void load(page + 1, false).finally(() => setLoadingMore(false));
  };

  const header = (
    <DriverHeader title="Assignment History" onBack={() => router.back()} />
  );

  if (status === 'loading') {
    return (
      <DriverScreen header={header} safeBottom>
        <ScreenLoading testID="history-loading" label="Loading history…" theme={uxTheme} />
      </DriverScreen>
    );
  }

  if (status === 'error') {
    return (
      <DriverScreen header={header} padded safeBottom>
        <View style={styles.errorWrap}>
          <ScreenError
            testID="history-error"
            retryTestID="history-retry"
            title="Could not load history"
            body={error || 'Could not load assignment history.'}
            onRetry={retryable ? () => void load(1, true) : null}
            theme={uxTheme}
          />
        </View>
      </DriverScreen>
    );
  }

  return (
    <DriverScreen header={header} testID="history-screen" safeBottom>
      <View style={styles.intro}>
        <Text style={styles.introBody}>
          Completed and cancelled trips. Current work stays on Work.
        </Text>
      </View>
      {!online ? <OfflineBanner testID="history-offline" /> : null}
      {items.length === 0 ? (
        <View style={styles.emptyWrap}>
          <EmptyState
            icon={
              <ClipboardCheck
                size={36}
                color={DriverTheme.colors.primary}
                accessibilityLabel="No completed assignments"
              />
            }>
            <ScreenEmpty
              testID="history-empty"
              title="No completed assignments yet."
              body="Completed and failed trips will appear here."
              theme={uxTheme}
            />
          </EmptyState>
        </View>
      ) : (
        <FlatList
          data={items}
          keyExtractor={(item) => String(item.id)}
          style={styles.flex}
          contentContainerStyle={styles.list}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={() => void onRefresh()}
              tintColor={DriverTheme.colors.primary}
              colors={[DriverTheme.colors.primary]}
            />
          }
          onEndReached={onEndReached}
          onEndReachedThreshold={0.4}
          ItemSeparatorComponent={() => <View style={styles.separator} />}
          ListFooterComponent={
            loadingMore ? (
              <ActivityIndicator color={DriverTheme.colors.primary} style={styles.more} />
            ) : null
          }
          renderItem={({ item }) => (
            <HistoryCard
              item={item}
              onPress={() => router.push(`/history/${item.id}` as Href)}>
              <PrimaryButton
                testID={`history-view-summary-${item.id}`}
                label="View Summary"
                onPress={() => router.push(`/history/${item.id}` as Href)}
                accessibilityLabel="View Summary"
              />
            </HistoryCard>
          )}
        />
      )}
    </DriverScreen>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1 },
  intro: {
    paddingHorizontal: DriverTheme.spacing.lg,
    paddingTop: DriverTheme.spacing.md,
    paddingBottom: DriverTheme.spacing.sm,
  },
  introBody: {
    ...DriverTheme.typography.body,
    color: DriverTheme.colors.textSecondary,
  },
  errorWrap: {
    flex: 1,
    justifyContent: 'center',
  },
  emptyWrap: {
    flex: 1,
    justifyContent: 'center',
  },
  list: {
    paddingHorizontal: DriverTheme.spacing.lg,
    paddingBottom: DriverTheme.spacing.xxl,
  },
  separator: {
    height: DriverTheme.spacing.md,
  },
  more: {
    marginVertical: DriverTheme.spacing.md,
  },
});
