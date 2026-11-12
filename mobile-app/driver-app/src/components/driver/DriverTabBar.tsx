import type { ComponentProps } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { Tabs } from 'expo-router';
import { Bell, Briefcase, MessageCircle, UserCircle } from 'lucide-react-native';

import { DriverTheme } from './theme';

type DriverTabBarProps = Parameters<NonNullable<ComponentProps<typeof Tabs>['tabBar']>>[0];

const ICONS = {
  index: Briefcase,
  messages: MessageCircle,
  notifications: Bell,
  account: UserCircle,
} as const;

function routeIcon(name: string) {
  if (name === 'index') return ICONS.index;
  if (name === 'messages') return ICONS.messages;
  if (name === 'notifications') return ICONS.notifications;
  if (name === 'account') return ICONS.account;
  return Briefcase;
}

function routeLabel(
  name: string,
  options: { title?: string; tabBarLabel?: unknown }
) {
  if (typeof options.tabBarLabel === 'string' && options.tabBarLabel.trim()) {
    return options.tabBarLabel;
  }
  if (typeof options.title === 'string' && options.title.trim()) {
    return options.title;
  }
  if (name === 'index') return 'Work';
  if (name === 'messages') return 'Messages';
  if (name === 'notifications') return 'Alerts';
  if (name === 'account') return 'Account';
  return name;
}

export function DriverTabBar({ state, descriptors, navigation, insets }: DriverTabBarProps) {
  return (
    <View
      style={[
        styles.bar,
        { paddingBottom: Math.max(insets.bottom, DriverTheme.spacing.sm) },
      ]}
      accessibilityRole="tablist">
      {state.routes.map((route, index) => {
        const focused = state.index === index;
        const { options } = descriptors[route.key];
        const label = routeLabel(route.name, options);
        const Icon = routeIcon(route.name);
        const color = focused ? '#1E75FF' : '#64748B';
        const badge = options.tabBarBadge;
        const showBadge = badge !== undefined && badge !== null && badge !== 0 && badge !== '';

        const onPress = () => {
          const event = navigation.emit({
            type: 'tabPress',
            target: route.key,
            canPreventDefault: true,
          });
          if (!focused && !event.defaultPrevented) {
            navigation.navigate(route.name, route.params);
          }
        };

        const onLongPress = () => {
          navigation.emit({
            type: 'tabLongPress',
            target: route.key,
          });
        };

        return (
          <Pressable
            key={route.key}
            onPress={onPress}
            onLongPress={onLongPress}
            style={({ pressed }) => [styles.item, pressed && styles.pressed]}
            accessibilityRole="tab"
            accessibilityState={{ selected: focused }}
            accessibilityLabel={options.tabBarAccessibilityLabel ?? label}>
            <View style={styles.iconWrap}>
              <Icon size={22} color={color} strokeWidth={focused ? 2.4 : 2} />
              {showBadge ? (
                <View style={styles.badge} accessibilityLabel={`${String(badge)} unread alerts`}>
                  <Text style={styles.badgeText}>{String(badge)}</Text>
                </View>
              ) : null}
            </View>
            <Text style={[styles.label, { color, fontWeight: focused ? '700' : '500' }]}>{label}</Text>
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  bar: {
    flexDirection: 'row',
    backgroundColor: '#080B16',
    minHeight: 64,
    paddingTop: 8,
    borderTopWidth: 1,
    borderTopColor: 'rgba(255, 255, 255, 0.08)',
  },
  item: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 48,
    gap: 4,
  },
  pressed: {
    opacity: 0.85,
  },
  iconWrap: {
    position: 'relative',
  },
  label: {
    fontSize: 11,
    fontWeight: '600',
  },
  badge: {
    position: 'absolute',
    top: -6,
    right: -10,
    minWidth: 18,
    height: 18,
    borderRadius: 9,
    backgroundColor: DriverTheme.colors.danger,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 4,
  },
  badgeText: {
    color: DriverTheme.colors.textOnPrimary,
    fontSize: 10,
    fontWeight: '700',
  },
});
