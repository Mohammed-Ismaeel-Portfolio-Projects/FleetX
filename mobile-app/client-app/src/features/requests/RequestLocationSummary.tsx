// @ts-nocheck — lucide icon props vs RN ColorValue
import { StyleSheet, Text, View } from 'react-native';
import { MapPin } from 'lucide-react-native';

import { contactLine, locationAddressLine, locationHeadline } from './requestWizard';
import { selectedLocationSummary } from './coordinates';

type ThemeColors = {
  text: string;
  textSecondary: string;
  primary: string;
  border: string;
  backgroundElement: string;
};

export function RequestLocationSummary({
  title,
  company,
  address,
  city,
  country,
  contact,
  phone,
  mapLabel,
  hasCoordinates,
  dateLabel,
  theme,
  testID,
}: {
  title: string;
  company: string;
  address: string;
  city: string;
  country: string;
  contact: string;
  phone: string;
  mapLabel?: string | null;
  hasCoordinates?: boolean;
  dateLabel?: string | null;
  theme: ThemeColors;
  testID: string;
}) {
  const headline = locationHeadline(company, city, country);
  const street = locationAddressLine(address);
  const contactText = contactLine(contact, phone);
  const selected = selectedLocationSummary(Boolean(hasCoordinates), mapLabel ?? '', city, country);
  const missingRequired = !city.trim() || !country.trim();

  return (
    <View
      testID={testID}
      style={[styles.card, { borderColor: theme.border, backgroundColor: theme.backgroundElement }]}>
      <View style={styles.row}>
        <View style={[styles.iconWrap, { backgroundColor: `${theme.primary}22` }]}>
          <MapPin size={16} color={theme.primary} />
        </View>
        <View style={styles.body}>
          <Text style={[styles.kicker, { color: theme.textSecondary }]}>{title}</Text>
          <Text style={[styles.headline, { color: theme.text }]}>
            {headline ?? 'Location not entered yet'}
          </Text>
          {street ? <Text style={[styles.meta, { color: theme.textSecondary }]}>{street}</Text> : null}
          {selected ? (
            <Text style={[styles.selected, { color: theme.primary }]}>📍 {selected}</Text>
          ) : (
            <Text style={[styles.meta, { color: theme.textSecondary }]}>Map point not selected yet</Text>
          )}
          {contactText ? <Text style={[styles.meta, { color: theme.textSecondary }]}>{contactText}</Text> : null}
          {dateLabel ? <Text style={[styles.date, { color: theme.text }]}>{dateLabel}</Text> : null}
          {missingRequired ? (
            <Text style={[styles.meta, { color: theme.textSecondary }]}>
              City and country are required before review.
            </Text>
          ) : null}
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    borderWidth: 1.5,
    borderRadius: 16,
    padding: 14,
  },
  row: {
    flexDirection: 'row',
    gap: 12,
    alignItems: 'flex-start',
  },
  iconWrap: {
    width: 32,
    height: 32,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  body: { flex: 1, minWidth: 0, gap: 2 },
  kicker: {
    fontSize: 11,
    fontWeight: '700',
    letterSpacing: 0.6,
    textTransform: 'uppercase',
  },
  headline: { fontSize: 15, fontWeight: '700', lineHeight: 20 },
  meta: { fontSize: 13, lineHeight: 18 },
  selected: { fontSize: 13, lineHeight: 18, fontWeight: '700' },
  date: { fontSize: 13, fontWeight: '600', marginTop: 4 },
});
