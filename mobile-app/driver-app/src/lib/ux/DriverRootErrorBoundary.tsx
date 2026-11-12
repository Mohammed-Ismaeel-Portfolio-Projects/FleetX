/**
 * DR-F02 — Root crash recovery shell for uncaught React render/lifecycle errors.
 * Presentation only; does not alter auth contracts, telemetry, or backend behavior.
 */

import React, { Component, type ErrorInfo, type ReactNode } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';
import { useRouter, type Href } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Colors, Spacing } from '@/constants/theme';
import { useAuth } from '@/lib/auth/AuthContext';
import { useColorScheme } from '@/hooks/use-color-scheme';

type BoundaryState = {
  hasError: boolean;
  treeKey: number;
};

type DriverCrashRecoveryProps = {
  onRetry: () => void;
  onRecover: () => void;
};

function DriverCrashRecovery({ onRetry, onRecover }: DriverCrashRecoveryProps) {
  const { logout } = useAuth();
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const colors = Colors[scheme === 'unspecified' ? 'light' : scheme];
  const theme = {
    text: colors.text,
    textSecondary: colors.textSecondary,
    primary: '#1B4F72',
  };
  const [retrying, setRetrying] = React.useState(false);
  const [signingOut, setSigningOut] = React.useState(false);

  const handleRetry = () => {
    if (retrying || signingOut) return;
    setRetrying(true);
    onRetry();
    setRetrying(false);
  };

  const handleSignOut = async () => {
    if (retrying || signingOut) return;
    setSigningOut(true);
    try {
      await logout();
    } catch {
      /* logout clears local session best-effort */
    } finally {
      onRecover();
      router.replace('/login' as Href);
      setSigningOut(false);
    }
  };

  return (
    <ThemedView style={styles.container} testID="driver-crash-recovery">
      <SafeAreaView style={styles.safe}>
        <View style={styles.content} accessibilityRole="summary">
          <ThemedText type="subtitle" style={styles.title}>
            Something went wrong
          </ThemedText>
          <ThemedText style={[styles.body, { color: theme.textSecondary }]}>
            The app encountered an unexpected problem. You can try again or sign out and return to
            the sign-in screen.
          </ThemedText>

          <Pressable
            testID="driver-crash-retry"
            onPress={handleRetry}
            disabled={retrying || signingOut}
            style={[styles.primaryBtn, { backgroundColor: theme.primary }]}
            accessibilityRole="button"
            accessibilityLabel="Try again"
            accessibilityHint="Resets the app view and attempts to continue">
            {retrying ? (
              <ActivityIndicator color="#FFFFFF" />
            ) : (
              <ThemedText style={styles.primaryText}>Try again</ThemedText>
            )}
          </Pressable>

          <Pressable
            testID="driver-crash-sign-out"
            onPress={() => void handleSignOut()}
            disabled={retrying || signingOut}
            style={styles.secondaryBtn}
            accessibilityRole="button"
            accessibilityLabel="Sign out"
            accessibilityHint="Ends your session and returns to sign in">
            {signingOut ? (
              <ActivityIndicator color={theme.primary} />
            ) : (
              <ThemedText style={{ color: theme.primary, fontWeight: '600' }}>Sign out</ThemedText>
            )}
          </Pressable>
        </View>
      </SafeAreaView>
    </ThemedView>
  );
}

export class DriverRootErrorBoundary extends Component<{ children: ReactNode }, BoundaryState> {
  state: BoundaryState = {
    hasError: false,
    treeKey: 0,
  };

  static getDerivedStateFromError(): Partial<BoundaryState> {
    return { hasError: true };
  }

  componentDidCatch(error: Error, info: ErrorInfo): void {
    if (typeof __DEV__ !== 'undefined' && __DEV__) {
      console.error('[DriverRootErrorBoundary]', error, info.componentStack);
    }
  }

  private handleRetry = (): void => {
    this.setState((prev) => ({
      hasError: false,
      treeKey: prev.treeKey + 1,
    }));
  };

  private handleRecover = (): void => {
    this.setState((prev) => ({
      hasError: false,
      treeKey: prev.treeKey + 1,
    }));
  };

  render(): ReactNode {
    if (this.state.hasError) {
      return (
        <DriverCrashRecovery onRetry={this.handleRetry} onRecover={this.handleRecover} />
      );
    }

    return <React.Fragment key={this.state.treeKey}>{this.props.children}</React.Fragment>;
  }
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  safe: { flex: 1 },
  content: {
    flex: 1,
    justifyContent: 'center',
    paddingHorizontal: Spacing.four,
    gap: Spacing.three,
  },
  title: { textAlign: 'center' },
  body: { textAlign: 'center', lineHeight: 20, fontSize: 14 },
  primaryBtn: {
    minHeight: 48,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: Spacing.two,
  },
  primaryText: { color: '#FFFFFF', fontWeight: '700', fontSize: 15 },
  secondaryBtn: {
    minHeight: 48,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: 'rgba(127,127,127,0.35)',
  },
});
