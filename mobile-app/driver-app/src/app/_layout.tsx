import { Stack } from 'expo-router';

import { AnimatedSplashOverlay } from '@/components/animated-icon';
import { AuthProvider } from '@/lib/auth/AuthContext';
import { DriverPushBootstrap } from '@/lib/push/PushBootstrap';
import { DriverRootErrorBoundary } from '@/lib/ux/DriverRootErrorBoundary';

export default function RootLayout() {
  return (
    <AuthProvider>
      <DriverRootErrorBoundary>
        <DriverPushBootstrap />
        <AnimatedSplashOverlay />
        <Stack>
        <Stack.Screen name="index" options={{ headerShown: false }} />
        <Stack.Screen name="login" options={{ headerShown: false }} />
        <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
        <Stack.Screen
          name="assignment/[id]"
          options={{ headerShown: false, presentation: 'card' }}
        />
        <Stack.Screen
          name="active-trip/[id]"
          options={{ headerShown: false, presentation: 'card' }}
        />
        <Stack.Screen
          name="verify-trip/[id]"
          options={{ headerShown: false, presentation: 'card' }}
        />
        <Stack.Screen
          name="pod/[id]"
          options={{ headerShown: false, presentation: 'card' }}
        />
        <Stack.Screen
          name="delivery-complete/[id]"
          options={{ headerShown: false, presentation: 'card' }}
        />
        <Stack.Screen
          name="conversation/[id]"
          options={{ headerShown: false, presentation: 'card' }}
        />
        <Stack.Screen
          name="shipment/[id]"
          options={{ headerShown: true, title: 'Communication', presentation: 'card' }}
        />
        <Stack.Screen
          name="history/index"
          options={{ headerShown: false, presentation: 'card' }}
        />
        <Stack.Screen
          name="history/[id]"
          options={{ headerShown: false, presentation: 'card' }}
        />
        <Stack.Screen
          name="evaluation/[id]"
          options={{ headerShown: false, presentation: 'card' }}
        />
        <Stack.Screen
          name="offline-recovery"
          options={{ headerShown: false, presentation: 'card' }}
        />
      </Stack>
      </DriverRootErrorBoundary>
    </AuthProvider>
  );
}
