import { useEffect, useId, useMemo, useRef, useState } from 'react';
import { LayoutChangeEvent, StyleSheet, View } from 'react-native';
import type { ClientMapCanvasProps } from './mapCanvasTypes';

function buildWebMapDocument(
  lat: number,
  lng: number,
  delta: number,
  interactive: boolean
): string {
  const initLat = Number.isFinite(lat) ? lat : 33.5138;
  const initLng = Number.isFinite(lng) ? lng : 36.2765;
  const initZoom = delta > 20 ? 2 : delta > 2 ? 5 : delta > 0.2 ? 11 : 13;

  return `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <style>
    html, body, #map { height: 100%; width: 100%; margin: 0; padding: 0; background: #E8EEF5; }
    .leaflet-container { background: #E8EEF5; }
  </style>
</head>
<body>
  <div id="map"></div>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    (function () {
      var interactive = ${interactive ? 'true' : 'false'};
      var map = L.map('map', {
        center: [${initLat}, ${initLng}],
        zoom: ${initZoom},
        zoomControl: interactive,
        dragging: interactive,
        scrollWheelZoom: interactive,
        doubleClickZoom: interactive,
        boxZoom: interactive,
        keyboard: interactive,
        tap: interactive
      });
      var markerLayer = L.layerGroup().addTo(map);
      var routeLayer = L.layerGroup().addTo(map);

      function post(payload) {
        if (window.parent && window.parent.postMessage) {
          window.parent.postMessage(payload, '*');
        }
      }

      function setView(lat, lng, delta) {
        var zoom = delta > 20 ? 2 : delta > 2 ? 5 : delta > 0.2 ? 11 : 13;
        map.setView([lat, lng], zoom);
      }

      function handleSync(data) {
        map.invalidateSize({ animate: false });
        markerLayer.clearLayers();
        routeLayer.clearLayers();
        if (typeof data.latitude === 'number' && typeof data.longitude === 'number') {
          setView(data.latitude, data.longitude, data.latitudeDelta || 0.05);
        }
        (data.markers || []).forEach(function (item) {
          var options = {};
          if (item.pinColor) {
            var pinIcon = L.divIcon({
              className: 'custom-marker-' + item.id,
              html: '<div style="background-color:' + item.pinColor + ';width:16px;height:16px;border-radius:50%;border:2px solid white;box-shadow:0 2px 4px rgba(0,0,0,0.4);"></div>',
              iconSize: [16, 16],
              iconAnchor: [8, 8]
            });
            options.icon = pinIcon;
          }
          L.marker([item.latitude, item.longitude], options).bindPopup(item.title || '').addTo(markerLayer);
        });
        if (data.routeCoordinates && data.routeCoordinates.length >= 2) {
          var latLngs = data.routeCoordinates.map(function (item) {
            return [item.latitude, item.longitude];
          });
          L.polyline(latLngs, { color: '#1E75FF', weight: 4, opacity: 0.85 }).addTo(routeLayer);
        }
        if (data.selected) {
          var pinIcon = L.divIcon({
            className: 'custom-pin',
            html: '<div style="background-color:#EF4444;width:18px;height:18px;border-radius:50%;border:2px solid white;box-shadow:0 2px 4px rgba(0,0,0,0.4);"></div>',
            iconSize: [18, 18],
            iconAnchor: [9, 9]
          });
          L.marker([data.selected.latitude, data.selected.longitude], { icon: pinIcon }).addTo(markerLayer);
        }
        if (data.fit) {
          var points = (data.markers || []).concat(data.routeCoordinates || []);
          if (points.length >= 2) {
            var bounds = L.latLngBounds(points.map(function (item) {
              return [item.latitude, item.longitude];
            }));
            map.fitBounds(bounds, { padding: [40, 40] });
          }
        }
      }

      window.addEventListener('message', function (event) {
        var data = event.data;
        if (typeof data === 'string') {
          try { data = JSON.parse(data); } catch(e) {}
        }
        data = data || {};
        if (data.type === 'fleetx-map-sync') handleSync(data);
      });

      if (interactive) {
        map.on('click', function (e) {
          post({ type: 'fleetx-map-pick', latitude: e.latlng.lat, longitude: e.latlng.lng });
        });
      }

      L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
      }).addTo(map);

      map.whenReady(function () {
        setTimeout(function () {
          map.invalidateSize({ animate: false });
          post({ type: 'fleetx-map-ready' });
        }, 50);
      });
    })();
  </script>
</body>
</html>`;
}

/**
 * Web map surface for Client pickup/delivery (Leaflet OpenStreetMap).
 */
export function ClientMapCanvas({
  latitude,
  longitude,
  latitudeDelta = 0.05,
  longitudeDelta = 0.05,
  selected = null,
  markers = [],
  routeCoordinates = [],
  interactive = true,
  onPress,
  onReady,
  onError: _onError,
  testID,
  width: widthProp,
  height: heightProp,
}: ClientMapCanvasProps) {
  const iframeRef = useRef<HTMLIFrameElement>(null);
  const readyRef = useRef(false);
  const iframeId = useId();
  const [measured, setMeasured] = useState<{ width: number; height: number } | null>(null);

  const width =
    typeof widthProp === 'number' && widthProp > 1
      ? widthProp
      : measured?.width && measured.width > 1
        ? measured.width
        : null;
  const height =
    typeof heightProp === 'number' && heightProp > 1
      ? heightProp
      : measured?.height && measured.height > 1
        ? measured.height
        : null;
  const hasSize = width != null && height != null;

  const markReady = () => {
    if (readyRef.current) return;
    readyRef.current = true;
    onReady?.();
  };

  const htmlDoc = useMemo(
    () => buildWebMapDocument(latitude, longitude, latitudeDelta, interactive),
    [latitude, longitude, latitudeDelta, interactive]
  );

  const postIframeSync = () => {
    const contentWindow = iframeRef.current?.contentWindow;
    if (!contentWindow) return;
    const payload = {
      type: 'fleetx-map-sync',
      latitude,
      longitude,
      latitudeDelta,
      longitudeDelta,
      selected,
      markers,
      routeCoordinates,
      fit: (markers.length >= 2 || routeCoordinates.length >= 2) && !interactive,
    };
    contentWindow.postMessage(payload, '*');
  };

  useEffect(() => {
    const handleMessage = (event: MessageEvent) => {
      let data = event.data;
      if (typeof data === 'string') {
        try {
          data = JSON.parse(data);
        } catch {
          return;
        }
      }
      if (!data || typeof data !== 'object') return;
      if (data.type === 'fleetx-map-ready') {
        markReady();
        postIframeSync();
      }
      if (
        data.type === 'fleetx-map-pick' &&
        typeof data.latitude === 'number' &&
        typeof data.longitude === 'number'
      ) {
        onPress?.({ latitude: data.latitude, longitude: data.longitude });
      }
    };

    window.addEventListener('message', handleMessage);
    return () => {
      readyRef.current = false;
      window.removeEventListener('message', handleMessage);
    };
  }, [onPress]);

  useEffect(() => {
    const timer = setTimeout(postIframeSync, 100);
    return () => clearTimeout(timer);
  }, [latitude, longitude, latitudeDelta, longitudeDelta, hasSize, selected, markers, routeCoordinates, interactive]);

  const onHostLayout = (event: LayoutChangeEvent) => {
    if (widthProp && heightProp) return;
    const { width: w, height: h } = event.nativeEvent.layout;
    if (!(w > 1) || !(h > 1)) return;
    setMeasured((prev) => {
      if (prev && Math.abs(prev.width - w) < 1 && Math.abs(prev.height - h) < 1) return prev;
      return { width: w, height: h };
    });
  };

  return (
    <View
      style={styles.host}
      testID={testID}
      collapsable={false}
      onLayout={onHostLayout}>
      {hasSize ? (
        <iframe
          id={iframeId}
          ref={iframeRef}
          srcDoc={htmlDoc}
          style={{
            width: `${width}px`,
            height: `${height}px`,
            border: 'none',
            backgroundColor: '#E8EEF5',
          }}
          onLoad={() => {
            markReady();
            postIframeSync();
            setTimeout(postIframeSync, 200);
          }}
        />
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  host: {
    width: '100%',
    height: '100%',
    backgroundColor: '#E8EEF5',
    overflow: 'hidden',
  },
});
