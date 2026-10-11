
import { supabase } from '@/src/services/supabase';
import { Feather } from '@expo/vector-icons';
import * as Location from 'expo-location';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import {
    ActivityIndicator,
    Alert,
    Linking,
    RefreshControl,
    SafeAreaView,
    ScrollView,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    View,
} from 'react-native';
import BottomNavbar from '../components/BottomNavbar';

type RecoveryResource = {
  id: string;
  title: string;
  category: string;
  description: string;
  barangay: string | null;
  address: string | null;
  contact_number: string | null;
  latitude: number | null;
  longitude: number | null;
};

type Coordinates = {
  latitude: number;
  longitude: number;
};

type NearbyResource = RecoveryResource & {
  distanceKm: number | null;
};

function calculateDistanceKm(
  origin: Coordinates,
  destination: Coordinates
) {
  const toRadians = (value: number) => (value * Math.PI) / 180;

  const earthRadiusKm = 6371;
  const latitudeDifference = toRadians(
    destination.latitude - origin.latitude
  );
  const longitudeDifference = toRadians(
    destination.longitude - origin.longitude
  );

  const a =
    Math.sin(latitudeDifference / 2) ** 2 +
    Math.cos(toRadians(origin.latitude)) *
      Math.cos(toRadians(destination.latitude)) *
      Math.sin(longitudeDifference / 2) ** 2;

  return (
    earthRadiusKm *
    2 *
    Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
  );
}

export default function NearbyAssistanceScreen() {
  const router = useRouter();
  const params = useLocalSearchParams<{
    location?: string;
    area?: 'inside' | 'outside';
  }>();

  const [resources, setResources] = useState<NearbyResource[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [search, setSearch] = useState('');
  const [origin, setOrigin] = useState<Coordinates | null>(null);
  const [originLabel, setOriginLabel] = useState(
    typeof params.location === 'string' ? params.location : ''
  );
  const [locationLoading, setLocationLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState('');

  const getDeviceLocation = async () => {
    try {
      setLocationLoading(true);

      const permission =
        await Location.requestForegroundPermissionsAsync();

      if (permission.status !== 'granted') {
        Alert.alert(
          'Location Permission Required',
          'Allow location access to sort assistance resources by distance.'
        );
        return;
      }

      const position = await Location.getCurrentPositionAsync({
        accuracy: Location.Accuracy.Balanced,
      });

      const coords = {
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
      };

      setOrigin(coords);
      setOriginLabel('Your current GPS location');
    } catch (error) {
      console.error('Unable to get current location:', error);
      Alert.alert(
        'Location Unavailable',
        'Please try again or continue browsing the verified resources.'
      );
    } finally {
      setLocationLoading(false);
    }
  };

  const loadResources = useCallback(async (refresh = false) => {
    if (refresh) {
      setRefreshing(true);
    } else {
      setLoading(true);
    }

    setErrorMessage('');

    try {
      const { data, error } = await supabase
        .from('recovery_resources')
        .select(
          'id, title, category, description, barangay, address, contact_number, latitude, longitude'
        )
        .eq('is_active', true)
        .eq('verified', true);

      if (error) throw error;

      const validResources = (data ?? []) as RecoveryResource[];

      const sortedResources: NearbyResource[] = validResources
        .map((resource) => {
          let distanceKm: number | null = null;

          if (
            origin &&
            resource.latitude !== null &&
            resource.longitude !== null
          ) {
            distanceKm = calculateDistanceKm(origin, {
              latitude: resource.latitude,
              longitude: resource.longitude,
            });
          }

          return { ...resource, distanceKm };
        })
        .sort((a, b) => {
          if (a.distanceKm === null && b.distanceKm === null) return 0;
          if (a.distanceKm === null) return 1;
          if (b.distanceKm === null) return -1;
          return a.distanceKm - b.distanceKm;
        });

      setResources(sortedResources);
    } catch (error) {
      console.error('Unable to load nearby resources:', error);
      setErrorMessage('Could not load assistance resources. Please try again.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [origin]);

  useEffect(() => {
    loadResources();
  }, [loadResources]);

  const filteredResources = resources.filter((resource) => {
    const query = search.trim().toLowerCase();

    return (
      !query ||
      resource.title.toLowerCase().includes(query) ||
      resource.category.toLowerCase().includes(query) ||
      resource.description.toLowerCase().includes(query) ||
      (resource.barangay ?? '').toLowerCase().includes(query) ||
      (resource.address ?? '').toLowerCase().includes(query)
    );
  });

  const openDirections = async (resource: NearbyResource) => {
    if (resource.latitude === null || resource.longitude === null) {
      Alert.alert(
        'Location Unavailable',
        'This resource does not have coordinates yet.'
      );
      return;
    }

    const url =
      `https://www.google.com/maps/dir/?api=1&destination=` +
      `${resource.latitude},${resource.longitude}`;

    try {
      await Linking.openURL(url);
    } catch {
      Alert.alert('Unable to Open Maps', 'Please try again.');
    }
  };

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView
        contentContainerStyle={styles.content}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={() => loadResources(true)}
            tintColor="#1769AA"
          />
        }
      >
        <View style={styles.header}>
          <TouchableOpacity
            style={styles.backButton}
            onPress={() => router.back()}
          >
            <Feather name="arrow-left" size={21} color="#123B5D" />
          </TouchableOpacity>

          <View style={{ flex: 1 }}>
            <Text style={styles.eyebrow}>RECOVERY SUPPORT</Text>
            <Text style={styles.title}>Nearby Assistance</Text>
          </View>
        </View>

        <View style={styles.locationCard}>
          <Feather name="map-pin" size={22} color="#1769AA" />
          <View style={{ flex: 1 }}>
            <Text style={styles.locationTitle}>Your search location</Text>
            <Text style={styles.locationText}>
              {originLabel || 'Location not selected'}
            </Text>
          </View>
        </View>

        <TouchableOpacity
          style={styles.gpsButton}
          onPress={getDeviceLocation}
          disabled={locationLoading}
        >
          {locationLoading ? (
            <ActivityIndicator color="#1769AA" />
          ) : (
            <Feather name="crosshair" size={18} color="#1769AA" />
          )}
          <Text style={styles.gpsButtonText}>
            {locationLoading ? 'Getting location...' : 'Use Current GPS Location'}
          </Text>
        </TouchableOpacity>

        {!origin && (
          <Text style={styles.helperText}>
            GPS is optional. Without GPS coordinates, resources can still be
            browsed, but distances cannot be calculated accurately.
          </Text>
        )}

        <View style={styles.searchBox}>
          <Feather name="search" size={19} color="#64748B" />
          <TextInput
            style={styles.searchInput}
            value={search}
            onChangeText={setSearch}
            placeholder="Search shelters, relief, medical..."
            placeholderTextColor="#94A3B8"
          />
        </View>

        {!loading && !errorMessage && (
          <Text style={styles.resultCount}>
            {filteredResources.length} verified{' '}
            {filteredResources.length === 1 ? 'resource' : 'resources'} found
          </Text>
        )}

        {loading && (
          <View style={styles.stateContainer}>
            <ActivityIndicator size="large" color="#1769AA" />
            <Text style={styles.stateText}>Finding assistance resources...</Text>
          </View>
        )}

        {!loading && errorMessage !== '' && (
          <View style={styles.stateContainer}>
            <Text style={styles.stateText}>{errorMessage}</Text>
            <TouchableOpacity
              style={styles.primaryButton}
              onPress={() => loadResources()}
            >
              <Text style={styles.primaryButtonText}>Try Again</Text>
            </TouchableOpacity>
          </View>
        )}

        {!loading &&
          !errorMessage &&
          filteredResources.length === 0 && (
            <View style={styles.stateContainer}>
              <Feather name="inbox" size={40} color="#94A3B8" />
              <Text style={styles.emptyTitle}>No resources found</Text>
              <Text style={styles.stateText}>
                Verified listings will appear here when available. Try a
                different search term or refresh the list.
              </Text>
            </View>
          )}

        {!loading &&
          !errorMessage &&
          filteredResources.map((resource) => (
            <View key={resource.id} style={styles.resourceCard}>
              <View style={styles.resourceHeader}>
                <View style={styles.resourceIcon}>
                  <Feather name="life-buoy" size={23} color="#2563EB" />
                </View>
                <View style={{ flex: 1 }}>
                  <Text style={styles.resourceTitle}>{resource.title}</Text>
                  <Text style={styles.category}>{resource.category}</Text>
                </View>
                <Feather name="check-circle" size={19} color="#15803D" />
              </View>

              <Text style={styles.description}>{resource.description}</Text>

              {(resource.address || resource.barangay) && (
                <View style={styles.detailRow}>
                  <Feather name="map-pin" size={15} color="#64748B" />
                  <Text style={styles.detailText}>
                    {[resource.address, resource.barangay]
                      .filter(Boolean)
                      .join(', ')}
                  </Text>
                </View>
              )}

              <View style={styles.distanceRow}>
                <Feather name="navigation" size={15} color="#1769AA" />
                <Text style={styles.distanceText}>
                  {resource.distanceKm !== null
                    ? `${resource.distanceKm.toFixed(1)} km away (straight-line distance)`
                    : 'Distance unavailable'}
                </Text>
              </View>

              <View style={styles.actionRow}>
                {resource.contact_number && (
                  <TouchableOpacity
                    style={styles.secondaryButton}
                    onPress={() => {
                      Linking.openURL(`tel:${resource.contact_number}`).catch(
                        () => Alert.alert('Unable to Call', 'Please try again.')
                      );
                    }}
                  >
                    <Feather name="phone" size={16} color="#1769AA" />
                    <Text style={styles.secondaryButtonText}>Call</Text>
                  </TouchableOpacity>
                )}

                <TouchableOpacity
                  style={styles.primaryButton}
                  onPress={() => openDirections(resource)}
                >
                  <Feather name="navigation" size={16} color="#FFFFFF" />
                  <Text style={styles.primaryButtonText}>Get Directions</Text>
                </TouchableOpacity>
              </View>
            </View>
          ))}

        <View style={styles.notice}>
          <Feather name="info" size={18} color="#92400E" />
          <Text style={styles.noticeText}>
            Distances are approximate straight-line measurements, not travel
            distances. Confirm availability before travelling. For immediate
            danger, contact local emergency services.
          </Text>
        </View>

        <View style={{ height: 110 }} />
      </ScrollView>

      <BottomNavbar activeTab="resources" />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#F8FAFC' },
  content: { padding: 20, paddingBottom: 130 },
  header: { flexDirection: 'row', alignItems: 'center', marginBottom: 22 },
  backButton: {
    width: 42, height: 42, borderRadius: 13, backgroundColor: '#FFFFFF',
    alignItems: 'center', justifyContent: 'center', marginRight: 12,
    borderWidth: 1, borderColor: '#E2E8F0',
  },
  eyebrow: {
    fontSize: 10, fontWeight: '700', letterSpacing: 1, color: '#64748B',
    marginBottom: 4,
  },
  title: { fontSize: 23, fontWeight: '800', color: '#123B5D' },
  locationCard: {
    flexDirection: 'row', alignItems: 'center', gap: 12,
    backgroundColor: '#EAF4FF', borderRadius: 14, padding: 15,
    borderWidth: 1, borderColor: '#D5E8FC',
  },
  locationTitle: { fontSize: 13, fontWeight: '800', color: '#123B5D' },
  locationText: { fontSize: 12, color: '#475569', marginTop: 4 },
  gpsButton: {
    minHeight: 47, flexDirection: 'row', alignItems: 'center',
    justifyContent: 'center', gap: 8, borderWidth: 1, borderColor: '#93C5FD',
    backgroundColor: '#FFFFFF', borderRadius: 12, marginTop: 12,
  },
  gpsButtonText: { fontSize: 13, fontWeight: '700', color: '#1769AA' },
  helperText: {
    fontSize: 12, lineHeight: 18, color: '#64748B', marginTop: 8,
  },
  searchBox: {
    flexDirection: 'row', alignItems: 'center', gap: 10,
    backgroundColor: '#FFFFFF', borderWidth: 1, borderColor: '#CBD5E1',
    borderRadius: 13, paddingHorizontal: 13, minHeight: 49, marginTop: 18,
  },
  searchInput: { flex: 1, color: '#0F172A', fontSize: 14, paddingVertical: 10 },
  resultCount: { fontSize: 12, color: '#64748B', marginTop: 16, marginBottom: 12 },
  stateContainer: {
    alignItems: 'center', justifyContent: 'center', padding: 25,
    backgroundColor: '#FFFFFF', borderRadius: 16, marginVertical: 10,
  },
  stateText: {
    fontSize: 13, color: '#64748B', lineHeight: 20,
    textAlign: 'center', marginTop: 9,
  },
  emptyTitle: {
    fontSize: 16, fontWeight: '800', color: '#123B5D', marginTop: 12,
    textAlign: 'center',
  },
  resourceCard: {
    backgroundColor: '#FFFFFF', borderRadius: 17, padding: 16,
    marginBottom: 13, borderWidth: 1, borderColor: '#E2E8F0',
  },
  resourceHeader: { flexDirection: 'row', alignItems: 'center', gap: 11 },
  resourceIcon: {
    width: 45, height: 45, borderRadius: 13, backgroundColor: '#DBEAFE',
    alignItems: 'center', justifyContent: 'center',
  },
  resourceTitle: { fontSize: 15, fontWeight: '800', color: '#1E293B' },
  category: { fontSize: 12, color: '#2563EB', marginTop: 4, fontWeight: '600' },
  description: { fontSize: 13, lineHeight: 20, color: '#475569', marginTop: 13 },
  detailRow: { flexDirection: 'row', alignItems: 'flex-start', gap: 8, marginTop: 11 },
  detailText: { flex: 1, color: '#64748B', fontSize: 12, lineHeight: 18 },
  distanceRow: { flexDirection: 'row', alignItems: 'center', gap: 7, marginTop: 12 },
  distanceText: { fontSize: 12, color: '#1769AA', fontWeight: '700' },
  actionRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 9, marginTop: 15 },
  secondaryButton: {
    flexDirection: 'row', alignItems: 'center', justifyContent: 'center',
    gap: 7, borderWidth: 1, borderColor: '#93C5FD', borderRadius: 10,
    paddingHorizontal: 15, paddingVertical: 10,
  },
  secondaryButtonText: { color: '#1769AA', fontWeight: '700', fontSize: 12 },
  primaryButton: {
    flexDirection: 'row', alignItems: 'center', justifyContent: 'center',
    gap: 7, backgroundColor: '#2563EB', borderRadius: 10,
    paddingHorizontal: 15, paddingVertical: 10, marginTop: 12,
  },
  primaryButtonText: { color: '#FFFFFF', fontWeight: '700', fontSize: 12 },
  notice: {
    flexDirection: 'row', alignItems: 'flex-start', gap: 9,
    backgroundColor: '#FFFBEB', borderRadius: 12, padding: 13,
    marginTop: 8, borderWidth: 1, borderColor: '#FDE68A',
  },
  noticeText: { flex: 1, color: '#92400E', fontSize: 12, lineHeight: 18 },
});
