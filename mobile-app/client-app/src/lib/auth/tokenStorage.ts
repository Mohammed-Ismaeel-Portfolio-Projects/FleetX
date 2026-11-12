import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

const TOKEN_KEY = 'fleetx_access_token';

async function memoryFallback(): Promise<{
  get: () => string | null;
  set: (v: string) => void;
  clear: () => void;
}> {
  let value: string | null = null;
  return {
    get: () => value,
    set: (v) => {
      value = v;
    },
    clear: () => {
      value = null;
    },
  };
}

let webStore: Awaited<ReturnType<typeof memoryFallback>> | null = null;

export async function getAccessToken(): Promise<string | null> {
  if (Platform.OS === 'web') {
    webStore ??= await memoryFallback();
    return webStore.get() ?? (typeof localStorage !== 'undefined' ? localStorage.getItem(TOKEN_KEY) : null);
  }
  return SecureStore.getItemAsync(TOKEN_KEY);
}

export async function setAccessToken(token: string): Promise<void> {
  if (Platform.OS === 'web') {
    webStore ??= await memoryFallback();
    webStore.set(token);
    if (typeof localStorage !== 'undefined') localStorage.setItem(TOKEN_KEY, token);
    return;
  }
  await SecureStore.setItemAsync(TOKEN_KEY, token);
}

export async function clearAccessToken(): Promise<void> {
  if (Platform.OS === 'web') {
    webStore ??= await memoryFallback();
    webStore.clear();
    if (typeof localStorage !== 'undefined') localStorage.removeItem(TOKEN_KEY);
    return;
  }
  await SecureStore.deleteItemAsync(TOKEN_KEY);
}
