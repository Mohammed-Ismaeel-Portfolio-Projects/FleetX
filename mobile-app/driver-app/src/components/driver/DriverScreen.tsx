import type { ReactNode } from 'react';
import {
  KeyboardAvoidingView,
  Platform,
  RefreshControl,
  ScrollView,
  StyleSheet,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { StatusBar } from 'expo-status-bar';

import { DriverTheme } from './theme';
import { OfflineSyncBanner } from './OfflineSyncBanner';

type DriverScreenProps = {
  children: ReactNode;
  header?: ReactNode;
  footer?: ReactNode;
  testID?: string;
  scroll?: boolean;
  padded?: boolean;
  refreshing?: boolean;
  onRefresh?: () => void;
  keyboardAvoiding?: boolean;
  /** Bottom inset for stack screens. Leave false on tab screens (tab bar owns it). */
  safeBottom?: boolean;
  scrollTestID?: string;
};

/**
 * Light operational canvas with optional navy header band (safe-area aware).
 * Tab screens: do not set safeBottom (the custom tab bar owns the inset).
 * Stack screens: set safeBottom, or let a footer (BottomActionBar) own the inset.
 */
export function DriverScreen({
  children,
  header,
  footer,
  testID,
  scroll = false,
  padded = false,
  refreshing = false,
  onRefresh,
  keyboardAvoiding = false,
  safeBottom = false,
  scrollTestID,
}: DriverScreenProps) {
  const body = (
    <View style={[styles.body, padded && !scroll ? styles.padded : null]}>
      {scroll ? (
        <ScrollView
          testID={scrollTestID}
          keyboardShouldPersistTaps="handled"
          contentContainerStyle={[styles.scrollContent, padded && styles.padded]}
          refreshControl={
            onRefresh ? (
              <RefreshControl
                refreshing={refreshing}
                onRefresh={onRefresh}
                tintColor={DriverTheme.colors.primary}
                colors={[DriverTheme.colors.primary]}
              />
            ) : undefined
          }>
          {children}
        </ScrollView>
      ) : (
        children
      )}
    </View>
  );

  return (
    <View style={styles.root} testID={testID}>
      <StatusBar style="light" />

      {/* Atmospheric Ambient Glow Blobs matching Client App GradientBackground */}
      <View style={[styles.glowBlob, styles.glowBlob1]} pointerEvents="none" />
      <View style={[styles.glowBlob, styles.glowBlob2]} pointerEvents="none" />
      <View style={styles.outerFrameBorder} pointerEvents="none" />

      {header ? (
        <SafeAreaView edges={['top']} style={styles.headerBand}>
          {header}
        </SafeAreaView>
      ) : (
        <SafeAreaView edges={['top']} style={styles.topInset} />
      )}
      <OfflineSyncBanner />
      {keyboardAvoiding ? (
        <KeyboardAvoidingView
          style={styles.body}
          behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
          {body}
        </KeyboardAvoidingView>
      ) : (
        body
      )}
      {footer}
      {safeBottom && !footer ? (
        <SafeAreaView edges={['bottom']} style={styles.bottomInset} />
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    backgroundColor: '#080B16',
    overflow: 'hidden',
  },
  headerBand: {
    backgroundColor: '#080B16',
    zIndex: 10,
  },
  topInset: {
    backgroundColor: '#080B16',
    zIndex: 10,
  },
  body: {
    flex: 1,
    backgroundColor: 'transparent',
    zIndex: 10,
  },
  padded: {
    paddingHorizontal: 16,
  },
  scrollContent: {
    flexGrow: 1,
    paddingTop: 16,
    paddingBottom: 36,
    gap: 16,
  },
  bottomInset: {
    backgroundColor: '#080B16',
    zIndex: 10,
  },
  glowBlob: {
    position: 'absolute',
    width: 260,
    height: 260,
    borderRadius: 130,
    opacity: 0.08,
  },
  glowBlob1: {
    top: -50,
    left: -50,
    backgroundColor: '#1E75FF',
  },
  glowBlob2: {
    bottom: -50,
    right: -50,
    backgroundColor: '#5C5CFF',
  },
  outerFrameBorder: {
    position: 'absolute',
    top: 4,
    left: 4,
    right: 4,
    bottom: 4,
    borderWidth: 1.5,
    borderRadius: 24,
    borderColor: '#5C5CFF',
    opacity: 0.12,
  },
});
