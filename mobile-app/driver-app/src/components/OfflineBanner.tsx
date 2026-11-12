import { StyleSheet, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { Spacing } from '@/constants/theme';

type Props = {
  testID?: string;
};

/** Passive offline indicator — no blocking modal while driving. */
export function OfflineBanner({ testID = 'offline-banner' }: Props) {
  return (
    <View style={styles.banner} testID={testID}>
      <ThemedText type="smallBold">Connection lost</ThemedText>
      <ThemedText style={styles.body}>
        Connection lost. We will retry when the network returns. Trip actions may fail until then.
      </ThemedText>
    </View>
  );
}

const styles = StyleSheet.create({
  banner: {
    gap: 4,
    padding: Spacing.three,
    borderRadius: 12,
    backgroundColor: 'rgba(180,35,24,0.12)',
    marginBottom: Spacing.two,
  },
  body: { opacity: 0.85, fontSize: 13 },
});
