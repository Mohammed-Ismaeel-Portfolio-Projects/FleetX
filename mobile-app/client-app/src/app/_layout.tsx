import { Stack } from 'expo-router';

import { AnimatedSplashOverlay } from '@/components/animated-icon';
import { AuthProvider } from '@/lib/auth/AuthContext';

export default function RootLayout() {
  return (
    <AuthProvider>
      <AnimatedSplashOverlay />
      <Stack>
        <Stack.Screen name="index" options={{ headerShown: false }} />
        <Stack.Screen name="login" options={{ headerShown: false }} />
        <Stack.Screen name="register" options={{ headerShown: false }} />
        <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
        <Stack.Screen
          name="conversation/[id]"
          options={{ headerShown: true, title: 'Conversation', presentation: 'card' }}
        />
        <Stack.Screen
          name="shipment/[id]"
          options={{ headerShown: true, title: 'Communication', presentation: 'card' }}
        />
        <Stack.Screen
          name="shipment-details/[id]"
          options={{ headerShown: false, presentation: 'card' }}
        />
        <Stack.Screen
          name="shipment-tracking/[id]"
          options={{ headerShown: false, presentation: 'card' }}
        />
        <Stack.Screen
          name="notifications"
          options={{ headerShown: false, presentation: 'card' }}
        />
        <Stack.Screen
          name="request-details/[id]"
          options={{ headerShown: false, presentation: 'card' }}
        />
        <Stack.Screen
          name="request-form"
          options={{ headerShown: false, presentation: 'card' }}
        />
      </Stack>
    </AuthProvider>
  );
}
