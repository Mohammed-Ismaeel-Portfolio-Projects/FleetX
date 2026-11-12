export interface ClientMapCoordinate {
  latitude: number;
  longitude: number;
}

export interface ClientMapMarker extends ClientMapCoordinate {
  id: string;
  title?: string;
  pinColor?: string;
}

export interface ClientMapCanvasProps {
  latitude: number;
  longitude: number;
  latitudeDelta?: number;
  longitudeDelta?: number;
  selected?: ClientMapCoordinate | null;
  markers?: ClientMapMarker[];
  routeCoordinates?: ClientMapCoordinate[];
  interactive?: boolean;
  onPress?: (coordinate: ClientMapCoordinate) => void;
  onReady?: () => void;
  onError?: (message: string) => void;
  testID?: string;
  /** Parent-measured size — required on Android when host would otherwise not layout. */
  width?: number;
  height?: number;
}

