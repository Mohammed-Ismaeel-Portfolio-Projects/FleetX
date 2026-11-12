// @ts-nocheck — lucide icon props vs RN ColorValue
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  useWindowDimensions,
  View,
  type LayoutChangeEvent,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import * as Location from 'expo-location';
import { ChevronLeft, LocateFixed, Search } from 'lucide-react-native';

import { useTheme } from '@/hooks/use-theme';
import { ClientMapCanvas } from './ClientMapCanvas';
import {
  reverseGeocodeLocation,
  searchGeocodeLocations,
  GeocodeSearchError,
  type GeocodeErrorCode,
  type GeocodeLocationResult,
} from './geocodeApi';
import {
  hasCoordinatePair,
  regionForCoordinate,
  UNSELECTED_MAP_REGION,
  type GeoCoordinate,
} from './coordinates';

export interface ClientLocationSelection {
  latitude: number;
  longitude: number;
  label: string;
  city: string | null;
  country: string | null;
}

export function ClientLocationPicker({
  stop,
  initialCoordinate,
  onConfirm,
  onCancel,
}: {
  stop: 'pickup' | 'delivery';
  initialCoordinate: GeoCoordinate | null;
  onConfirm: (selection: ClientLocationSelection) => void;
  onCancel: () => void;
}) {
  const theme = useTheme();
  const insets = useSafeAreaInsets();
  const { height: windowHeight } = useWindowDimensions();
  const mapHeight = useMemo(() => {
    const target = Math.round(windowHeight * 0.42);
    return Math.max(260, Math.min(target, 420));
  }, [windowHeight]);
  const prefix = stop === 'pickup' ? 'request-pickup' : 'request-delivery';
  const confirmTestID = stop === 'pickup' ? 'request-pickup-map-confirm' : 'request-delivery-map-confirm';
  const cancelTestID = stop === 'pickup' ? 'request-pickup-map-cancel' : 'request-delivery-map-cancel';
  const retryTestID = stop === 'pickup' ? 'request-pickup-map-retry' : 'request-delivery-map-retry';
  const title = stop === 'pickup' ? 'Select Pickup Location' : 'Select Delivery Location';

  const start = initialCoordinate
    ? regionForCoordinate(initialCoordinate)
    : UNSELECTED_MAP_REGION;

  const [mapEpoch, setMapEpoch] = useState(0);
  /** Defer MapView one frame after the wrap has layout (Android Modal + GL). */
  const [mapAttachAllowed, setMapAttachAllowed] = useState(false);
  const [mapBox, setMapBox] = useState<{ width: number; height: number } | null>(null);
  const [center, setCenter] = useState(start);
  const [selected, setSelected] = useState<GeoCoordinate | null>(initialCoordinate);
  const [mapReady, setMapReady] = useState(false);
  const [mapError, setMapError] = useState<string | null>(null);
  /** Soft banner only — must never cover MapView or block presses. */
  const [mapHint, setMapHint] = useState<string | null>(null);
  const [locating, setLocating] = useState(false);
  const [permissionHint, setPermissionHint] = useState<string | null>(null);
  const [geocoding, setGeocoding] = useState(false);
  const [detected, setDetected] = useState<GeocodeLocationResult | null>(
    initialCoordinate
      ? {
          latitude: initialCoordinate.latitude,
          longitude: initialCoordinate.longitude,
          label: 'Location selected',
          city: null,
          country: null,
        }
      : null
  );
  const [query, setQuery] = useState('');
  const [searching, setSearching] = useState(false);
  const [searchError, setSearchError] = useState<string | null>(null);
  const [searchErrorCode, setSearchErrorCode] = useState<GeocodeErrorCode | null>(null);
  const [hits, setHits] = useState<GeocodeLocationResult[]>([]);
  const geocodeSeq = useRef(0);
  const searchSeq = useRef(0);
  const debounceTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const loadTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    return () => {
      if (debounceTimer.current) clearTimeout(debounceTimer.current);
    };
  }, []);

  const applyCoordinate = useCallback(async (coordinate: GeoCoordinate) => {
    setSelected(coordinate);
    setCenter(regionForCoordinate(coordinate));
    setDetected({
      latitude: coordinate.latitude,
      longitude: coordinate.longitude,
      label: 'Location selected',
      city: null,
      country: null,
    });
    const seq = ++geocodeSeq.current;
    setGeocoding(true);
    try {
      const result = await reverseGeocodeLocation(coordinate.latitude, coordinate.longitude);
      if (seq !== geocodeSeq.current) return;
      setDetected({
        ...result,
        label: result.label || 'Location selected',
      });
    } catch {
      if (seq !== geocodeSeq.current) return;
      setDetected({
        latitude: coordinate.latitude,
        longitude: coordinate.longitude,
        label: 'Location selected',
        city: null,
        country: null,
      });
    } finally {
      if (seq === geocodeSeq.current) setGeocoding(false);
    }
  }, []);

  useEffect(() => {
    setMapReady(false);
    setMapError(null);
    setMapHint(null);
    setMapAttachAllowed(false);
    if (loadTimer.current) clearTimeout(loadTimer.current);
    // Only hard-fail if MapView never becomes ready (true init failure).
    loadTimer.current = setTimeout(() => {
      setMapReady((ready) => {
        if (!ready) {
          setMapError(
            Platform.OS === 'android'
              ? 'Map unavailable. Android Maps configuration is required. Search or Use my current location still works.'
              : 'Map unavailable. Please try again.'
          );
        }
        return ready;
      });
    }, 20000);
    return () => {
      if (loadTimer.current) clearTimeout(loadTimer.current);
    };
  }, [mapEpoch]);

  useEffect(() => {
    // Wait one tick so Modal animation / layout settles before attaching MapView.
    const handle = requestAnimationFrame(() => {
      setMapAttachAllowed(true);
    });
    return () => cancelAnimationFrame(handle);
  }, [mapEpoch]);

  const handleReady = () => {
    if (loadTimer.current) clearTimeout(loadTimer.current);
    setMapReady(true);
    setMapError(null);
  };

  const retryMap = () => {
    // Remount map only — preserve selected coordinates / form state.
    setMapError(null);
    setMapHint(null);
    setMapReady(false);
    setMapAttachAllowed(false);
    setMapBox(null);
    setMapEpoch((value) => value + 1);
  };

  const useCurrentLocation = async () => {
    setLocating(true);
    setPermissionHint(null);
    try {
      const current = await Location.getForegroundPermissionsAsync();
      let status = current.status;
      if (status !== 'granted') {
        const requested = await Location.requestForegroundPermissionsAsync();
        status = requested.status;
      }
      if (status !== 'granted') {
        setPermissionHint(
          'Current location is unavailable. You can still tap the map to place the pin.'
        );
        return;
      }
      const position = await Location.getCurrentPositionAsync({
        accuracy: Location.Accuracy.Balanced,
      });
      await applyCoordinate({
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
      });
    } catch {
      setPermissionHint(
        'Could not read your current location. Tap the map to choose a point instead.'
      );
    } finally {
      setLocating(false);
    }
  };

  const runSearch = async (targetQuery?: string) => {
    const trimmed = (targetQuery ?? query).trim();
    if (trimmed.length < 2) {
      setHits([]);
      setSearchError(null);
      setSearchErrorCode(null);
      return;
    }
    const seq = ++searchSeq.current;
    setSearching(true);
    setSearchError(null);
    setSearchErrorCode(null);
    try {
      const results = await searchGeocodeLocations(trimmed);
      if (seq !== searchSeq.current) return;
      setHits(results);
      if (results.length === 0) {
        setSearchError('No matching places found.');
        setSearchErrorCode(null);
      }
    } catch (error) {
      if (seq !== searchSeq.current) return;
      setHits([]);
      if (error instanceof GeocodeSearchError) {
        setSearchErrorCode(error.code);
        setSearchError(error.message);
      } else {
        setSearchErrorCode('UNKNOWN_ERROR');
        setSearchError('Could not search locations. You can place the pickup pin directly on the map.');
      }
    } finally {
      if (seq === searchSeq.current) setSearching(false);
    }
  };

  const handleQueryChange = (text: string) => {
    setQuery(text);
    if (debounceTimer.current) clearTimeout(debounceTimer.current);

    const trimmed = text.trim();
    if (trimmed.length < 2) {
      setHits([]);
      setSearchError(null);
      setSearchErrorCode(null);
      return;
    }

    debounceTimer.current = setTimeout(() => {
      void runSearch(text);
    }, 400);
  };

  const handleSearchSubmit = () => {
    if (debounceTimer.current) clearTimeout(debounceTimer.current);
    void runSearch();
  };

  const confirmEnabled = hasCoordinatePair(selected?.latitude, selected?.longitude);
  const summary = detected?.label || (confirmEnabled ? 'Location selected' : 'Tap the map to place the pin');
  const placeLine = [detected?.city, detected?.country].filter(Boolean).join(', ');

  const confirm = () => {
    if (!selected) return;
    onConfirm({
      latitude: selected.latitude,
      longitude: selected.longitude,
      label: detected?.label || 'Location selected',
      city: detected?.city ?? null,
      country: detected?.country ?? null,
    });
  };

  const mapRegion = selected ? regionForCoordinate(selected) : center;

  return (
    <View style={[styles.screen, { backgroundColor: theme.background, paddingTop: insets.top + 8 }]}>
      <View style={styles.header}>
        <Pressable
          testID={cancelTestID}
          onPress={onCancel}
          style={[styles.iconBtn, { borderColor: theme.border, backgroundColor: theme.backgroundElement }]}
          accessibilityRole="button"
          accessibilityLabel="Cancel location selection">
          <ChevronLeft size={20} color={theme.text} />
        </Pressable>
        <View style={styles.headerText}>
          <Text style={[styles.title, { color: theme.text }]}>{title}</Text>
          <Text style={[styles.subtitle, { color: theme.textSecondary }]}>
            Tap the map to place the pin. Address fields stay as you typed them.
          </Text>
        </View>
      </View>

      <View style={[styles.searchRow, { borderColor: theme.border, backgroundColor: theme.inputBg }]}>
        <Search size={16} color={theme.textSecondary} />
        <TextInput
          testID={`${prefix}-map-search`}
          style={[styles.searchInput, { color: theme.text }]}
          value={query}
          onChangeText={handleQueryChange}
          placeholder="Search a place"
          placeholderTextColor={theme.textSecondary}
          autoCapitalize="words"
          returnKeyType="search"
          onSubmitEditing={handleSearchSubmit}
        />
        <Pressable onPress={handleSearchSubmit} disabled={searching} accessibilityRole="button">
          <Text style={[styles.searchAction, { color: theme.primary }]}>
            {searching ? '…' : 'Search'}
          </Text>
        </Pressable>
      </View>
      {searchError ? (
        searchErrorCode ? (
          <View style={[styles.searchNotice, { borderColor: theme.border, backgroundColor: theme.backgroundElement }]}>
            <Text style={[styles.searchNoticeText, { color: theme.textSecondary }]}>
              {searchError}
            </Text>
          </View>
        ) : (
          <Text style={styles.searchError}>{searchError}</Text>
        )
      ) : null}
      {hits.length > 0 ? (
        <View style={[styles.hits, { borderColor: theme.border, backgroundColor: theme.backgroundElement }]}>
          {hits.slice(0, 4).map((hit, index) => (
            <Pressable
              key={`${hit.latitude}-${hit.longitude}-${index}`}
              onPress={() => {
                void applyCoordinate({ latitude: hit.latitude, longitude: hit.longitude });
                setHits([]);
                setQuery(hit.label || query);
              }}
              style={styles.hitRow}
              accessibilityRole="button">
              <Text style={[styles.hitTitle, { color: theme.text }]}>{hit.label || 'Place'}</Text>
              {hit.city || hit.country ? (
                <Text style={[styles.hitMeta, { color: theme.textSecondary }]}>
                  {[hit.city, hit.country].filter(Boolean).join(', ')}
                </Text>
              ) : null}
            </Pressable>
          ))}
        </View>
      ) : null}

      <View
        style={[styles.mapWrap, { height: mapHeight, borderColor: theme.border }]}
        accessibilityLabel={stop === 'pickup' ? 'Pickup map' : 'Delivery map'}
        collapsable={false}
        onLayout={(event: LayoutChangeEvent) => {
          const { width, height } = event.nativeEvent.layout;
          if (!(width > 1) || !(height > 1)) return;
          setMapBox((prev) => {
            if (
              prev &&
              Math.abs(prev.width - width) < 1 &&
              Math.abs(prev.height - height) < 1
            ) {
              return prev;
            }
            return { width, height };
          });
        }}>
        {mapAttachAllowed && mapBox ? (
          <ClientMapCanvas
            key={mapEpoch}
            testID={`${prefix}-map-view`}
            width={mapBox.width}
            height={mapBox.height}
            latitude={mapRegion.latitude}
            longitude={mapRegion.longitude}
            latitudeDelta={mapRegion.latitudeDelta}
            longitudeDelta={mapRegion.longitudeDelta}
            selected={selected}
            interactive
            onPress={(coordinate) => void applyCoordinate(coordinate)}
            onReady={handleReady}
            onError={(message) => {
              // Never cover an already-ready interactive MapView — that blocks taps.
              setMapReady((ready) => {
                if (ready) {
                  setMapHint(
                    message ||
                      'Map imagery may be incomplete. You can still tap to place the pin.'
                  );
                  return ready;
                }
                setMapError(
                  message ||
                    (Platform.OS === 'android'
                      ? 'Map unavailable. Android Maps configuration is required. Search or Use my current location still works.'
                      : 'Map unavailable. Please try again.')
                );
                return ready;
              });
            }}
          />
        ) : null}
        {!mapReady && !mapError ? (
          <View style={styles.mapOverlay} testID={`${prefix}-map-loading`} pointerEvents="none">
            <ActivityIndicator color={theme.primary} />
            <Text style={[styles.overlayText, { color: theme.text }]}>Loading map…</Text>
          </View>
        ) : null}
        {mapError && !mapReady ? (
          <View style={styles.mapOverlay} testID={`${prefix}-map-error`} pointerEvents="box-none">
            <Text style={[styles.overlayText, { color: theme.text }]}>{mapError}</Text>
            <Pressable
              testID={retryTestID}
              onPress={retryMap}
              style={[styles.retryBtn, { backgroundColor: theme.primary }]}
              accessibilityRole="button"
              accessibilityLabel="Retry loading the map">
              <Text style={styles.retryText}>Retry</Text>
            </Pressable>
          </View>
        ) : null}
      </View>
      {mapHint && mapReady ? (
        <View style={styles.hintBanner} testID={`${prefix}-map-hint`}>
          <Text style={[styles.hint, { color: theme.textSecondary, flex: 1 }]}>{mapHint}</Text>
          <Pressable onPress={retryMap} accessibilityRole="button" accessibilityLabel="Retry map">
            <Text style={[styles.searchAction, { color: theme.primary }]}>Retry</Text>
          </Pressable>
        </View>
      ) : null}

      <View style={styles.toolbar}>
        <Pressable
          testID={`${prefix}-map-locate`}
          onPress={() => void useCurrentLocation()}
          disabled={locating}
          style={[styles.outlineBtn, { borderColor: theme.border }]}
          accessibilityRole="button"
          accessibilityLabel="Use my current location">
          {locating ? (
            <ActivityIndicator color={theme.primary} />
          ) : (
            <>
              <LocateFixed size={16} color={theme.primary} />
              <Text style={[styles.outlineBtnText, { color: theme.text }]}>Use my current location</Text>
            </>
          )}
        </Pressable>
      </View>
      {permissionHint ? (
        <Text testID={`${prefix}-map-permission`} style={[styles.hint, { color: theme.textSecondary }]}>
          {permissionHint}
        </Text>
      ) : null}

      <View
        testID={`${prefix}-map-selected`}
        style={[styles.summary, { borderColor: theme.border, backgroundColor: theme.backgroundElement }]}
        accessibilityLabel={confirmEnabled ? 'Location selected' : 'No location selected yet'}>
        <Text style={[styles.summaryKicker, { color: theme.textSecondary }]}>Selected location</Text>
        <Text style={[styles.summaryTitle, { color: theme.text }]}>
          {geocoding ? 'Resolving location…' : summary}
        </Text>
        {placeLine ? (
          <Text style={[styles.summaryMeta, { color: theme.textSecondary }]}>{placeLine}</Text>
        ) : null}
        {confirmEnabled && selected ? (
          <Text style={[styles.coordHint, { color: theme.textSecondary }]}>
            {selected.latitude.toFixed(5)}, {selected.longitude.toFixed(5)}
          </Text>
        ) : null}
        <Text style={[styles.hint, { color: theme.textSecondary, marginTop: 8 }]}>
          Detected names are a hint only. They do not replace the address you entered on the form.
        </Text>
      </View>

      <View style={{ paddingBottom: Math.max(insets.bottom, 16) }}>
        <Pressable
          testID={confirmTestID}
          onPress={confirm}
          disabled={!confirmEnabled}
          style={[
            styles.primaryBtn,
            { backgroundColor: theme.primary, opacity: confirmEnabled ? 1 : 0.5 },
          ]}
          accessibilityRole="button"
          accessibilityState={{ disabled: !confirmEnabled }}
          accessibilityLabel={stop === 'pickup' ? 'Confirm pickup location' : 'Confirm delivery location'}>
          <Text style={styles.primaryBtnText}>
            {stop === 'pickup' ? 'Confirm Pickup Location' : 'Confirm Delivery Location'}
          </Text>
        </Pressable>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, paddingHorizontal: 16, gap: 10 },
  header: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  iconBtn: {
    width: 44,
    height: 44,
    borderRadius: 12,
    borderWidth: 1.5,
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerText: { flex: 1 },
  title: { fontSize: 18, fontWeight: '700' },
  subtitle: { fontSize: 12, marginTop: 2, lineHeight: 16 },
  searchRow: {
    minHeight: 48,
    borderWidth: 1.5,
    borderRadius: 12,
    paddingHorizontal: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  searchInput: { flex: 1, fontSize: 15, minHeight: 44 },
  searchAction: { fontWeight: '700', fontSize: 13 },
  searchError: { color: '#F87171', fontSize: 12 },
  searchNotice: {
    borderWidth: 1.5,
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  searchNoticeText: {
    fontSize: 12,
    lineHeight: 16,
  },
  hits: { borderWidth: 1.5, borderRadius: 12, overflow: 'hidden', maxHeight: 160 },
  hitRow: { paddingHorizontal: 12, paddingVertical: 10, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: 'rgba(148,163,184,0.35)' },
  hitTitle: { fontSize: 14, fontWeight: '600' },
  hitMeta: { fontSize: 12, marginTop: 2 },
  mapWrap: {
    width: '100%',
    borderWidth: 1.5,
    borderRadius: 16,
    overflow: 'hidden',
    position: 'relative',
    backgroundColor: '#E8EEF5',
  },
  mapOverlay: {
    ...StyleSheet.absoluteFillObject,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(8,11,22,0.55)',
    gap: 12,
    padding: 20,
    zIndex: 2,
  },
  hintBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  overlayText: { fontSize: 14, fontWeight: '600', textAlign: 'center' },
  retryBtn: { minHeight: 44, paddingHorizontal: 18, borderRadius: 12, alignItems: 'center', justifyContent: 'center' },
  retryText: { color: '#FFFFFF', fontWeight: '700' },
  toolbar: { flexDirection: 'row' },
  outlineBtn: {
    minHeight: 48,
    borderRadius: 12,
    borderWidth: 1.5,
    paddingHorizontal: 14,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    flex: 1,
  },
  outlineBtnText: { fontWeight: '700', fontSize: 14 },
  hint: { fontSize: 12, lineHeight: 17 },
  coordHint: { fontSize: 11, marginTop: 4, fontVariant: ['tabular-nums'] },
  summary: { borderWidth: 1.5, borderRadius: 16, padding: 14 },
  summaryKicker: { fontSize: 11, fontWeight: '700', letterSpacing: 0.6, textTransform: 'uppercase' },
  summaryTitle: { fontSize: 16, fontWeight: '700', marginTop: 4 },
  summaryMeta: { fontSize: 13, marginTop: 2 },
  primaryBtn: { minHeight: 52, borderRadius: 12, alignItems: 'center', justifyContent: 'center' },
  primaryBtnText: { color: '#FFFFFF', fontWeight: '700', fontSize: 15 },
});
