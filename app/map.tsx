
import * as Location from 'expo-location';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  PanResponder,
  Pressable,
  ScrollView,
  StatusBar,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import MapView, { Marker, Polyline } from 'react-native-maps';
import { SafeAreaView } from 'react-native-safe-area-context';

import BottomNavbar from '../components/BottomNavbar';

type Coordinates = {
  latitude: number;
  longitude: number;
};

type EvacuationCenter = {
  id: string;
  name: string;
  barangay: string;
  latitude: number;
  longitude: number;
};

// MOCK DATA ONLY — replace with verified shelter coordinates later.
const evacuationCenters: EvacuationCenter[] = [
  {
    id: 'mock-1',
    name: 'Mock Evacuation Center A',
    barangay: 'Test Barangay A',
    latitude: 14.095,
    longitude: 121.02,
  },
  {
    id: 'mock-2',
    name: 'Mock Evacuation Center B',
    barangay: 'Test Barangay B',
    latitude: 14.085,
    longitude: 121.03,
  },
  {
    id: 'mock-3',
    name: 'Mock Evacuation Center C',
    barangay: 'Test Barangay C',
    latitude: 14.105,
    longitude: 121.01,
  },
];

const DEFAULT_REGION = {
  latitude: 14.0925,
  longitude: 121.0215,
  latitudeDelta: 0.06,
  longitudeDelta: 0.06,
};

function calculateDistance(
  from: Coordinates,
  to: Coordinates
): number {
  const radians = (degrees: number) => (degrees * Math.PI) / 180;
  const earthRadiusKm = 6371;

  const dLat = radians(to.latitude - from.latitude);
  const dLon = radians(to.longitude - from.longitude);

  const a =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(radians(from.latitude)) *
    Math.cos(radians(to.latitude)) *
    Math.sin(dLon / 2) ** 2;

  const safeA = Math.min(1, Math.max(0, a));

  return (
    earthRadiusKm *
    2 *
    Math.atan2(Math.sqrt(safeA), Math.sqrt(1 - safeA))
  );
}

function formatDistance(distance: number): string {
  return distance < 1
    ? `${Math.round(distance * 1000)} m away`
    : `${distance.toFixed(2)} km away`;
}

export default function MapScreen() {
  const mapRef = useRef<MapView>(null);

  const [userLocation, setUserLocation] =
    useState<Coordinates | null>(null);
  const [hasLocationPermission, setHasLocationPermission] =
    useState(false);
  const [loading, setLoading] = useState(false);
  const [mapHeight, setMapHeight] = useState(300);

  const mapHeightRef = useRef(300);
  const startHeightRef = useRef(300);

  const MIN_MAP_HEIGHT = 100;
  const MAX_MAP_HEIGHT = 380;

  const [selectedCenterId, setSelectedCenterId] =
    useState<string | null>(null);
  const [routeCoordinates, setRouteCoordinates] = useState<Coordinates[]>([]);
  const [routeDistance, setRouteDistance] = useState<number | null>(null);
  const [routeDuration, setRouteDuration] = useState<number | null>(null);
  const [routeLoading, setRouteLoading] = useState(false);

  const [locationText, setLocationText] = useState(
    'Allow location access to find the nearest mock center.'
  );

  const panelPanResponder = useRef(
    PanResponder.create({
      onStartShouldSetPanResponder: () => true,

      onMoveShouldSetPanResponder: () => true,

      onPanResponderGrant: () => {
        startHeightRef.current = mapHeightRef.current;
      },

      onPanResponderMove: (_, gesture) => {
        const newHeight = Math.max(
          MIN_MAP_HEIGHT,
          Math.min(
            MAX_MAP_HEIGHT,
            startHeightRef.current + gesture.dy
          )
        );

        mapHeightRef.current = newHeight;
        setMapHeight(newHeight);
      },

      onPanResponderRelease: (_, gesture) => {
        const finalHeight = Math.max(
          MIN_MAP_HEIGHT,
          Math.min(
            MAX_MAP_HEIGHT,
            startHeightRef.current + gesture.dy
          )
        );

        mapHeightRef.current = finalHeight;
        setMapHeight(finalHeight);
      },

      onPanResponderTerminate: () => {
        startHeightRef.current = mapHeightRef.current;
      },

      onPanResponderTerminationRequest: () => false,
    })
  ).current;

  const requestLocation = useCallback(async () => {
    setLoading(true);

    try {
      const permission =
        await Location.requestForegroundPermissionsAsync();

      if (permission.status !== 'granted') {
        setHasLocationPermission(false);
        setLocationText(
          'Location access denied. Enable permission in your device settings.'
        );
        return;
      }

      setHasLocationPermission(true);

      const position = await Location.getCurrentPositionAsync({
        accuracy: Location.Accuracy.Balanced,
      });

      const coordinates: Coordinates = {
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
      };

      setUserLocation(coordinates);
      setRouteCoordinates([]);
      setRouteDistance(null);
      setRouteDuration(null);
      setSelectedCenterId(null);
      setLocationText('Your current GPS location has been detected.');

      mapRef.current?.animateToRegion(
        {
          ...coordinates,
          latitudeDelta: 0.025,
          longitudeDelta: 0.025,
        },
        800
      );
    } catch {
      setLocationText(
        'Unable to get your location. Check Location Services and try again.'
      );

      Alert.alert(
        'Location unavailable',
        'Please check your device settings and try again.'
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void requestLocation();
  }, [requestLocation]);

  const nearestCenters = userLocation
    ? evacuationCenters
        .map((center) => ({
          ...center,
          distance: calculateDistance(userLocation, center),
        }))
        .sort((a, b) => a.distance - b.distance)
    : [];

  // Calculates a road route from the user's GPS location to the selected center.
  // OSRM expects coordinates in longitude,latitude order.
  const focusOnCenter = async (center: EvacuationCenter) => {
    setSelectedCenterId(center.id);
    setRouteCoordinates([]);
    setRouteDistance(null);
    setRouteDuration(null);

    if (!userLocation) {
      Alert.alert(
        'Location Required',
        'Please enable your GPS location first, then select an evacuation center.'
      );
      return;
    }

    setRouteLoading(true);

    try {
      const url =
        `https://router.project-osrm.org/route/v1/driving/` +
        `${userLocation.longitude},${userLocation.latitude};` +
        `${center.longitude},${center.latitude}` +
        '?overview=full&geometries=geojson';

      const response = await fetch(url);

      if (!response.ok) {
        throw new Error('The routing service could not be reached.');
      }

      const data = await response.json();

      if (data.code !== 'Ok' || !data.routes?.length) {
        throw new Error('No driving route was found for this evacuation center.');
      }

      const route = data.routes[0];
      const coordinates: Coordinates[] = route.geometry.coordinates.map(
        ([longitude, latitude]: [number, number]) => ({
          latitude,
          longitude,
        })
      );

      if (coordinates.length < 2) {
        throw new Error('The routing service returned an incomplete route.');
      }

      setRouteCoordinates(coordinates);
      setRouteDistance(route.distance / 1000);
      setRouteDuration(route.duration / 60);

      // Fit both the user's location and the selected destination on screen.
      mapRef.current?.fitToCoordinates(coordinates, {
        edgePadding: {
          top: 90,
          right: 55,
          bottom: 90,
          left: 55,
        },
        animated: true,
      });
    } catch (error) {
      console.error('Route calculation error:', error);
      Alert.alert(
        'Unable to Calculate Route',
        error instanceof Error
          ? error.message
          : 'Please check your internet connection and try again.'
      );
    } finally {
      setRouteLoading(false);
    }
  };

  const clearRoute = () => {
    setSelectedCenterId(null);
    setRouteCoordinates([]);
    setRouteDistance(null);
    setRouteDuration(null);
  };

  const goToMyLocation = async () => {
    if (!userLocation) {
      await requestLocation();
      return;
    }

    mapRef.current?.animateToRegion(
      {
        ...userLocation,
        latitudeDelta: 0.015,
        longitudeDelta: 0.015,
      },
      800
    );
  };

  const centersToDisplay = userLocation
    ? nearestCenters
    : evacuationCenters;

  return (
    <SafeAreaView style={styles.safeArea} edges={['top', 'left', 'right']}>
      <StatusBar barStyle="dark-content" />

      <View style={styles.container}>
        {/* Header stays below the iPhone status bar */}
        <View style={styles.header}>
          <Text style={styles.title}>TaliResQ Map</Text>
          <Text style={styles.subtitle}>
            Your location and nearby evacuation centers
          </Text>
        </View>

        {/* Map height changes explicitly when toggled */}
        <View
          style={[
            styles.mapContainer,
            { height: mapHeight },
          ]}
        >
          <MapView
            ref={mapRef}
            style={styles.map}
            initialRegion={DEFAULT_REGION}
            showsUserLocation={hasLocationPermission}
            showsMyLocationButton={false}
            showsCompass
            showsScale
            mapType="standard"
          >
            {/* Road route from the user's location to the selected center */}
            {routeCoordinates.length > 0 && (
              <Polyline
                coordinates={routeCoordinates}
                strokeColor="#2563EB"
                strokeWidth={5}
                lineCap="round"
                lineJoin="round"
              />
            )}

            {evacuationCenters.map((center) => (
              <Marker
                key={center.id}
                coordinate={{
                  latitude: center.latitude,
                  longitude: center.longitude,
                }}
                title={center.name}
                description={`MOCK SHELTER — ${center.barangay}`}
                pinColor="#16A34A"
                onPress={() => focusOnCenter(center)}
              />
            ))}
          </MapView>

          <View style={styles.mockNotice}>
            <Text style={styles.mockNoticeText}>
              TEST MAP · MOCK SHELTERS
            </Text>
          </View>

          <Pressable
            style={styles.myLocationButton}
            onPress={goToMyLocation}
            disabled={loading}
          >
            {loading ? (
              <ActivityIndicator color="#1769AA" />
            ) : (
              <Text style={styles.myLocationIcon}>◎</Text>
            )}
            <Text style={styles.buttonText}>My Location</Text>
          </Pressable>
        </View>

        {/* White panel fills remaining space and scrolls */}
        <View style={styles.infoCard}>
          <View
            style={styles.panelToggle}
            {...panelPanResponder.panHandlers}
          >
            <View style={styles.panelHandle} />

            <Text style={styles.panelToggleText}>
              Swipe up to shrink map · Swipe down to expand
            </Text>
          </View>

          <ScrollView
            style={styles.panelScroll}
            contentContainerStyle={styles.panelContent}
            showsVerticalScrollIndicator
            nestedScrollEnabled
          >
            <Text style={styles.cardTitle}>Your Location</Text>
            <Text style={styles.locationText}>{locationText}</Text>

            <Pressable
              style={[
                styles.refreshButton,
                loading && styles.disabledButton,
              ]}
              onPress={requestLocation}
              disabled={loading}
            >
              {loading ? (
                <ActivityIndicator color="#FFFFFF" />
              ) : (
                <Text style={styles.refreshButtonText}>
                  Update My Location
                </Text>
              )}
            </Pressable>

            <View style={styles.sectionHeading}>
              <Text style={styles.cardTitle}>
                Nearest Evacuation Centers
              </Text>
              <Text style={styles.centerCount}>
                {evacuationCenters.length}
              </Text>
            </View>

            <Text style={styles.locationText}>
              {userLocation
                ? 'Sorted from nearest to farthest by straight-line distance.'
                : 'Enable location access to calculate distances. Centers are shown below.'}
            </Text>

            {routeLoading && (
              <View style={styles.routeInfo}>
                <ActivityIndicator color="#1769AA" />
                <Text style={styles.locationText}>Calculating route...</Text>
              </View>
            )}

            {routeCoordinates.length > 0 && (
              <View style={styles.routeInfo}>
                <View style={styles.routeHeader}>
                  <Text style={styles.routeTitle}>Route to Selected Center</Text>
                  <Pressable onPress={clearRoute}>
                    <Text style={styles.clearRouteText}>Clear</Text>
                  </Pressable>
                </View>

                <View style={styles.routeDetails}>
                  <View>
                    <Text style={styles.routeValue}>
                      {routeDistance !== null
                        ? `${routeDistance.toFixed(2)} km`
                        : '--'}
                    </Text>
                    <Text style={styles.routeLabel}>Road distance</Text>
                  </View>

                  <View>
                    <Text style={styles.routeValue}>
                      {routeDuration !== null
                        ? `${Math.ceil(routeDuration)} min`
                        : '--'}
                    </Text>
                    <Text style={styles.routeLabel}>Estimated driving time</Text>
                  </View>
                </View>

                <Text style={styles.routeDisclaimer}>
                  Driving estimate only. Actual travel time and road accessibility may vary.
                </Text>
              </View>
            )}

            {centersToDisplay.map((center, index) => {
              const distance = userLocation
                ? calculateDistance(userLocation, center)
                : undefined;

              const selected = selectedCenterId === center.id;

              return (
                <Pressable
                  key={center.id}
                  onPress={() => focusOnCenter(center)}
                  style={[
                    styles.centerItem,
                    selected && styles.selectedCenterItem,
                  ]}
                >
                  <View style={styles.centerNumber}>
                    <Text style={styles.centerNumberText}>
                      {index + 1}
                    </Text>
                  </View>

                  <View style={styles.centerDetails}>
                    <Text style={styles.centerName}>
                      {center.name}
                    </Text>
                    <Text style={styles.centerBarangay}>
                      {center.barangay}
                    </Text>
                    <Text style={styles.centerDistance}>
                      {distance === undefined
                        ? 'Distance unavailable'
                        : formatDistance(distance)}
                    </Text>
                  </View>

                  <Text style={styles.viewMapText}>View</Text>
                </Pressable>
              );
            })}

            <Text style={styles.disclaimer}>
              Mock locations are for development only. They are not
              verified evacuation shelters.
            </Text>
          </ScrollView>
        </View>

        <BottomNavbar activeTab="map" />
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: '#FFFFFF',
  },
  container: {
    flex: 1,
    backgroundColor: '#F8FAFC',
  },
  header: {
    paddingHorizontal: 18,
    paddingTop: 8,
    paddingBottom: 12,
    backgroundColor: '#FFFFFF',
  },
  title: {
    fontSize: 23,
    fontWeight: 'bold',
    color: '#123B5D',
  },
  subtitle: {
    fontSize: 13,
    color: '#64748B',
    marginTop: 4,
  },
  mapContainer: {
    width: '100%',
    flexShrink: 0,
    overflow: 'hidden',
  },
  map: {
    position: 'absolute',
    top: 0,
    right: 0,
    bottom: 0,
    left: 0,
    width: '100%',
    height: '100%',
  },
  mockNotice: {
    position: 'absolute',
    top: 12,
    left: 12,
    backgroundColor: '#FEF3C7',
    borderRadius: 8,
    paddingHorizontal: 10,
    paddingVertical: 7,
  },
  mockNoticeText: {
    color: '#92400E',
    fontSize: 10,
    fontWeight: 'bold',
  },
  myLocationButton: {
    position: 'absolute',
    right: 14,
    bottom: 16,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: '#FFFFFF',
    paddingVertical: 12,
    paddingHorizontal: 15,
    borderRadius: 12,
    elevation: 4,
  },
  myLocationIcon: {
    fontSize: 22,
    color: '#1769AA',
    fontWeight: 'bold',
  },
  buttonText: {
    color: '#123B5D',
    fontWeight: '600',
  },
  infoCard: {
    flex: 1,
    minHeight: 0,
    backgroundColor: '#FFFFFF',
    paddingHorizontal: 16,
    paddingTop: 8,
    borderTopLeftRadius: 18,
    borderTopRightRadius: 18,
  },
  panelToggle: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 16,
    gap: 6,
    minHeight: 60,
  },
  panelHandle: {
    width: 44,
    height: 5,
    borderRadius: 3,
    backgroundColor: '#94A3B8',
  },
  panelToggleText: {
    fontSize: 11,
    color: '#64748B',
  },
  panelScroll: {
    flex: 1,
    minHeight: 0,
  },
  panelContent: {
    paddingBottom: 18,
    gap: 8,
  },
  cardTitle: {
    color: '#123B5D',
    fontSize: 17,
    fontWeight: 'bold',
    flexShrink: 1,
  },
  locationText: {
    color: '#64748B',
    fontSize: 13,
    lineHeight: 19,
  },
  refreshButton: {
    backgroundColor: '#1769AA',
    padding: 12,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 42,
    marginVertical: 4,
  },
  disabledButton: {
    opacity: 0.7,
  },
  refreshButtonText: {
    color: '#FFFFFF',
    fontWeight: 'bold',
  },
  sectionHeading: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: 8,
    marginTop: 6,
  },
  centerCount: {
    color: '#1769AA',
    fontSize: 13,
    fontWeight: 'bold',
    backgroundColor: '#DBEAFE',
    overflow: 'hidden',
    borderRadius: 12,
    paddingHorizontal: 9,
    paddingVertical: 4,
  },
  centerItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: 12,
    marginTop: 4,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#E2E8F0',
    backgroundColor: '#FFFFFF',
  },
  selectedCenterItem: {
    borderColor: '#16A34A',
    backgroundColor: '#F0FDF4',
  },
  centerNumber: {
    width: 30,
    height: 30,
    borderRadius: 15,
    backgroundColor: '#DCFCE7',
    alignItems: 'center',
    justifyContent: 'center',
  },
  centerNumberText: {
    color: '#166534',
    fontWeight: 'bold',
  },
  centerDetails: {
    flex: 1,
    gap: 3,
  },
  centerName: {
    color: '#123B5D',
    fontWeight: 'bold',
    fontSize: 13,
  },
  centerBarangay: {
    color: '#64748B',
    fontSize: 12,
  },
  centerDistance: {
    color: '#15803D',
    fontWeight: '600',
    fontSize: 12,
    marginTop: 2,
  },
  viewMapText: {
    color: '#1769AA',
    fontSize: 12,
    fontWeight: 'bold',
  },
  routeInfo: {
    backgroundColor: '#EFF6FF',
    borderRadius: 12,
    padding: 14,
    marginTop: 8,
    marginBottom: 4,
    gap: 10,
    borderWidth: 1,
    borderColor: '#BFDBFE',
  },
  routeHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 8,
  },
  routeTitle: {
    color: '#123B5D',
    fontSize: 14,
    fontWeight: 'bold',
  },
  clearRouteText: {
    color: '#DC2626',
    fontSize: 12,
    fontWeight: '600',
  },
  routeDetails: {
    flexDirection: 'row',
    justifyContent: 'space-around',
    paddingVertical: 6,
  },
  routeValue: {
    color: '#1769AA',
    fontSize: 20,
    fontWeight: 'bold',
  },
  routeLabel: {
    color: '#64748B',
    fontSize: 11,
    marginTop: 3,
  },
  routeDisclaimer: {
    color: '#64748B',
    fontSize: 10,
    lineHeight: 15,
  },
  disclaimer: {
    color: '#92400E',
    fontSize: 11,
    lineHeight: 16,
    marginTop: 8,
  },
});
