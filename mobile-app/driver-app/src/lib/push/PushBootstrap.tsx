import { useEffect } from 'react';
import { Platform } from 'react-native';
import { useRouter, type Href } from 'expo-router';

import { useAuth } from '@/lib/auth/AuthContext';
import { loadNativeNotifications } from '@/lib/push/nativeNotifications';
import { hrefFromDriverPushData, parseDriverPushData } from '@/lib/push/pushPayload';
import { syncDriverPushIfGranted } from '@/lib/push/pushRegistration';

/**
 * Registers (if already permitted) and routes notification taps through
 * allowlisted deep links. Destinations re-fetch; payload is never mutated.
 *
 * expo-notifications is not imported at module scope so Expo Go can boot.
 */
export function DriverPushBootstrap() {
  const { canOperate } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (!canOperate) return;
    void syncDriverPushIfGranted();
  }, [canOperate]);

  useEffect(() => {
    if (Platform.OS !== 'ios' && Platform.OS !== 'android') return undefined;

    let cancelled = false;
    let sub: { remove: () => void } | undefined;

    const openFromResponse = (response: {
      notification: { request: { content: { data?: unknown } } };
    } | null) => {
      if (!response) return;
      const data = parseDriverPushData(response.notification.request.content.data);
      router.push(hrefFromDriverPushData(data) as Href);
    };

    void (async () => {
      const Notifications = await loadNativeNotifications();
      if (!Notifications || cancelled) return;

      const last = await Notifications.getLastNotificationResponseAsync();
      if (cancelled) return;
      openFromResponse(last);

      sub = Notifications.addNotificationResponseReceivedListener((response) => {
        openFromResponse(response);
      });
      if (cancelled) {
        sub.remove();
        sub = undefined;
      }
    })();

    return () => {
      cancelled = true;
      sub?.remove();
    };
  }, [router]);

  return null;
}
