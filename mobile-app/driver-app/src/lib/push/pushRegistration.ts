/**
 * Driver push registration (D12). Token only after canOperate. Never Client users.
 *
 * expo-notifications is loaded only in development/production native builds.
 * Expo Go does not import it (SDK 53+ Android would crash on module init).
 */

import * as Device from 'expo-device';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

import { registerDriverDevice, unregisterDriverDevice } from '@/features/driverApi/devices';
import { AppConfig } from '@/lib/config';
import {
  EXPO_GO_PUSH_UNAVAILABLE_MESSAGE,
  isExpoGoRuntime,
  loadNativeNotifications,
} from '@/lib/push/nativeNotifications';

const TOKEN_KEY = 'fleetx.driver.push_token';

export type DriverPushPermission = 'unknown' | 'granted' | 'denied' | 'unavailable';

export type DriverPushStatus = {
  permission: DriverPushPermission;
  registered: boolean;
  message: string;
};

function isNative(): boolean {
  return Platform.OS === 'ios' || Platform.OS === 'android';
}

function expoGoUnavailableStatus(): DriverPushStatus {
  return {
    permission: 'unavailable',
    registered: false,
    message: EXPO_GO_PUSH_UNAVAILABLE_MESSAGE,
  };
}

export async function getStoredPushToken(): Promise<string | null> {
  try {
    return await SecureStore.getItemAsync(TOKEN_KEY);
  } catch {
    return null;
  }
}

async function storePushToken(token: string | null): Promise<void> {
  try {
    if (token) await SecureStore.setItemAsync(TOKEN_KEY, token);
    else await SecureStore.deleteItemAsync(TOKEN_KEY);
  } catch {
    // Best-effort local cache only.
  }
}

export async function getDriverPushStatus(): Promise<DriverPushStatus> {
  if (isExpoGoRuntime()) {
    return expoGoUnavailableStatus();
  }
  if (!isNative() || !Device.isDevice) {
    return {
      permission: 'unavailable',
      registered: false,
      message: 'Device alerts need a physical Android or iOS device.',
    };
  }
  if (!AppConfig.easProjectId) {
    return {
      permission: 'unavailable',
      registered: false,
      message:
        'Device alerts are not configured for this build (missing EAS project ID). In-app Alerts still work.',
    };
  }
  try {
    const Notifications = await loadNativeNotifications();
    if (!Notifications) {
      return {
        permission: 'unavailable',
        registered: false,
        message: 'Device alerts are unavailable in this runtime. In-app Alerts still work.',
      };
    }
    const perms = await Notifications.getPermissionsAsync();
    const granted = perms.granted || perms.status === 'granted';
    if (granted) {
      const stored = await getStoredPushToken();
      return {
        permission: 'granted',
        registered: Boolean(stored),
        message: stored
          ? 'Trip alerts are enabled on this device.'
          : 'Permission granted. Registering this device…',
      };
    }
    if (perms.status === 'denied') {
      return {
        permission: 'denied',
        registered: false,
        message:
          'Notifications are off for this app. In-app Alerts still work. Enable notifications in system settings to get trip alerts.',
      };
    }
    return {
      permission: 'unknown',
      registered: false,
      message: 'Enable trip alerts to get notified when work is assigned or cancelled.',
    };
  } catch {
    return {
      permission: 'unavailable',
      registered: false,
      message: 'Device alerts are unavailable in this runtime. In-app Alerts still work.',
    };
  }
}

/** Silent sync when permission is already granted. Does not prompt. */
export async function syncDriverPushIfGranted(): Promise<void> {
  const status = await getDriverPushStatus();
  if (status.permission !== 'granted') return;
  await registerCurrentToken();
}

/** Explicit Driver permission CTA. */
export async function enableDriverPush(): Promise<DriverPushStatus> {
  if (isExpoGoRuntime()) {
    return expoGoUnavailableStatus();
  }
  if (!isNative() || !Device.isDevice) {
    return getDriverPushStatus();
  }
  if (!AppConfig.easProjectId) {
    return getDriverPushStatus();
  }
  try {
    const Notifications = await loadNativeNotifications();
    if (!Notifications) {
      return getDriverPushStatus();
    }
    if (Platform.OS === 'android') {
      await Notifications.setNotificationChannelAsync('driver-alerts', {
        name: 'Trip alerts',
        importance: Notifications.AndroidImportance.HIGH,
      });
    }
    const current = await Notifications.getPermissionsAsync();
    let granted = current.granted || current.status === 'granted';
    if (!granted) {
      const asked = await Notifications.requestPermissionsAsync();
      granted = asked.granted || asked.status === 'granted';
    }
    if (!granted) {
      return getDriverPushStatus();
    }
    await registerCurrentToken();
    return getDriverPushStatus();
  } catch {
    return getDriverPushStatus();
  }
}

async function registerCurrentToken(): Promise<void> {
  if (!AppConfig.easProjectId) return;
  const Notifications = await loadNativeNotifications();
  if (!Notifications) return;
  const native = Platform.OS === 'ios' ? 'ios' : Platform.OS === 'android' ? 'android' : null;
  if (!native) return;
  const tokenRes = await Notifications.getExpoPushTokenAsync({
    projectId: AppConfig.easProjectId,
  });
  const token = tokenRes.data;
  if (!token) return;
  await registerDriverDevice({
    pushToken: token,
    platform: native,
    deviceId: Device.modelName ?? Device.osInternalBuildId ?? null,
  });
  await storePushToken(token);
}

/** Best-effort; never blocks logout. */
export async function unregisterDriverPushBestEffort(): Promise<void> {
  const token = await getStoredPushToken();
  if (!token) return;
  try {
    await unregisterDriverDevice(token);
  } catch {
    // Inbox and session logout must proceed.
  } finally {
    await storePushToken(null);
  }
}
