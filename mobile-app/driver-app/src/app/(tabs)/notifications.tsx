import { useCallback, useState } from 'react';
import { FlatList, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { useFocusEffect, useRouter, type Href } from 'expo-router';
import { Bell } from 'lucide-react-native';

import {
  DriverHeader,
  DriverScreen,
  EmptyState,
  NotificationItem,
} from '@/components/driver';
import { DriverTheme } from '@/components/driver/theme';
import {
  listNotifications,
  markAllNotificationsRead,
  markNotificationRead,
  notificationHref,
  type DriverNotificationItem,
} from '@/features/driverApi/notifications';
import {
  getNotificationPresentation,
  notificationRowAccessibilityLabel,
} from '@/features/notifications/notificationPresentation';
import { DriverWorkApiError, isRetryableWorkError } from '@/features/driverApi/workTypes';
import { useAuth } from '@/lib/auth/AuthContext';
import { ScreenEmpty, ScreenError, ScreenLoading } from '@/lib/ux/ScreenState';

const uxTheme = {
  text: DriverTheme.colors.textPrimary,
  textSecondary: DriverTheme.colors.textSecondary,
  primary: DriverTheme.colors.primary,
};

export default function NotificationsScreen() {
  const router = useRouter();
  const { canOperate } = useAuth();

  const [status, setStatus] = useState<'loading' | 'ready' | 'error'>('loading');
  const [items, setItems] = useState<DriverNotificationItem[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [retryable, setRetryable] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    if (!canOperate) {
      setStatus('error');
      setRetryable(false);
      setError('Notifications are unavailable for this account.');
      return;
    }
    setStatus('loading');
    setError(null);
    try {
      const result = await listNotifications(1);
      setItems(result.items);
      setStatus('ready');
    } catch (e) {
      setRetryable(isRetryableWorkError(e));
      setError(e instanceof DriverWorkApiError ? e.message : 'Could not load notifications.');
      setStatus('error');
    }
  }, [canOperate]);

  useFocusEffect(
    useCallback(() => {
      void load();
    }, [load])
  );

  const onRefresh = async () => {
    setRefreshing(true);
    try {
      const result = await listNotifications(1);
      setItems(result.items);
      setStatus('ready');
    } catch {
      /* keep list */
    } finally {
      setRefreshing(false);
    }
  };

  const openItem = async (item: DriverNotificationItem) => {
    try {
      if (!item.isRead) {
        await markNotificationRead(item.id);
        setItems((prev) =>
          prev.map((n) => (n.id === item.id ? { ...n, isRead: true } : n))
        );
      }
    } catch {
      /* navigation still allowed */
    }
    router.push(notificationHref(item) as Href);
  };

  const header = (
    <DriverHeader
      title="Alerts"
      rightAction={
        items.some((i) => !i.isRead) ? (
          <Pressable
            testID="notifications-mark-all"
            onPress={() => void markAllNotificationsRead().then(() => load())}
            accessibilityRole="button"
            accessibilityLabel="Mark all read"
            hitSlop={8}
            style={styles.markAll}>
            <Text style={styles.markAllText}>Mark all read</Text>
          </Pressable>
        ) : null
      }
    />
  );

  if (status === 'loading') {
    return (
      <DriverScreen header={<DriverHeader title="Alerts" />}>
        <ScreenLoading testID="notifications-loading" label="Loading notifications…" theme={uxTheme} />
      </DriverScreen>
    );
  }

  if (status === 'error') {
    return (
      <DriverScreen header={<DriverHeader title="Alerts" />} padded>
        <View style={styles.errorWrap}>
          <ScreenError
            testID="notifications-error"
            retryTestID="notifications-retry"
            title="Could not load notifications"
            body={error || 'Could not load notifications.'}
            onRetry={retryable ? () => void load() : null}
            theme={uxTheme}
          />
        </View>
      </DriverScreen>
    );
  }

  return (
    <DriverScreen header={header} testID="notifications-screen">
      {items.length === 0 ? (
        <View style={styles.emptyWrap}>
          <EmptyState
            icon={
              <Bell
                size={36}
                color={DriverTheme.colors.primary}
                accessibilityLabel="No notifications"
              />
            }>
            <ScreenEmpty
              testID="notifications-empty"
              title="No notifications"
              body="Operational updates will appear here."
              theme={uxTheme}
            />
          </EmptyState>
        </View>
      ) : (
        <FlatList
          data={items}
          keyExtractor={(item) => String(item.id)}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={() => void onRefresh()}
              tintColor={DriverTheme.colors.primary}
              colors={[DriverTheme.colors.primary]}
            />
          }
          contentContainerStyle={styles.list}
          ItemSeparatorComponent={() => <View style={styles.separator} />}
          renderItem={({ item }) => {
            const presentation = getNotificationPresentation(item);
            return (
              <NotificationItem
                testID={`notification-row-${item.id}`}
                title={item.title}
                body={item.body}
                createdAt={item.createdAt}
                isRead={item.isRead}
                presentation={presentation}
                onPress={() => void openItem(item)}
                accessibilityLabel={notificationRowAccessibilityLabel(item, presentation)}
              />
            );
          }}
        />
      )}
    </DriverScreen>
  );
}

const styles = StyleSheet.create({
  markAll: {
    minHeight: DriverTheme.control.iconButton,
    justifyContent: 'center',
    paddingHorizontal: DriverTheme.spacing.xs,
  },
  markAllText: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.textOnNavy,
    fontWeight: '700',
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
    paddingTop: DriverTheme.spacing.lg,
    paddingBottom: DriverTheme.spacing.xxl,
  },
  separator: {
    height: DriverTheme.spacing.md,
  },
});
