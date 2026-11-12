// @ts-nocheck — lucide icon props vs RN ColorValue
import React from 'react';
import { StyleSheet, Text, View, TouchableOpacity } from 'react-native';
import { useRouter } from 'expo-router';
import type { LucideIcon } from 'lucide-react-native';
import { MessageSquare, ChevronLeft } from 'lucide-react-native';
import { useTheme } from '@/hooks/use-theme';
import { GradientBackground } from '@/components/product';
import { useAuth } from '@/lib/auth/AuthContext';

type UnavailableDomainProps = {
  title: string;
  testID: string;
  icon: LucideIcon;
  /** Why this domain is UNAVAILABLE (not EMPTY). */
  explanation: string;
  /** Optional back for stack screens (notifications, shipment-details). */
  showBack?: boolean;
};

/**
 * Production-safe UNAVAILABLE shell for domains without client-safe APIs.
 * Distinguishes UNAVAILABLE from EMPTY — never fabricates zero-record lists.
 */
export function UnavailableDomainScreen({
  title,
  testID,
  icon: Icon,
  explanation,
  showBack = false,
}: UnavailableDomainProps) {
  const theme = useTheme();
  const router = useRouter();
  const { identityLinked, roleOk, identity } = useAuth();
  const canMessage = roleOk && identityLinked;

  return (
    <GradientBackground>
      {showBack ? (
        <View style={styles.backRow}>
          <TouchableOpacity
            testID={`${testID}-back`}
            style={[styles.backBtn, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}
            onPress={() => router.back()}
            activeOpacity={0.7}
            accessibilityLabel="Go back">
            <ChevronLeft size={20} color={theme.text} />
          </TouchableOpacity>
        </View>
      ) : null}

      <View style={styles.headerBlock} testID={testID}>
        <View style={[styles.iconWrap, { backgroundColor: theme.backgroundSelected, borderColor: theme.border }]}>
          <Icon size={22} color={theme.textSecondary} />
        </View>
        <Text style={[styles.title, { color: theme.text }]} testID={`${testID}-title`}>
          {title}
        </Text>
        <View style={styles.unavailablePill} testID={`${testID}-badge`}>
          <Text style={styles.unavailablePillText}>Unavailable</Text>
        </View>
      </View>

      <View
        style={[styles.card, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}
        testID={`${testID}-card`}>
        <Text style={[styles.cardBody, { color: theme.textSecondary }]} testID={`${testID}-body`}>
          {explanation}
        </Text>
        <Text style={[styles.semantics, { color: theme.textSecondary }]}>
          This is not an empty list. Client-safe data for this feature is not available in the app
          yet.
        </Text>
      </View>

      {!identityLinked ? (
        <View
          testID={`${testID}-unlinked`}
          style={[
            styles.warnBanner,
            {
              backgroundColor: 'rgba(251, 191, 36, 0.12)',
              borderColor: 'rgba(251, 191, 36, 0.45)',
            },
          ]}>
          <Text style={styles.warnTitle}>Company not linked</Text>
          <Text style={[styles.warnBody, { color: theme.textSecondary }]}>
            Company-linked features require a linked company account. Self-service company linking
            is not available in this app.
            {identity?.unlink_reason ? ` (${identity.unlink_reason})` : ''}
          </Text>
        </View>
      ) : null}

      {canMessage ? (
        <TouchableOpacity
          testID={`${testID}-message-cta`}
          style={[styles.primaryBtn, { backgroundColor: theme.primary }]}
          activeOpacity={0.8}
          onPress={() => router.push('/messages')}
          accessibilityRole="button"
          accessibilityLabel="Open FleetX Support messages"
          accessibilityHint="Opens your FleetX Support conversations">
          <MessageSquare size={16} color="#FFFFFF" />
          <Text style={styles.primaryBtnText}>FleetX Support</Text>
        </TouchableOpacity>
      ) : (
        <View
          testID={`${testID}-message-disabled`}
          style={[styles.disabledNote, { borderColor: theme.border }]}>
          <Text style={[styles.disabledNoteText, { color: theme.textSecondary }]}>
            {!roleOk
              ? 'Messaging is unavailable for this account role.'
              : 'Messaging is unavailable until your account is linked to a company.'}
          </Text>
        </View>
      )}
    </GradientBackground>
  );
}

const styles = StyleSheet.create({
  backRow: {
    marginBottom: 8,
  },
  backBtn: {
    width: 44,
    height: 44,
    borderRadius: 12,
    borderWidth: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  headerBlock: {
    alignItems: 'flex-start',
    marginBottom: 16,
    marginTop: 8,
  },
  iconWrap: {
    width: 48,
    height: 48,
    borderRadius: 14,
    borderWidth: 1,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 12,
  },
  title: {
    fontSize: 22,
    fontWeight: '700',
    marginBottom: 10,
  },
  unavailablePill: {
    backgroundColor: 'rgba(148, 163, 184, 0.18)',
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 999,
  },
  unavailablePillText: {
    color: '#94A3B8',
    fontSize: 11,
    fontWeight: '700',
    letterSpacing: 0.3,
  },
  card: {
    borderWidth: 1.5,
    borderRadius: 16,
    padding: 16,
    marginBottom: 14,
  },
  cardBody: {
    fontSize: 14,
    lineHeight: 20,
    marginBottom: 10,
  },
  semantics: {
    fontSize: 12,
    lineHeight: 17,
    fontWeight: '600',
  },
  warnBanner: {
    borderWidth: 1,
    borderRadius: 14,
    padding: 14,
    marginBottom: 14,
  },
  warnTitle: {
    color: '#FBBF24',
    fontSize: 14,
    fontWeight: '700',
    marginBottom: 4,
  },
  warnBody: {
    fontSize: 12,
    lineHeight: 17,
  },
  primaryBtn: {
    minHeight: 48,
    borderRadius: 12,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingHorizontal: 16,
  },
  primaryBtnText: {
    color: '#FFFFFF',
    fontSize: 14,
    fontWeight: '700',
  },
  disabledNote: {
    borderWidth: 1,
    borderRadius: 12,
    padding: 14,
  },
  disabledNoteText: {
    fontSize: 13,
    lineHeight: 18,
    textAlign: 'center',
  },
});
