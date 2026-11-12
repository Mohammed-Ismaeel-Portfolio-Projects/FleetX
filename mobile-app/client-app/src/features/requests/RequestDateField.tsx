// @ts-nocheck — lucide icon props vs RN ColorValue
import { useMemo, useState } from 'react';
import { Modal, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { Calendar, ChevronLeft, ChevronRight } from 'lucide-react-native';

import {
  daysInMonth,
  formatRequestDateLabel,
  formatRequestDateYmd,
  monthYearLabel,
  parseRequestDate,
  shiftMonth,
  todayParts,
  weekdayIndex,
} from './requestDates';

type ThemeColors = {
  text: string;
  textSecondary: string;
  primary: string;
  border: string;
  backgroundElement: string;
  inputBg: string;
  background: string;
};

const WEEKDAYS = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

export function RequestDateField({
  label,
  value,
  onChange,
  error,
  testID,
  openTestID,
  theme,
  helper,
}: {
  label: string;
  value: string;
  onChange: (next: string) => void;
  error?: string;
  testID: string;
  openTestID: string;
  theme: ThemeColors;
  helper?: string;
}) {
  const parsed = parseRequestDate(value);
  const pretty = formatRequestDateLabel(value);
  const seed = parsed ?? todayParts();
  const [open, setOpen] = useState(false);
  const [cursor, setCursor] = useState({ year: seed.year, month: seed.month });

  const openCalendar = () => {
    const next = parseRequestDate(value) ?? todayParts();
    setCursor({ year: next.year, month: next.month });
    setOpen(true);
  };

  const cells = useMemo(() => {
    const leading = weekdayIndex(cursor.year, cursor.month, 1);
    const count = daysInMonth(cursor.year, cursor.month);
    const total = Math.ceil((leading + count) / 7) * 7;
    return Array.from({ length: total }, (_, index) => {
      const day = index - leading + 1;
      if (day < 1 || day > count) return null;
      return day;
    });
  }, [cursor.year, cursor.month]);

  return (
    <View style={styles.wrap}>
      <Text style={[styles.label, { color: theme.textSecondary }]}>{label}</Text>
      <Pressable
        testID={openTestID}
        onPress={openCalendar}
        accessibilityRole="button"
        accessibilityLabel={`${label}. ${pretty ?? 'No date selected'}. Opens calendar`}
        style={[
          styles.display,
          {
            backgroundColor: theme.inputBg,
            borderColor: error ? '#EF4444' : theme.border,
          },
        ]}>
        <Calendar size={18} color={theme.primary} />
        <Text style={[styles.displayText, { color: pretty ? theme.text : theme.textSecondary }]}>
          {pretty ?? 'Select date'}
        </Text>
      </Pressable>
      <TextInput
        testID={testID}
        value={value}
        onChangeText={onChange}
        placeholder="YYYY-MM-DD"
        placeholderTextColor={theme.textSecondary}
        autoCapitalize="none"
        autoCorrect={false}
        keyboardType="numbers-and-punctuation"
        accessibilityLabel={label}
        style={[
          styles.ymd,
          {
            color: theme.text,
            borderColor: error ? '#EF4444' : theme.border,
            backgroundColor: theme.inputBg,
          },
        ]}
      />
      {helper ? (
        <Text style={[styles.helper, { color: theme.textSecondary }]}>{helper}</Text>
      ) : null}
      {error ? <Text style={styles.error}>{error}</Text> : null}

      <Modal visible={open} transparent animationType="fade" onRequestClose={() => setOpen(false)}>
        <Pressable style={styles.backdrop} onPress={() => setOpen(false)} accessibilityLabel="Close calendar">
          <Pressable
            style={[styles.sheet, { backgroundColor: theme.background, borderColor: theme.border }]}
            onPress={(e) => e.stopPropagation()}>
            <View style={styles.monthBar}>
              <Pressable
                onPress={() => setCursor((c) => shiftMonth(c.year, c.month, -1))}
                accessibilityRole="button"
                accessibilityLabel="Previous month"
                style={[styles.monthBtn, { borderColor: theme.border }]}>
                <ChevronLeft size={18} color={theme.text} />
              </Pressable>
              <Text style={[styles.monthLabel, { color: theme.text }]}>
                {monthYearLabel(cursor.year, cursor.month)}
              </Text>
              <Pressable
                onPress={() => setCursor((c) => shiftMonth(c.year, c.month, 1))}
                accessibilityRole="button"
                accessibilityLabel="Next month"
                style={[styles.monthBtn, { borderColor: theme.border }]}>
                <ChevronRight size={18} color={theme.text} />
              </Pressable>
            </View>
            <View style={styles.weekRow}>
              {WEEKDAYS.map((day) => (
                <Text key={day} style={[styles.weekday, { color: theme.textSecondary }]}>
                  {day}
                </Text>
              ))}
            </View>
            <View style={styles.grid}>
              {cells.map((day, index) => {
                if (day == null) {
                  return <View key={`e-${index}`} style={styles.cell} />;
                }
                const ymd = formatRequestDateYmd(cursor.year, cursor.month, day);
                const selected = value.trim() === ymd;
                return (
                  <Pressable
                    key={ymd}
                    testID={`request-date-day-${ymd}`}
                    onPress={() => {
                      onChange(ymd);
                      setOpen(false);
                    }}
                    accessibilityRole="button"
                    accessibilityLabel={ymd}
                    accessibilityState={{ selected }}
                    style={[
                      styles.cellBtn,
                      selected && { backgroundColor: theme.primary },
                    ]}>
                    <Text style={[styles.cellText, { color: selected ? '#FFFFFF' : theme.text }]}>{day}</Text>
                  </Pressable>
                );
              })}
            </View>
            <View style={styles.sheetActions}>
              <Pressable
                onPress={() => {
                  onChange('');
                  setOpen(false);
                }}
                accessibilityRole="button"
                accessibilityLabel="Clear date">
                <Text style={[styles.clearText, { color: theme.textSecondary }]}>Clear</Text>
              </Pressable>
              <Pressable
                onPress={() => setOpen(false)}
                accessibilityRole="button"
                accessibilityLabel="Cancel"
                style={[styles.cancelBtn, { borderColor: theme.border }]}>
                <Text style={[styles.cancelText, { color: theme.text }]}>Close</Text>
              </Pressable>
            </View>
          </Pressable>
        </Pressable>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { marginBottom: 16, width: '100%' },
  label: { fontSize: 14, fontWeight: '600', marginBottom: 6, paddingLeft: 4 },
  display: {
    minHeight: 52,
    borderWidth: 1.5,
    borderRadius: 12,
    paddingHorizontal: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  displayText: { fontSize: 16, fontWeight: '600' },
  ymd: {
    marginTop: 8,
    height: 44,
    borderWidth: 1.5,
    borderRadius: 12,
    paddingHorizontal: 12,
    fontSize: 14,
    fontWeight: '500',
  },
  helper: { fontSize: 12, marginTop: 4, paddingLeft: 4 },
  error: { color: '#EF4444', fontSize: 12, marginTop: 4, fontWeight: '500', paddingLeft: 4 },
  backdrop: {
    flex: 1,
    backgroundColor: 'rgba(8, 11, 22, 0.72)',
    justifyContent: 'center',
    padding: 20,
  },
  sheet: {
    borderRadius: 20,
    borderWidth: 1.5,
    padding: 16,
  },
  monthBar: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 12,
  },
  monthBtn: {
    width: 40,
    height: 40,
    borderRadius: 12,
    borderWidth: 1.5,
    alignItems: 'center',
    justifyContent: 'center',
  },
  monthLabel: { fontSize: 16, fontWeight: '700' },
  weekRow: { flexDirection: 'row', marginBottom: 6 },
  weekday: { flex: 1, textAlign: 'center', fontSize: 11, fontWeight: '700' },
  grid: { flexDirection: 'row', flexWrap: 'wrap' },
  cell: { width: '14.285%', height: 40 },
  cellBtn: {
    width: '14.285%',
    height: 40,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 10,
  },
  cellText: { fontSize: 14, fontWeight: '600' },
  sheetActions: {
    marginTop: 12,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  clearText: { fontSize: 14, fontWeight: '600' },
  cancelBtn: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 10,
    borderWidth: 1.5,
  },
  cancelText: { fontSize: 14, fontWeight: '700' },
});
