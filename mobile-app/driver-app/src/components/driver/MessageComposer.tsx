import { ActivityIndicator, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';

import { DriverTheme } from './theme';

type MessageComposerProps = {
  value: string;
  onChangeText: (text: string) => void;
  onSend: () => void;
  sending: boolean;
  closed: boolean;
  sendError: string | null;
  bottomInset: number;
};

export function MessageComposer({
  value,
  onChangeText,
  onSend,
  sending,
  closed,
  sendError,
  bottomInset,
}: MessageComposerProps) {
  const canSend = !sending && value.trim().length > 0;

  return (
    <View style={[styles.wrap, { paddingBottom: Math.max(bottomInset, DriverTheme.spacing.md) }]}>
      {sendError ? (
        <Text testID="send-error" style={styles.sendError}>
          {sendError}
        </Text>
      ) : null}
      {closed ? (
        <Text style={styles.closed}>Conversation closed — composer disabled.</Text>
      ) : (
        <View testID="composer" style={styles.composer}>
          <TextInput
            testID="composer-input"
            accessibilityLabel="Message"
            value={value}
            onChangeText={onChangeText}
            placeholder="Message"
            placeholderTextColor={DriverTheme.colors.textMuted}
            editable={!sending}
            multiline
            style={styles.input}
          />
          <Pressable
            testID="composer-send"
            accessibilityLabel="Send"
            accessibilityRole="button"
            accessibilityState={{ disabled: !canSend, busy: sending }}
            onPress={onSend}
            disabled={!canSend}
            style={({ pressed }) => [
              styles.sendBtn,
              !canSend && styles.sendDisabled,
              pressed && canSend && styles.sendPressed,
            ]}>
            {sending ? (
              <ActivityIndicator color={DriverTheme.colors.textOnPrimary} />
            ) : (
              <Text style={styles.sendText}>Send</Text>
            )}
          </Pressable>
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    backgroundColor: DriverTheme.colors.surface,
    borderTopWidth: 1,
    borderTopColor: DriverTheme.colors.border,
    paddingHorizontal: DriverTheme.spacing.lg,
    paddingTop: DriverTheme.spacing.md,
    gap: DriverTheme.spacing.sm,
  },
  composer: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    gap: DriverTheme.spacing.sm,
  },
  input: {
    flex: 1,
    minHeight: 44,
    maxHeight: 120,
    borderWidth: 1,
    borderColor: DriverTheme.colors.border,
    borderRadius: DriverTheme.radius.medium,
    paddingHorizontal: DriverTheme.spacing.md,
    paddingVertical: DriverTheme.spacing.sm,
    backgroundColor: DriverTheme.colors.background,
    color: DriverTheme.colors.textPrimary,
    ...DriverTheme.typography.body,
  },
  sendBtn: {
    backgroundColor: DriverTheme.colors.primary,
    borderRadius: DriverTheme.radius.medium,
    paddingHorizontal: DriverTheme.spacing.lg,
    minWidth: 72,
    minHeight: 44,
    alignItems: 'center',
    justifyContent: 'center',
  },
  sendDisabled: {
    opacity: 0.5,
  },
  sendPressed: {
    opacity: 0.88,
  },
  sendText: {
    ...DriverTheme.typography.button,
    color: DriverTheme.colors.textOnPrimary,
    fontSize: 15,
  },
  sendError: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.danger,
  },
  closed: {
    ...DriverTheme.typography.body,
    color: DriverTheme.colors.textSecondary,
    paddingVertical: DriverTheme.spacing.sm,
  },
});
