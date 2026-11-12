import { useMemo, useRef, useState } from 'react';
import { PanResponder, Pressable, StyleSheet, View, type GestureResponderHandlers } from 'react-native';

import { ThemedText } from '@/components/themed-text';

export type SignatureStroke = { x: number; y: number }[];

type Props = {
  onChange: (strokes: SignatureStroke[]) => void;
};

type SignatureGesture = {
  panHandlers: GestureResponderHandlers;
  clearDraft: () => void;
};

function createSignatureGesture(
  onStrokeComplete: (draft: SignatureStroke) => void
): SignatureGesture {
  let draft: SignatureStroke = [];

  const pan = PanResponder.create({
    onStartShouldSetPanResponder: () => true,
    onMoveShouldSetPanResponder: () => true,
    onPanResponderGrant: (evt) => {
      draft = [{ x: evt.nativeEvent.locationX, y: evt.nativeEvent.locationY }];
    },
    onPanResponderMove: (evt) => {
      draft = [
        ...draft,
        { x: evt.nativeEvent.locationX, y: evt.nativeEvent.locationY },
      ];
    },
    onPanResponderRelease: () => {
      if (draft.length > 1) {
        onStrokeComplete(draft);
      }
      draft = [];
    },
  });

  return {
    panHandlers: pan.panHandlers,
    clearDraft: () => {
      draft = [];
    },
  };
}

/**
 * Lightweight signature capture (D7) — stroke JSON for multipart upload.
 */
export function SignaturePad({ onChange }: Props) {
  const [strokes, setStrokes] = useState<SignatureStroke[]>([]);
  const strokesRef = useRef<SignatureStroke[]>([]);
  strokesRef.current = strokes;

  const gesture = useMemo(
    () =>
      createSignatureGesture((draft) => {
        const next = [...strokesRef.current, draft];
        setStrokes(next);
        onChange(next);
      }),
    [onChange]
  );

  return (
    <View style={styles.wrap} testID="signature-pad">
      <View style={styles.pad} {...gesture.panHandlers} testID="signature-pad-surface">
        <ThemedText style={styles.hint}>
          {strokes.length > 0 ? 'Signature captured' : 'Sign here with your finger'}
        </ThemedText>
        <ThemedText style={styles.meta} testID="signature-pad-strokes">
          Strokes: {strokes.length}
        </ThemedText>
      </View>
      <Pressable
        testID="signature-pad-clear"
        onPress={() => {
          gesture.clearDraft();
          strokesRef.current = [];
          setStrokes([]);
          onChange([]);
        }}
        style={styles.clear}>
        <ThemedText>Clear signature</ThemedText>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { gap: 8 },
  pad: {
    minHeight: 160,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: 'rgba(127,127,127,0.35)',
    backgroundColor: 'rgba(255,255,255,0.06)',
    padding: 12,
    justifyContent: 'center',
  },
  hint: { opacity: 0.7 },
  meta: { marginTop: 8, opacity: 0.5, fontSize: 12 },
  clear: { minHeight: 44, justifyContent: 'center' },
});
