/**
 * Active Trip operational awareness (D8).
 * Passive banners only; never opens modals while the vehicle is moving.
 */

import { useCallback, useEffect, useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import { useRouter, type Href } from 'expo-router';

import { ThemedText } from '@/components/themed-text';
import { Spacing } from '@/constants/theme';
import {
  listNotifications,
  markNotificationRead,
  notificationHref,
  type DriverNotificationItem,
} from '@/features/driverApi/notifications';
import { isVehicleMoving, MOTION_DEFER_HINT } from '@/lib/safety/drivingMotion';

const SPEED_ALERT_CODE = 'safety.speed_excess';
const HARSH_BRAKING_CODE = 'safety.harsh_braking';
const SUDDEN_STOP_CODE = 'safety.sudden_stop';

function isMotionAlertCode(code: string): boolean {
  return code === HARSH_BRAKING_CODE || code === SUDDEN_STOP_CODE;
}

function pickAwareness(
  items: DriverNotificationItem[],
  assignmentId: string
): {
  critical: DriverNotificationItem | null;
  message: DriverNotificationItem | null;
  info: DriverNotificationItem | null;
  speedAlert: DriverNotificationItem | null;
  harshBrakeAlert: DriverNotificationItem | null;
  suddenStopAlert: DriverNotificationItem | null;
} {
  const unread = items.filter((n) => !n.isRead);
  const forTrip = unread.filter(
    (n) =>
      n.assignmentId == null || String(n.assignmentId) === assignmentId
  );

  const critical =
    forTrip.find(
      (n) =>
        n.severity === 'critical' ||
        n.code === 'assignment.cancelled' ||
        n.code === 'assignment.reassigned'
    ) ?? null;

  const message =
    forTrip.find((n) => n.code === 'message.unread') ?? null;

  const speedAlert =
    forTrip.find((n) => n.code === SPEED_ALERT_CODE) ?? null;

  const harshBrakeAlert =
    forTrip.find((n) => n.code === HARSH_BRAKING_CODE) ?? null;

  const suddenStopAlert =
    forTrip.find((n) => n.code === SUDDEN_STOP_CODE) ?? null;

  const info =
    forTrip.find(
      (n) =>
        n.code !== SPEED_ALERT_CODE &&
        !isMotionAlertCode(n.code) &&
        (n.code === 'pickup.arrival' ||
          n.code === 'delivery.arrival' ||
          n.code === 'pickup.verification_success' ||
          n.code === 'delivery.verification_success' ||
          n.code === 'delivery.failed' ||
          n.severity === 'warning')
    ) ?? null;

  return {
    critical,
    message,
    info,
    speedAlert,
    harshBrakeAlert,
    suddenStopAlert,
  };
}

type Props = {
  assignmentId: string;
  speedKmh: number | null;
  theme: { primary: string };
  stale: boolean;
  onCriticalChange?: (critical: boolean) => void;
};

function MotionSafetyBanner({
  item,
  moving,
  theme,
  testID,
  openTestID,
  onOpen,
}: {
  item: DriverNotificationItem;
  moving: boolean;
  theme: { primary: string };
  testID: string;
  openTestID: string;
  onOpen: () => void;
}) {
  return (
    <View style={[styles.banner, styles.speed]} testID={testID}>
      <ThemedText type="smallBold">
        {moving ? 'MOTION SAFETY ALERT' : item.title}
      </ThemedText>
      <ThemedText style={styles.body}>{item.body}</ThemedText>
      {moving ? (
        <ThemedText style={styles.hint}>
          No action needed while driving. Open details when stopped.
        </ThemedText>
      ) : (
        <Pressable
          testID={openTestID}
          onPress={onOpen}
          style={styles.link}>
          <ThemedText style={{ color: theme.primary }}>Open alert</ThemedText>
        </Pressable>
      )}
    </View>
  );
}

export function TripAwarenessBanner({
  assignmentId,
  speedKmh,
  theme,
  stale,
  onCriticalChange,
}: Props) {
  const router = useRouter();
  const [critical, setCritical] = useState<DriverNotificationItem | null>(null);
  const [message, setMessage] = useState<DriverNotificationItem | null>(null);
  const [info, setInfo] = useState<DriverNotificationItem | null>(null);
  const [speedAlert, setSpeedAlert] = useState<DriverNotificationItem | null>(null);
  const [harshBrakeAlert, setHarshBrakeAlert] =
    useState<DriverNotificationItem | null>(null);
  const [suddenStopAlert, setSuddenStopAlert] =
    useState<DriverNotificationItem | null>(null);
  const moving = isVehicleMoving(speedKmh);

  const refresh = useCallback(async () => {
    try {
      const { items } = await listNotifications(1);
      const picked = pickAwareness(items, assignmentId);
      setCritical(picked.critical);
      setMessage(picked.message);
      setInfo(picked.info);
      setSpeedAlert(picked.speedAlert);
      setHarshBrakeAlert(picked.harshBrakeAlert);
      setSuddenStopAlert(picked.suddenStopAlert);
      onCriticalChange?.(picked.critical !== null);
    } catch {
      /* awareness is best-effort */
    }
  }, [assignmentId, onCriticalChange]);

  useEffect(() => {
    let cancelled = false;
    const tick = () => {
      if (!cancelled) void refresh();
    };
    const initial = setTimeout(tick, 0);
    const timer = setInterval(tick, 20000);
    return () => {
      cancelled = true;
      clearTimeout(initial);
      clearInterval(timer);
    };
  }, [refresh]);

  const openSafe = async (item: DriverNotificationItem, allowNavigate: boolean) => {
    try {
      if (!item.isRead) {
        await markNotificationRead(item.id);
      }
    } catch {
      /* continue */
    }
    if (!allowNavigate) return;
    router.push(notificationHref(item) as Href);
  };

  if (critical) {
    return (
      <View style={[styles.banner, styles.critical]} testID="trip-awareness-critical">
        <ThemedText type="smallBold">{critical.title}</ThemedText>
        <ThemedText style={styles.body}>{critical.body}</ThemedText>
        <ThemedText style={styles.hint}>
          Tracking and trip actions are blocked. Return to Work for the latest assignment.
        </ThemedText>
        <Pressable
          testID="trip-awareness-critical-work"
          onPress={() => {
            void openSafe(critical, true);
            router.replace('/(tabs)' as Href);
          }}
          style={styles.link}>
          <ThemedText style={{ color: theme.primary }}>Back to Work</ThemedText>
        </Pressable>
      </View>
    );
  }

  if (stale) {
    return null;
  }

  return (
    <View style={styles.stack} testID="trip-awareness">
      {speedAlert ? (
        <View style={[styles.banner, styles.speed]} testID="trip-awareness-speed">
          <ThemedText type="smallBold">SPEED SAFETY ALERT</ThemedText>
          <ThemedText style={styles.body}>{speedAlert.body}</ThemedText>
          {moving ? (
            <ThemedText style={styles.hint}>
              No action needed while driving. Open details when stopped.
            </ThemedText>
          ) : (
            <Pressable
              testID="trip-awareness-speed-open"
              onPress={() => void openSafe(speedAlert, true)}
              style={styles.link}>
              <ThemedText style={{ color: theme.primary }}>Open alert</ThemedText>
            </Pressable>
          )}
        </View>
      ) : null}

      {harshBrakeAlert || suddenStopAlert ? (
        <View style={styles.stack} testID="trip-awareness-motion">
          {harshBrakeAlert ? (
            <MotionSafetyBanner
              item={harshBrakeAlert}
              moving={moving}
              theme={theme}
              testID="trip-awareness-harsh"
              openTestID="trip-awareness-harsh-open"
              onOpen={() => void openSafe(harshBrakeAlert, true)}
            />
          ) : null}
          {suddenStopAlert ? (
            <MotionSafetyBanner
              item={suddenStopAlert}
              moving={moving}
              theme={theme}
              testID="trip-awareness-sudden"
              openTestID="trip-awareness-sudden-open"
              onOpen={() => void openSafe(suddenStopAlert, true)}
            />
          ) : null}
        </View>
      ) : null}

      {info ? (
        <View style={[styles.banner, styles.info]} testID="trip-awareness-info">
          <ThemedText type="smallBold">{info.title}</ThemedText>
          <ThemedText style={styles.body}>{info.body}</ThemedText>
          {moving ? (
            <ThemedText style={styles.hint}>{MOTION_DEFER_HINT}</ThemedText>
          ) : (
            <Pressable
              testID="trip-awareness-info-open"
              onPress={() => void openSafe(info, true)}
              style={styles.link}>
              <ThemedText style={{ color: theme.primary }}>Open</ThemedText>
            </Pressable>
          )}
        </View>
      ) : null}

      {message ? (
        <View style={[styles.banner, styles.message]} testID="trip-awareness-message">
          <ThemedText type="smallBold">{message.title}</ThemedText>
          <ThemedText style={styles.body}>{message.body}</ThemedText>
          {moving ? (
            <ThemedText style={styles.hint}>Unread dispatcher message — open when stopped.</ThemedText>
          ) : (
            <Pressable
              testID="trip-awareness-message-open"
              onPress={() => void openSafe(message, true)}
              style={styles.link}>
              <ThemedText style={{ color: theme.primary }}>Open conversation</ThemedText>
            </Pressable>
          )}
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  stack: { gap: Spacing.two },
  banner: {
    gap: 4,
    padding: Spacing.three,
    borderRadius: 12,
  },
  critical: { backgroundColor: 'rgba(180,35,24,0.18)' },
  speed: { backgroundColor: 'rgba(180,110,24,0.16)' },
  info: { backgroundColor: 'rgba(27,79,114,0.14)' },
  message: { backgroundColor: 'rgba(127,127,127,0.16)' },
  body: { opacity: 0.9 },
  hint: { opacity: 0.65, marginTop: 4 },
  link: { minHeight: 44, justifyContent: 'center', marginTop: 4 },
});
