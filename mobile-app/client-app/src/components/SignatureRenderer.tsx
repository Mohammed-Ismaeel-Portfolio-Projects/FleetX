import React from 'react';
import { View, StyleSheet, Text } from 'react-native';
import Svg, { Path } from 'react-native-svg';

export interface SignatureStroke {
  points: Array<{ x: number; y: number }>;
}

export interface SignatureRendererProps {
  strokes?: SignatureStroke[] | null;
  width?: number;
  height?: number;
}

export function SignatureRenderer({ strokes, width = 280, height = 120 }: SignatureRendererProps) {
  if (!strokes || strokes.length === 0) {
    return (
      <View style={styles.empty}>
        <Text style={styles.emptyText}>No digital signature points</Text>
      </View>
    );
  }

  const paths = strokes.map((stroke, index) => {
    if (!stroke.points || stroke.points.length === 0) return null;
    const d = stroke.points.reduce((acc, point, idx) => {
      return idx === 0 ? `M ${point.x} ${point.y}` : `${acc} L ${point.x} ${point.y}`;
    }, '');
    return (
      <Path
        key={index}
        d={d}
        stroke="#3B82F6"
        strokeWidth="2.5"
        strokeLinecap="round"
        strokeLinejoin="round"
        fill="none"
      />
    );
  });

  return (
    <View style={styles.container}>
      <Svg viewBox={`0 0 ${width} ${height}`} width="100%" height={height}>
        {paths}
      </Svg>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    backgroundColor: '#060911',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: 'rgba(255, 255, 255, 0.1)',
    padding: 6,
  },
  empty: {
    height: 80,
    backgroundColor: 'rgba(15, 23, 42, 0.5)',
    borderRadius: 8,
    borderWidth: 1,
    borderStyle: 'dashed',
    borderColor: 'rgba(255, 255, 255, 0.1)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  emptyText: {
    fontSize: 12,
    color: '#64748B',
  },
});
