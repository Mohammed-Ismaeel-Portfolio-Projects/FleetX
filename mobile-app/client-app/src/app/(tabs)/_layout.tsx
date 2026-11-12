// @ts-nocheck — lucide icon props vs RN ColorValue in Expo Router tabBarIcon
import { Redirect, Tabs } from 'expo-router';
import { ActivityIndicator, Pressable, StyleSheet } from 'react-native';
import { Home, Truck, FileText, MessageSquare, User } from 'lucide-react-native';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Colors, Spacing } from '@/constants/theme';
import { useAuth } from '@/lib/auth/AuthContext';
import { useColorScheme } from '@/hooks/use-color-scheme';
import { AppConfig } from '@/lib/config';

export default function TabsLayout() {
  const { ready, isAuthenticated, roleOk, user, identity, logout } = useAuth();
  const scheme = useColorScheme() ?? 'light';
  const colors = Colors[scheme === 'unspecified' ? 'light' : scheme];

  if (!ready) {
    return (
      <ThemedView style={styles.center} testID="tabs-auth-loading">
        <ActivityIndicator />
      </ThemedView>
    );
  }

  if (!isAuthenticated) {
    return <Redirect href="/login" />;
  }

  if (!roleOk) {
    const roleName = user?.role?.name ?? identity?.role ?? 'unknown';
    return (
      <ThemedView style={styles.centerPad} testID="role-mismatch-gate">
        <ThemedText type="subtitle">Wrong account type</ThemedText>
        <ThemedText style={styles.centerText}>
          This FleetX Client App is for accounts with role &quot;{AppConfig.appRole}&quot;. Your
          signed-in role is &quot;{roleName}&quot;. Client messaging and product features are not
          available for this account.
        </ThemedText>
        <Pressable
          testID="role-mismatch-logout"
          style={[styles.logoutBtn, { backgroundColor: colors.primary }]}
          onPress={() => void logout()}
          accessibilityRole="button"
          accessibilityLabel="Sign out"
          accessibilityHint="Signs out of this account because it is the wrong account type for the Client App">
          <ThemedText style={styles.logoutText}>Sign out</ThemedText>
        </Pressable>
      </ThemedView>
    );
  }

  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarStyle: {
          backgroundColor: '#0B1220',
          borderTopColor: '#1E293B',
          borderTopWidth: 1.5,
          height: 64,
          paddingBottom: 10,
          paddingTop: 10,
        },
        tabBarActiveTintColor: colors.primary,
        tabBarInactiveTintColor: colors.textSecondary,
        tabBarLabelStyle: {
          fontSize: 11,
          fontWeight: '600',
        },
      }}>
      <Tabs.Screen
        name="index"
        options={{
          title: 'Home',
          tabBarLabel: 'Home',
          tabBarIcon: ({ color, size }) => <Home size={size} color={String(color)} />,
        }}
      />
      <Tabs.Screen
        name="requests"
        options={{
          title: 'Requests',
          tabBarLabel: 'Requests',
          tabBarIcon: ({ color, size }) => <FileText size={size} color={String(color)} />,
        }}
      />
      <Tabs.Screen
        name="shipments"
        options={{
          title: 'Shipments',
          tabBarLabel: 'Shipments',
          tabBarIcon: ({ color, size }) => <Truck size={size} color={String(color)} />,
        }}
      />
      <Tabs.Screen
        name="messages"
        options={{
          title: 'Messages',
          tabBarLabel: 'Messages',
          tabBarIcon: ({ color, size }) => <MessageSquare size={size} color={String(color)} />,
        }}
      />
      <Tabs.Screen
        name="profile"
        options={{
          title: 'Profile',
          tabBarLabel: 'Profile',
          tabBarIcon: ({ color, size }) => <User size={size} color={String(color)} />,
        }}
      />
      {/* Deep-link compatibility: former Tracking tab is not a primary destination. */}
      <Tabs.Screen
        name="tracking"
        options={{
          href: null,
        }}
      />
    </Tabs>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  centerPad: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: Spacing.four,
  },
  centerText: {
    textAlign: 'center',
    marginTop: Spacing.two,
    marginBottom: Spacing.three,
    opacity: 0.85,
  },
  logoutBtn: {
    paddingHorizontal: 20,
    paddingVertical: 12,
    borderRadius: 12,
  },
  logoutText: {
    color: '#FFFFFF',
    fontWeight: '700',
  },
});
