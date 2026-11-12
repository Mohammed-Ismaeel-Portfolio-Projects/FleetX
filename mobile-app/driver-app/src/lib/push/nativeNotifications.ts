/**
 * Native remote-push loader (D12).
 *
 * Expo Go (SDK 53+) cannot provide Android remote notifications and throws if
 * `expo-notifications` is imported at module scope. Development and production
 * builds still load the real module. Never fabricate tokens or success.
 */

import { isRunningInExpoGo } from 'expo';
import { Platform } from 'react-native';

type NotificationsModule = typeof import('expo-notifications');

let nativeModule: NotificationsModule | null | undefined;

export function isExpoGoRuntime(): boolean {
  return isRunningInExpoGo();
}

export function isNativePushRuntime(): boolean {
  if (isExpoGoRuntime()) return false;
  return Platform.OS === 'ios' || Platform.OS === 'android';
}

export const EXPO_GO_PUSH_UNAVAILABLE_MESSAGE =
  'Remote trip alerts are not available in Expo Go. Use a development or production build. In-app Alerts still work.';

/**
 * Returns the real expo-notifications module, or null in Expo Go / non-native.
 * Loads the module only when native remote push is actually supported.
 */
export async function loadNativeNotifications(): Promise<NotificationsModule | null> {
  if (nativeModule !== undefined) return nativeModule;
  if (!isNativePushRuntime()) {
    nativeModule = null;
    return null;
  }
  const Notifications = await import('expo-notifications');
  Notifications.setNotificationHandler({
    handleNotification: async () => ({
      shouldShowAlert: true,
      shouldPlaySound: true,
      shouldSetBadge: false,
      shouldShowBanner: true,
      shouldShowList: true,
    }),
  });
  nativeModule = Notifications;
  return Notifications;
}
