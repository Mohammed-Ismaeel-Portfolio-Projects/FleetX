import { useCallback, useState } from 'react';
import { FlatList, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { useFocusEffect, useRouter } from 'expo-router';
import { MessageCircle } from 'lucide-react-native';

import {
  ConversationRow,
  DriverHeader,
  DriverScreen,
  EmptyState,
} from '@/components/driver';
import { DriverTheme } from '@/components/driver/theme';
import { useAuth } from '@/lib/auth/AuthContext';
import { listConversations } from '@/features/driverApi/messaging';
import type { Conversation } from '@/features/messaging/types';
import { ScreenBlocked, ScreenError, ScreenLoading } from '@/lib/ux/ScreenState';

const uxTheme = {
  text: DriverTheme.colors.textPrimary,
  textSecondary: DriverTheme.colors.textSecondary,
  primary: DriverTheme.colors.primary,
};

export default function MessagesScreen() {
  const { roleOk, identityLinked } = useAuth();
  const router = useRouter();
  const [items, setItems] = useState<Conversation[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async (isRefresh = false) => {
    if (!roleOk || !identityLinked) {
      setItems([]);
      setLoading(false);
      return;
    }
    if (isRefresh) setRefreshing(true);
    else setLoading(true);
    setError(null);
    try {
      const { conversations } = await listConversations({
        type: 'dispatcher_driver',
        per_page: 50,
      });
      const sorted = [...conversations].sort((a, b) => {
        if (a.status === 'open' && b.status !== 'open') return -1;
        if (b.status === 'open' && a.status !== 'open') return 1;
        const at = a.updatedAt ? Date.parse(a.updatedAt) : 0;
        const bt = b.updatedAt ? Date.parse(b.updatedAt) : 0;
        return bt - at;
      });
      setItems(sorted);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load conversations');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [roleOk, identityLinked]);

  useFocusEffect(
    useCallback(() => {
      void load();
    }, [load])
  );

  const header = <DriverHeader title="Messages" />;

  if (!roleOk) {
    return (
      <DriverScreen header={header}>
        <ScreenBlocked
          title="Messaging unavailable"
          body="Messaging unavailable — role mismatch."
          theme={uxTheme}
        />
      </DriverScreen>
    );
  }

  if (!identityLinked) {
    return (
      <DriverScreen header={header}>
        <ScreenBlocked
          title="Driver linking unavailable"
          body="Your user is not linked to a Driver record. Conversations cannot be loaded."
          theme={uxTheme}
        />
      </DriverScreen>
    );
  }

  if (loading) {
    return (
      <DriverScreen header={header}>
        <ScreenLoading label="Loading conversations…" theme={uxTheme} />
      </DriverScreen>
    );
  }

  return (
    <DriverScreen header={header} testID="messages-screen">
      {error ? (
        <View style={styles.errorWrap}>
          <ScreenError testID="messages-error" title="Could not load conversations" body={error} theme={uxTheme} />
        </View>
      ) : null}
      <FlatList
        data={items}
        keyExtractor={(item) => item.id}
        contentContainerStyle={items.length === 0 ? styles.emptyList : styles.list}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={() => void load(true)}
            tintColor={DriverTheme.colors.primary}
            colors={[DriverTheme.colors.primary]}
          />
        }
        ListEmptyComponent={
          <EmptyState
            testID="messages-empty"
            icon={
              <MessageCircle
                size={36}
                color={DriverTheme.colors.primary}
                accessibilityLabel="No conversations"
              />
            }
            title="No dispatcher conversations yet."
            body="Threads appear here when a dispatcher starts a conversation about your shipment."
          />
        }
        renderItem={({ item }) => (
          <ConversationRow
            item={item}
            onPress={() => router.push(`/conversation/${item.id}`)}
            onShipmentPress={() => router.push(`/shipment/${item.shipmentId}`)}
          />
        )}
        ItemSeparatorComponent={() => <View style={styles.separator} />}
        ListFooterComponent={
          items.length > 0 ? (
            <Text style={styles.footerNote}>
              Conversations are started by your dispatcher. This is operational messaging only.
            </Text>
          ) : null
        }
      />
    </DriverScreen>
  );
}

const styles = StyleSheet.create({
  errorWrap: {
    paddingHorizontal: DriverTheme.spacing.lg,
    paddingTop: DriverTheme.spacing.md,
  },
  list: {
    paddingHorizontal: DriverTheme.spacing.lg,
    paddingTop: DriverTheme.spacing.lg,
    paddingBottom: DriverTheme.spacing.xxl,
  },
  emptyList: {
    flexGrow: 1,
    justifyContent: 'center',
    paddingHorizontal: DriverTheme.spacing.lg,
  },
  separator: {
    height: DriverTheme.spacing.md,
  },
  footerNote: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.textMuted,
    textAlign: 'center',
    paddingHorizontal: DriverTheme.spacing.md,
    paddingTop: DriverTheme.spacing.xl,
    paddingBottom: DriverTheme.spacing.lg,
  },
});
