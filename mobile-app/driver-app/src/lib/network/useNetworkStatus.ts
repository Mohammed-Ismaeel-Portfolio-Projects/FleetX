import { useEffect, useState } from 'react';
import NetInfo from '@react-native-community/netinfo';

/**
 * Best-effort online indicator (D9). Not an offline sync engine.
 */
export function useNetworkStatus(): { online: boolean } {
  const [online, setOnline] = useState(true);

  useEffect(() => {
    const sub = NetInfo.addEventListener((state) => {
      const reachable = state.isInternetReachable;
      setOnline(state.isConnected !== false && reachable !== false);
    });
    return () => sub();
  }, []);

  return { online };
}
