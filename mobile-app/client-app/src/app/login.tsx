import { Redirect, useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { Image } from 'expo-image';
import { Mail, Lock } from 'lucide-react-native';

import { GradientBackground, InputField, CustomButton } from '@/components/product';
import { ThemedView } from '@/components/themed-view';
import { useTheme } from '@/hooks/use-theme';
import { getAuthErrorMessage } from '@/lib/auth/authErrors';
import { useAuth } from '@/lib/auth/AuthContext';

export default function LoginScreen() {
  const theme = useTheme();
  const router = useRouter();
  const {
    ready,
    isAuthenticated,
    bootError,
    authNotice,
    authNoticeKind,
    login,
    retrySessionRestore,
    dismissBootError,
    clearAuthNotice,
  } = useAuth();

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [retrying, setRetrying] = useState(false);

  useEffect(() => {
    if (ready && isAuthenticated && !bootError) {
      router.replace('/(tabs)');
    }
  }, [ready, isAuthenticated, bootError, router]);

  if (!ready) {
    return (
      <ThemedView style={styles.center} testID="login-boot">
        <ActivityIndicator />
      </ThemedView>
    );
  }

  if (bootError) {
    return (
      <ThemedView style={styles.centerPad} testID="login-boot-error">
        <Text style={styles.bootTitle}>Session restore failed</Text>
        <Text style={styles.bootBody}>{bootError}</Text>
        <TouchableOpacity
          testID="login-boot-retry"
          style={[styles.retryBtn, { backgroundColor: theme.primary }]}
          disabled={retrying}
          accessibilityRole="button"
          accessibilityLabel="Retry session restore"
          accessibilityHint="Attempts to restore your signed-in session"
          accessibilityState={{ disabled: retrying, busy: retrying }}
          onPress={() => {
            if (retrying) return;
            setRetrying(true);
            void retrySessionRestore().finally(() => setRetrying(false));
          }}>
          <Text style={styles.retryText}>{retrying ? 'Retrying…' : 'Retry'}</Text>
        </TouchableOpacity>
        <TouchableOpacity
          testID="login-boot-dismiss"
          onPress={() => {
            void dismissBootError();
          }}
          style={styles.secondaryLink}
          accessibilityRole="button"
          accessibilityLabel="Continue to sign in">
          <Text style={{ color: theme.textSecondary }}>Continue to sign in</Text>
        </TouchableOpacity>
      </ThemedView>
    );
  }

  if (isAuthenticated) {
    return <Redirect href="/(tabs)" />;
  }

  const handleLogin = async () => {
    if (loading) return;
    setFormError(null);
    clearAuthNotice();

    const trimmedEmail = email.trim();
    if (!trimmedEmail || !password) {
      setFormError('Email and password are required.');
      return;
    }

    setLoading(true);
    try {
      await login(trimmedEmail, password);
      // Navigation happens via isAuthenticated → Redirect / useEffect after session validation.
    } catch (e) {
      setFormError(getAuthErrorMessage(e, 'Login failed. Please try again.'));
    } finally {
      setLoading(false);
    }
  };

  return (
    <GradientBackground>
      <View style={styles.headerContainer}>
        <Image
          source={require('../../assets/images/logos/fleetlogo.png')}
          style={styles.logoImage}
          contentFit="contain"
          accessibilityLabel="FleetX"
        />
        <Text style={[styles.subtitleText, { color: theme.textSecondary }]}>
          Welcome back! Sign in with your FleetX client account.
        </Text>
      </View>

      <View
        style={[
          styles.cardContainer,
          {
            backgroundColor: theme.backgroundElement,
            borderColor: theme.border,
          },
        ]}>
        {authNotice ? (
          <Text
            testID="login-auth-notice"
            style={[
              styles.notice,
              authNoticeKind === 'session_expired' ? styles.noticeWarn : styles.noticeInfo,
            ]}>
            {authNotice}
          </Text>
        ) : null}

        <InputField
          testID="login-email"
          label="Email Address"
          accessibilityLabel="Email address"
          placeholder="name@example.com"
          keyboardType="email-address"
          autoCapitalize="none"
          value={email}
          onChangeText={setEmail}
          icon={Mail}
        />

        <InputField
          testID="login-password"
          label="Password"
          accessibilityLabel="Password"
          placeholder="••••••••"
          secureTextEntry
          value={password}
          onChangeText={setPassword}
          icon={Lock}
        />

        {formError ? (
          <Text testID="login-error" style={styles.error}>
            {formError}
          </Text>
        ) : null}

        <View testID="login-submit">
          <CustomButton
            title="Login"
            onPress={() => void handleLogin()}
            loading={loading}
            disabled={loading}
            variant="primary"
            accessibilityLabel="Sign in to FleetX"
          />
        </View>
      </View>
    </GradientBackground>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  centerPad: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
  },
  bootTitle: {
    color: '#F8FAFC',
    fontSize: 18,
    fontWeight: '700',
    marginBottom: 8,
    textAlign: 'center',
  },
  bootBody: {
    color: '#94A3B8',
    fontSize: 14,
    textAlign: 'center',
    marginBottom: 16,
  },
  retryBtn: {
    paddingHorizontal: 20,
    paddingVertical: 12,
    borderRadius: 12,
    marginBottom: 12,
  },
  retryText: { color: '#FFFFFF', fontWeight: '700' },
  secondaryLink: { padding: 8 },
  headerContainer: {
    alignItems: 'center',
    marginBottom: 24,
    marginTop: 40,
  },
  logoImage: {
    width: 220,
    height: 80,
    marginBottom: 8,
  },
  subtitleText: {
    fontSize: 15,
    fontWeight: '500',
    marginTop: 8,
    textAlign: 'center',
  },
  cardContainer: {
    padding: 24,
    borderRadius: 20,
    borderWidth: 1.5,
    width: '100%',
  },
  notice: {
    fontSize: 13,
    fontWeight: '600',
    marginBottom: 12,
    lineHeight: 18,
  },
  noticeWarn: { color: '#FBBF24' },
  noticeInfo: { color: '#93C5FD' },
  error: {
    color: '#EF4444',
    marginBottom: 8,
    fontSize: 13,
  },
  footerContainer: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    marginTop: 24,
    marginBottom: 16,
  },
  footerText: {
    fontSize: 14,
    fontWeight: '500',
  },
  signUpLink: {
    fontSize: 14,
    fontWeight: '700',
  },
});
