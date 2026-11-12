import { StyleSheet, Text, View } from 'react-native';

export default function MapAndroidDiagnosticScreen() {
  return (
    <View style={styles.root} testID="map-android-diagnostic">
      <Text style={styles.text}>
        Android Map Diagnostic is an Android-only native test screen.
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    backgroundColor: '#111',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
  },
  text: {
    color: '#94A3B8',
    fontSize: 14,
    textAlign: 'center',
  },
});
