import React from 'react';
import { StyleSheet, View, KeyboardAvoidingView, Platform, ScrollView } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTheme } from '@/hooks/use-theme';

interface GradientBackgroundProps {
  children: React.ReactNode;
  scrollable?: boolean;
}

export function GradientBackground({ children, scrollable = true }: GradientBackgroundProps) {
  const theme = useTheme();
  const insets = useSafeAreaInsets();

  // Modern safe area calculations following modern platforms spacing baselines
  const paddingTop = insets.top > 0 ? insets.top + 16 : 24;
  const paddingBottom = insets.bottom > 0 ? insets.bottom + 16 : 24;

  const content = (
    <KeyboardAvoidingView
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
      style={[
        styles.keyboardAvoid,
        {
          paddingTop,
          paddingBottom: scrollable ? 0 : paddingBottom,
        }
      ]}
    >
      {scrollable ? (
        <ScrollView
          contentContainerStyle={[styles.scrollContainer, { paddingBottom }]}
          showsVerticalScrollIndicator={false}
          keyboardShouldPersistTaps="handled"
        >
          {children}
        </ScrollView>
      ) : (
        <View style={styles.nonScrollContainer}>{children}</View>
      )}
    </KeyboardAvoidingView>
  );

  return (
    <View style={[styles.container, { backgroundColor: theme.background }]}>
      {/* Visual Identity Ambient Glow Blobs */}
      <View style={[styles.glowBlob, styles.glowBlob1, { backgroundColor: theme.primary }]} />
      <View style={[styles.glowBlob, styles.glowBlob2, { backgroundColor: theme.glow }]} />
      
      {/* Outer Glow border framing helper (recreating the border highlight from the photo) */}
      <View style={[styles.outerFrameBorder, { borderColor: theme.glow }]} />

      {content}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    overflow: 'hidden',
  },
  keyboardAvoid: {
    flex: 1,
    zIndex: 10,
  },
  scrollContainer: {
    flexGrow: 1,
    paddingHorizontal: 20,
    paddingBottom: 40,
    justifyContent: 'center',
  },
  nonScrollContainer: {
    flex: 1,
    paddingHorizontal: 20,
    justifyContent: 'center',
  },
  outerFrameBorder: {
    position: 'absolute',
    top: 4,
    left: 4,
    right: 4,
    bottom: 4,
    borderWidth: 1.5,
    borderRadius: 24,
    opacity: 0.15,
    pointerEvents: 'none',
    zIndex: 1,
  },
  glowBlob: {
    position: 'absolute',
    width: 250,
    height: 250,
    borderRadius: 125,
    opacity: 0.08,
    filter: Platform.OS === 'web' ? 'blur(80px)' : undefined, // Web blur support
    // For Native platforms, we simulate blurred blobs via layout overlays and low opacity.
  },
  glowBlob1: {
    top: -50,
    left: -50,
  },
  glowBlob2: {
    bottom: -50,
    right: -50,
  },
});
