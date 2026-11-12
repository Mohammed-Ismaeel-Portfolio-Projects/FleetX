import { StyleSheet, Text, View } from 'react-native';

import { DriverTheme } from './theme';

type MessageBubbleProps = {
  body: string;
  senderLabel: string;
  timeLabel: string;
  mine: boolean;
};

export function MessageBubble({ body, senderLabel, timeLabel, mine }: MessageBubbleProps) {
  return (
    <View
      style={[styles.wrap, mine ? styles.mineAlign : styles.theirsAlign]}
      accessibilityRole="text"
      accessibilityLabel={`${senderLabel}: ${body}`}>
      <View style={[styles.bubble, mine ? styles.mine : styles.theirs]}>
        <Text style={[styles.body, mine ? styles.mineText : styles.theirsText]}>{body}</Text>
        <Text style={[styles.time, mine ? styles.mineTime : styles.theirsTime]} numberOfLines={2}>
          {senderLabel} · {timeLabel}
        </Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    maxWidth: '82%',
    marginBottom: DriverTheme.spacing.md,
  },
  mineAlign: {
    alignSelf: 'flex-end',
    alignItems: 'flex-end',
  },
  theirsAlign: {
    alignSelf: 'flex-start',
    alignItems: 'flex-start',
  },
  bubble: {
    paddingHorizontal: DriverTheme.spacing.lg,
    paddingVertical: DriverTheme.spacing.md,
    borderRadius: DriverTheme.radius.large,
  },
  mine: {
    backgroundColor: DriverTheme.colors.primary,
    borderBottomRightRadius: DriverTheme.radius.small,
  },
  theirs: {
    backgroundColor: DriverTheme.colors.surface,
    borderWidth: 1,
    borderColor: DriverTheme.colors.border,
    borderBottomLeftRadius: DriverTheme.radius.small,
  },
  body: {
    ...DriverTheme.typography.body,
  },
  mineText: {
    color: DriverTheme.colors.textOnPrimary,
  },
  theirsText: {
    color: DriverTheme.colors.textPrimary,
  },
  time: {
    fontSize: 11,
    lineHeight: 16,
    marginTop: DriverTheme.spacing.xs,
  },
  mineTime: {
    color: 'rgba(255,255,255,0.78)',
  },
  theirsTime: {
    color: DriverTheme.colors.textMuted,
  },
});
