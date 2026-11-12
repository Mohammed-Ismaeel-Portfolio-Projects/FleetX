// Premium custom dark map styles for react-native-maps MapView configurations
export const DARK_MAP_STYLE = [
  {
    elementType: 'geometry',
    stylers: [{ color: '#0B1220' }],
  },
  {
    elementType: 'labels.text.fill',
    stylers: [{ color: '#627085' }],
  },
  {
    elementType: 'labels.text.stroke',
    stylers: [{ color: '#0B1220' }],
  },
  {
    featureType: 'administrative',
    elementType: 'geometry.stroke',
    stylers: [{ color: '#1E293B' }],
  },
  {
    featureType: 'landscape.natural',
    elementType: 'geometry',
    stylers: [{ color: '#080B16' }],
  },
  {
    featureType: 'road',
    elementType: 'geometry',
    stylers: [{ color: '#1E293B' }],
  },
  {
    featureType: 'road',
    elementType: 'geometry.stroke',
    stylers: [{ color: '#0F172A' }],
  },
  {
    featureType: 'road.highway',
    elementType: 'geometry',
    stylers: [{ color: '#1B294A' }],
  },
  {
    featureType: 'road.highway.controlled_access',
    elementType: 'geometry',
    stylers: [{ color: '#203460' }],
  },
  {
    featureType: 'water',
    elementType: 'geometry',
    stylers: [{ color: '#0F1C3F' }],
  },
];
