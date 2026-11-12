// @ts-nocheck — lucide icon props vs RN ColorValue
/**
 * C2.9-C3.9 — Client Profile / Account.
 * Real AuthProvider identity only. No fake preferences, security, or AI controls.
 */
import React, { useState } from 'react';
import {
  ActivityIndicator,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useRouter } from 'expo-router';
import { Bell, Building2, ChevronRight, CreditCard, LogOut, Mail, User } from 'lucide-react-native';

import { GradientBackground } from '@/components/product';
import {
  deriveProfileInitials,
  displayProfileName,
  formatCustomerRoleLabel,
} from '@/features/profile/profileIdentity';
import { useTheme } from '@/hooks/use-theme';
import { useAuth } from '@/lib/auth/AuthContext';
import { AppConfig } from '@/lib/config';

export default function ProfileScreen() {
  const theme = useTheme();
  const router = useRouter();
  const { ready, user, identity, identityLinked, logout } = useAuth();
  const [loggingOut, setLoggingOut] = useState(false);

  const displayName = displayProfileName(user?.name);
  const initials = deriveProfileInitials(user?.name);
  const email = (user?.email && String(user.email).trim()) || null;
  const roleLabel = formatCustomerRoleLabel(user?.role?.name ?? identity?.role);
  const companyName =
    identityLinked && identity?.company?.company_name
      ? String(identity.company.company_name).trim()
      : null;
  const companyCode =
    identityLinked && identity?.company?.company_code
      ? String(identity.company.company_code).trim()
      : null;
  const unlinkReason = identity?.unlink_reason ? String(identity.unlink_reason).trim() : null;

  const handleLogout = async () => {
    if (loggingOut) return;
    setLoggingOut(true);
    try {
      await logout();
    } finally {
      router.replace('/login');
      setLoggingOut(false);
    }
  };

  if (!ready) {
    return (
      <GradientBackground>
        <View style={styles.loadingWrap} testID="profile-loading">
          <ActivityIndicator color={theme.primary} />
          <Text style={[styles.loadingText, { color: theme.textSecondary }]}>Loading account…</Text>
        </View>
      </GradientBackground>
    );
  }

  return (
    <GradientBackground>
      <View style={styles.screen} testID="profile-screen">
        <Text style={[styles.screenTitle, { color: theme.text }]} testID="profile-title">
          Account
        </Text>
        <Text style={[styles.screenSubtitle, { color: theme.textSecondary }]}>
          Your FleetX customer identity and company relationship
        </Text>

        {/* Account identity */}
        <View
          style={[styles.card, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}
          testID="profile-identity-card"
          accessibilityRole="summary"
          accessibilityLabel={`Account ${displayName}${email ? `, ${email}` : ''}, role ${roleLabel}`}>
          <View style={styles.identityRow}>
            <View
              style={[styles.avatar, { backgroundColor: theme.backgroundSelected, borderColor: theme.border }]}
              testID="profile-avatar"
              accessibilityElementsHidden
              importantForAccessibility="no">
              <Text style={[styles.avatarText, { color: theme.text }]}>{initials}</Text>
            </View>
            <View style={styles.identityMeta}>
              <Text style={[styles.name, { color: theme.text }]} testID="profile-display-name">
                {displayName}
              </Text>
              <View style={styles.roleRow}>
                <User size={14} color={theme.textSecondary} />
                <Text style={[styles.roleText, { color: theme.textSecondary }]} testID="profile-role">
                  {roleLabel}
                </Text>
              </View>
            </View>
          </View>

          {email ? (
            <View style={styles.detailRow} testID="profile-email-row">
              <Mail size={16} color={theme.textSecondary} />
              <Text style={[styles.detailText, { color: theme.text }]} numberOfLines={2}>
                {email}
              </Text>
            </View>
          ) : null}
        </View>

        {/* Company */}
        <Text style={[styles.sectionLabel, { color: theme.textSecondary }]}>Company</Text>
        <View
          style={[styles.card, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}
          testID="profile-company-card"
          accessibilityRole="summary"
          accessibilityLabel={
            identityLinked
              ? `Company linked${companyName ? `, ${companyName}` : ''}${
                  companyCode ? `, code ${companyCode}` : ''
                }`
              : 'Company not linked'
          }>
          {identityLinked ? (
            <>
              <View style={styles.companyHeader}>
                <View style={[styles.companyIcon, { backgroundColor: theme.backgroundSelected }]}>
                  <Building2 size={18} color={theme.primary} />
                </View>
                <View style={styles.companyMeta}>
                  <Text style={[styles.companyName, { color: theme.text }]} testID="profile-company-name">
                    {companyName || 'Linked company'}
                  </Text>
                  {companyCode ? (
                    <Text
                      style={[styles.companyCode, { color: theme.textSecondary }]}
                      testID="profile-company-code">
                      {companyCode}
                    </Text>
                  ) : null}
                </View>
                <View
                  style={[styles.statusPill, { backgroundColor: 'rgba(34, 197, 94, 0.15)' }]}
                  testID="profile-company-linked">
                  <Text style={styles.statusPillLinked}>Linked</Text>
                </View>
              </View>
            </>
          ) : (
            <View testID="profile-company-unlinked">
              <View style={styles.companyHeader}>
                <View style={[styles.companyIcon, { backgroundColor: theme.backgroundSelected }]}>
                  <Building2 size={18} color={theme.textSecondary} />
                </View>
                <View style={styles.companyMeta}>
                  <Text style={[styles.companyName, { color: theme.text }]}>Not linked</Text>
                  <Text style={[styles.unlinkedBody, { color: theme.textSecondary }]}>
                    Your account is not currently linked to a FleetX company.
                  </Text>
                </View>
                <View
                  style={[styles.statusPill, { backgroundColor: 'rgba(251, 191, 36, 0.15)' }]}
                  testID="profile-company-unlinked-badge">
                  <Text style={styles.statusPillUnlinked}>Unlinked</Text>
                </View>
              </View>
              {unlinkReason ? (
                <Text
                  style={[styles.unlinkReason, { color: theme.textSecondary }]}
                  testID="profile-unlink-reason">
                  {unlinkReason}
                </Text>
              ) : null}
            </View>
          )}
        </View>

        {/* Account actions — existing destinations only */}
        <Text style={[styles.sectionLabel, { color: theme.textSecondary }]}>Account</Text>
        <View
          style={[styles.card, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}
          testID="profile-actions-card">
          <Pressable
            testID="profile-open-invoices"
            onPress={() => router.push('/invoices')}
            accessibilityRole="button"
            accessibilityLabel="Billing & Invoices"
            accessibilityHint="Opens your commercial monthly billing and invoices"
            style={({ pressed }) => [styles.actionRow, { opacity: pressed ? 0.75 : 1, marginBottom: 12, paddingBottom: 12, borderBottomWidth: 1, borderBottomColor: theme.border }]}>
            <View style={styles.actionLeft}>
              <CreditCard size={18} color={theme.text} />
              <View style={styles.actionTextBlock}>
                <Text style={[styles.actionTitle, { color: theme.text }]}>Billing &amp; Invoices</Text>
                <Text style={[styles.actionSubtitle, { color: theme.textSecondary }]}>
                  View monthly commercial invoices &amp; pay via Stripe
                </Text>
              </View>
            </View>
            <ChevronRight size={18} color={theme.textSecondary} />
          </Pressable>
          <Pressable
            testID="profile-open-notifications"
            onPress={() => router.push('/notifications')}
            accessibilityRole="button"
            accessibilityLabel="Notifications"
            accessibilityHint="Opens your notification inbox"
            style={({ pressed }) => [styles.actionRow, { opacity: pressed ? 0.75 : 1 }]}>
            <View style={styles.actionLeft}>
              <Bell size={18} color={theme.text} />
              <View style={styles.actionTextBlock}>
                <Text style={[styles.actionTitle, { color: theme.text }]}>Notifications</Text>
                <Text style={[styles.actionSubtitle, { color: theme.textSecondary }]}>
                  View your notifications
                </Text>
              </View>
            </View>
            <ChevronRight size={18} color={theme.textSecondary} />
          </Pressable>
        </View>

        {/* About */}
        <Text style={[styles.sectionLabel, { color: theme.textSecondary }]}>About FleetX</Text>
        <View
          style={[styles.card, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}
          testID="profile-about-card"
          accessibilityRole="summary"
          accessibilityLabel={`FleetX Client version ${AppConfig.appVersion}`}>
          <Text style={[styles.aboutTitle, { color: theme.text }]}>FleetX Client</Text>
          <Text style={[styles.aboutVersion, { color: theme.textSecondary }]} testID="profile-app-version">
            Version {AppConfig.appVersion}
          </Text>
        </View>

        {/* Sign out */}
        <Pressable
          testID="profile-logout"
          onPress={() => void handleLogout()}
          disabled={loggingOut}
          accessibilityRole="button"
          accessibilityLabel={loggingOut ? 'Signing out' : 'Sign out'}
          accessibilityHint="Ends your session and returns to the login screen"
          accessibilityState={{ disabled: loggingOut, busy: loggingOut }}
          style={({ pressed }) => [
            styles.logoutBtn,
            {
              borderColor: 'rgba(239, 68, 68, 0.45)',
              opacity: loggingOut || pressed ? 0.65 : 1,
            },
          ]}>
          {loggingOut ? (
            <ActivityIndicator color="#EF4444" size="small" />
          ) : (
            <LogOut size={16} color="#EF4444" />
          )}
          <Text style={styles.logoutText}>{loggingOut ? 'Signing out…' : 'Sign out'}</Text>
        </Pressable>
      </View>
    </GradientBackground>
  );
}

const styles = StyleSheet.create({
  screen: {
    paddingBottom: Platform.OS === 'ios' ? 28 : 20,
  },
  loadingWrap: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 12,
    minHeight: 240,
  },
  loadingText: {
    fontSize: 14,
    fontWeight: '600',
  },
  screenTitle: {
    fontSize: 22,
    fontWeight: '800',
    marginTop: 8,
  },
  screenSubtitle: {
    fontSize: 13,
    lineHeight: 18,
    marginTop: 6,
    marginBottom: 20,
  },
  sectionLabel: {
    fontSize: 11,
    fontWeight: '700',
    letterSpacing: 0.6,
    textTransform: 'uppercase',
    marginBottom: 8,
    marginTop: 4,
  },
  card: {
    borderRadius: 16,
    borderWidth: 1.5,
    padding: 16,
    marginBottom: 16,
  },
  identityRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
    marginBottom: 14,
  },
  avatar: {
    width: 52,
    height: 52,
    borderRadius: 26,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: {
    fontSize: 16,
    fontWeight: '800',
  },
  identityMeta: {
    flex: 1,
    minWidth: 0,
  },
  name: {
    fontSize: 17,
    fontWeight: '800',
  },
  roleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginTop: 4,
  },
  roleText: {
    fontSize: 13,
    fontWeight: '600',
  },
  detailRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  detailText: {
    flex: 1,
    fontSize: 14,
    fontWeight: '600',
  },
  companyHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 12,
  },
  companyIcon: {
    width: 36,
    height: 36,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  companyMeta: {
    flex: 1,
    minWidth: 0,
  },
  companyName: {
    fontSize: 15,
    fontWeight: '700',
  },
  companyCode: {
    fontSize: 12,
    fontWeight: '600',
    marginTop: 4,
  },
  unlinkedBody: {
    fontSize: 12,
    lineHeight: 17,
    marginTop: 4,
  },
  unlinkReason: {
    fontSize: 12,
    lineHeight: 17,
    marginTop: 12,
  },
  statusPill: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  statusPillLinked: {
    color: '#22C55E',
    fontSize: 11,
    fontWeight: '800',
  },
  statusPillUnlinked: {
    color: '#FBBF24',
    fontSize: 11,
    fontWeight: '800',
  },
  actionRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    minHeight: 52,
  },
  actionLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    flex: 1,
    minWidth: 0,
  },
  actionTextBlock: {
    flex: 1,
    minWidth: 0,
  },
  actionTitle: {
    fontSize: 15,
    fontWeight: '700',
  },
  actionSubtitle: {
    fontSize: 12,
    marginTop: 2,
  },
  aboutTitle: {
    fontSize: 15,
    fontWeight: '700',
  },
  aboutVersion: {
    fontSize: 13,
    fontWeight: '600',
    marginTop: 4,
  },
  logoutBtn: {
    marginTop: 8,
    minHeight: 48,
    borderRadius: 14,
    borderWidth: 1.5,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  logoutText: {
    color: '#EF4444',
    fontSize: 15,
    fontWeight: '700',
  },
});
