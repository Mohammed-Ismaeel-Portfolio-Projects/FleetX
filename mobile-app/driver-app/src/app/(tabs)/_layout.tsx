import { useCallback, useEffect, useState } from 'react';
import { Redirect, Tabs } from 'expo-router';
import { ActivityIndicator, StyleSheet, View } from 'react-native';

import { DriverTabBar } from '@/components/driver';
import { DriverTheme } from '@/components/driver/theme';
import { getUnreadNotificationCount } from '@/features/driverApi/notifications';
import { useAuth } from '@/lib/auth/AuthContext';

export default function TabsLayout() {
  const { ready, isAuthenticated, canOperate } = useAuth();
  const [unread, setUnread] = useState(0);

  const refreshUnread = useCallback(async () => {
    if (!canOperate) {
      setUnread(0);
      return;
    }
    try {
      setUnread(await getUnreadNotificationCount());
    } catch {
      /* badge is best-effort */
    }
  }, [canOperate]);

  useEffect(() => {
    if (!isAuthenticated || !canOperate) return;
    let cancelled = false;
    const tick = () => {
      if (!cancelled) void refreshUnread();
    };
    const initial = setTimeout(tick, 0);
    const timer = setInterval(tick, 30000);
    return () => {
      cancelled = true;
      clearTimeout(initial);
      clearInterval(timer);
    };
  }, [canOperate, isAuthenticated, refreshUnread]);

  if (!ready) {
    return (
      <View style={styles.center}>
        <ActivityIndicator color={DriverTheme.colors.primary} />
      </View>
    );
  }

  if (!isAuthenticated) {
    return <Redirect href="/login" />;
  }

  return (
    <Tabs
      tabBar={(props) => <DriverTabBar {...props} />}
      screenOptions={{
        headerShown: false,
      }}>
      <Tabs.Screen name="index" options={{ title: 'Work', tabBarLabel: 'Work' }} />
      <Tabs.Screen name="messages" options={{ title: 'Messages', tabBarLabel: 'Messages' }} />
      <Tabs.Screen
        name="notifications"
        listeners={{ focus: () => void refreshUnread() }}
        options={{
          title: 'Alerts',
          tabBarLabel: 'Alerts',
          tabBarBadge: unread > 0 ? (unread > 99 ? '99+' : unread) : undefined,
        }}
      />
      <Tabs.Screen name="account" options={{ title: 'Account', tabBarLabel: 'Account' }} />
    </Tabs>
  );
}

const styles = StyleSheet.create({
  center: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: DriverTheme.colors.background,
  },
});
