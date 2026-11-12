import { Redirect, useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import {
  Platform,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { Image } from 'expo-image';
import { Eye, EyeOff } from 'lucide-react-native';

import {
  DriverCard,
  DriverHeader,
  DriverScreen,
  PrimaryButton,
  SecondaryButton,
} from '@/components/driver';
import { DriverTheme } from '@/components/driver/theme';
import { getAuthErrorMessage } from '@/lib/auth/authErrors';
import { useAuth } from '@/lib/auth/AuthContext';
import { AppConfig } from '@/lib/config';
import { ScreenLoading } from '@/lib/ux/ScreenState';

const uxTheme = {
  text: DriverTheme.colors.textPrimary,
  textSecondary: DriverTheme.colors.textSecondary,
  primary: DriverTheme.colors.primary,
};

/**
 * Dev-only prefill — never ships in production bundles.
 * Requires __DEV__, non-production env, and EXPO_PUBLIC_DRIVER_DEV_PREFILL=true.
 */
function initialDevCredentials(): { email: string; password: string } {
  const allow =
    !AppConfig.isProduction &&
    typeof __DEV__ !== 'undefined' &&
    __DEV__ &&
    process.env.EXPO_PUBLIC_DRIVER_DEV_PREFILL === 'true';
  if (!allow) {
    return { email: '', password: '' };
  }
  return {
    email: process.env.EXPO_PUBLIC_DRIVER_DEV_EMAIL || '',
    password: process.env.EXPO_PUBLIC_DRIVER_DEV_PASSWORD || '',
  };
}

export default function LoginScreen() {
  const {
    ready,
    isAuthenticated,
    login,
    bootError,
    authNotice,
    retrySessionRestore,
    dismissBootError,
    clearAuthNotice,
  } = useAuth();
  const router = useRouter();

  const initial = initialDevCredentials();
  const [email, setEmail] = useState(initial.email);
  const [password, setPassword] = useState(initial.password);
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [showPassword, setShowPassword] = useState(false);
  const [emailFocused, setEmailFocused] = useState(false);
  const [passwordFocused, setPasswordFocused] = useState(false);

  useEffect(() => {
    if (ready && isAuthenticated) {
      router.replace('/(tabs)');
    }
  }, [ready, isAuthenticated, router]);

  const header = <DriverHeader title="FleetX Driver" />;

  if (!ready) {
    return (
      <DriverScreen header={header} safeBottom>
        <ScreenLoading label="Loading…" theme={uxTheme} />
      </DriverScreen>
    );
  }

  if (isAuthenticated) {
    return <Redirect href="/(tabs)" />;
  }

  if (bootError) {
    return (
      <DriverScreen header={header} scroll padded keyboardAvoiding safeBottom>
        <View style={styles.centerCol}>
          <Text style={styles.kicker}>Driver workspace</Text>
          <Text style={styles.heading}>Could not restore session</Text>
          <DriverCard style={styles.errorCard}>
            <Text testID="login-boot-error" style={styles.errorBody}>
              {bootError}
            </Text>
          </DriverCard>
          <PrimaryButton
            testID="login-boot-retry"
            label="Retry"
            onPress={() => void retrySessionRestore()}
            accessibilityLabel="Retry session restore"
          />
          <SecondaryButton
            testID="login-boot-dismiss"
            label="Sign in again"
            onPress={() => void dismissBootError()}
            accessibilityLabel="Sign in again"
          />
        </View>
      </DriverScreen>
    );
  }

  const onSubmit = async () => {
    setFormError(null);
    clearAuthNotice();
    setSubmitting(true);
    try {
      await login(email.trim(), password);
      router.replace('/(tabs)');
    } catch (e) {
      setFormError(getAuthErrorMessage(e, 'Login failed'));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <DriverScreen header={header} scroll padded keyboardAvoiding safeBottom>
      <View style={styles.centerCol}>
        <View style={styles.headerContainer}>
          <Image
            source={require('../../assets/images/logos/fleetlogo.png')}
            style={styles.logoImage}
            contentFit="contain"
            accessibilityLabel="FleetX"
          />
          <Text style={styles.kicker}>Driver Workspace</Text>
          <Text style={styles.heading}>Sign in to continue</Text>
          <Text style={styles.sub}>Sign in with your FleetX driver account</Text>
        </View>

        {authNotice ? (
          <View style={styles.noticeCard}>
            <Text testID="login-notice" style={styles.noticeText}>
              {authNotice}
            </Text>
          </View>
        ) : null}

        <DriverCard>
          <Text style={styles.label}>Email</Text>
          <TextInput
            testID="login-email"
            accessibilityLabel="Email"
            autoCapitalize="none"
            autoCorrect={false}
            autoComplete="email"
            textContentType="emailAddress"
            keyboardType="email-address"
            placeholder="Email"
            placeholderTextColor={DriverTheme.colors.textMuted}
            style={[styles.input, emailFocused && styles.inputFocused]}
            value={email}
            onChangeText={setEmail}
            editable={!submitting}
            onFocus={() => setEmailFocused(true)}
            onBlur={() => setEmailFocused(false)}
            returnKeyType="next"
          />

          <Text style={styles.label}>Password</Text>
          <View style={[styles.passwordRow, passwordFocused && styles.inputFocused]}>
            <TextInput
              testID="login-password"
              accessibilityLabel="Password"
              secureTextEntry={!showPassword}
              autoComplete="password"
              textContentType="password"
              placeholder="Password"
              placeholderTextColor={DriverTheme.colors.textMuted}
              style={styles.passwordInput}
              value={password}
              onChangeText={setPassword}
              editable={!submitting}
              onFocus={() => setPasswordFocused(true)}
              onBlur={() => setPasswordFocused(false)}
              returnKeyType="go"
              onSubmitEditing={() => {
                if (!submitting) void onSubmit();
              }}
            />
            <Pressable
              onPress={() => setShowPassword((v) => !v)}
              accessibilityRole="button"
              accessibilityLabel={showPassword ? 'Hide password' : 'Show password'}
              hitSlop={8}
              style={styles.eye}
              disabled={submitting}>
              {showPassword ? (
                <EyeOff size={20} color={DriverTheme.colors.textSecondary} />
              ) : (
                <Eye size={20} color={DriverTheme.colors.textSecondary} />
              )}
            </Pressable>
          </View>

          {formError ? (
            <View style={styles.errorCard}>
              <Text style={styles.errorTitle}>Unable to sign in</Text>
              <Text testID="login-error" style={styles.errorBody}>
                {formError}
              </Text>
            </View>
          ) : null}

          <PrimaryButton
            testID="login-submit"
            label="Sign in"
            onPress={() => void onSubmit()}
            disabled={submitting}
            loading={submitting}
            accessibilityLabel="Sign in"
          />
        </DriverCard>
      </View>
    </DriverScreen>
  );
}

const styles = StyleSheet.create({
  centerCol: {
    flexGrow: 1,
    justifyContent: 'center',
    paddingVertical: DriverTheme.spacing.xl,
    gap: DriverTheme.spacing.md,
  },
  headerContainer: {
    alignItems: 'center',
    marginBottom: 8,
    gap: 4,
  },
  logoImage: {
    width: 220,
    height: 80,
    marginBottom: 12,
  },
  kicker: {
    ...DriverTheme.typography.sectionTitle,
    color: DriverTheme.colors.primary,
    textTransform: 'uppercase',
  },
  heading: {
    ...DriverTheme.typography.screenTitle,
    color: DriverTheme.colors.textPrimary,
  },
  sub: {
    ...DriverTheme.typography.body,
    color: DriverTheme.colors.textSecondary,
    marginBottom: DriverTheme.spacing.sm,
  },
  label: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.textSecondary,
    textTransform: 'uppercase',
    letterSpacing: 0.4,
    marginBottom: DriverTheme.spacing.xs,
  },
  input: {
    borderWidth: 1,
    borderColor: DriverTheme.colors.border,
    borderRadius: DriverTheme.radius.medium,
    paddingHorizontal: DriverTheme.spacing.md,
    paddingVertical: Platform.OS === 'ios' ? DriverTheme.spacing.md : DriverTheme.spacing.sm,
    fontSize: 16,
    lineHeight: 22,
    minHeight: 48,
    color: DriverTheme.colors.textPrimary,
    backgroundColor: DriverTheme.colors.background,
    marginBottom: DriverTheme.spacing.md,
  },
  passwordRow: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: DriverTheme.colors.border,
    borderRadius: DriverTheme.radius.medium,
    backgroundColor: DriverTheme.colors.background,
    minHeight: 48,
    marginBottom: DriverTheme.spacing.md,
  },
  passwordInput: {
    flex: 1,
    minHeight: 48,
    paddingHorizontal: DriverTheme.spacing.md,
    paddingVertical: Platform.OS === 'ios' ? DriverTheme.spacing.md : DriverTheme.spacing.sm,
    fontSize: 16,
    lineHeight: 22,
    color: DriverTheme.colors.textPrimary,
  },
  inputFocused: {
    borderColor: DriverTheme.colors.primary,
  },
  eye: {
    width: DriverTheme.control.iconButton,
    height: DriverTheme.control.iconButton,
    alignItems: 'center',
    justifyContent: 'center',
  },
  noticeCard: {
    backgroundColor: DriverTheme.colors.infoSoft,
    borderRadius: DriverTheme.radius.medium,
    padding: DriverTheme.spacing.md,
  },
  noticeText: {
    ...DriverTheme.typography.body,
    color: DriverTheme.colors.primaryDark,
  },
  errorCard: {
    backgroundColor: DriverTheme.colors.dangerSoft,
    borderRadius: DriverTheme.radius.medium,
    padding: DriverTheme.spacing.md,
    gap: DriverTheme.spacing.xs,
    marginBottom: DriverTheme.spacing.sm,
  },
  errorTitle: {
    ...DriverTheme.typography.bodyMedium,
    color: DriverTheme.colors.danger,
  },
  errorBody: {
    ...DriverTheme.typography.body,
    color: DriverTheme.colors.danger,
  },
});
