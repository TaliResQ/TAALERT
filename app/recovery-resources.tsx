
import { supabase } from '@/src/services/supabase';
import { Feather } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
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

export default function RecoveryResourcesScreen() {
  const router = useRouter();

  const [resources, setResources] = useState<RecoveryResource[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [search, setSearch] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('All');
  const [errorMessage, setErrorMessage] = useState('');

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
        .eq('verified', true)
        .order('title', { ascending: true });

      if (error) throw error;

      setResources((data ?? []) as RecoveryResource[]);
    } catch (error) {
      console.error('Unable to load recovery resources:', error);
      setErrorMessage('Could not load resources. Please try again.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    loadResources();
  }, [loadResources]);

  const categories = [
    'All',
    ...Array.from(new Set(resources.map((item) => item.category))),
  ];

  const filteredResources = resources.filter((item) => {
    const query = search.trim().toLowerCase();

    const matchesSearch =
      !query ||
      item.title.toLowerCase().includes(query) ||
      item.category.toLowerCase().includes(query) ||
      item.description.toLowerCase().includes(query) ||
      (item.barangay ?? '').toLowerCase().includes(query) ||
      (item.address ?? '').toLowerCase().includes(query);

    const matchesCategory =
      selectedCategory === 'All' ||
      item.category === selectedCategory;

    return matchesSearch && matchesCategory;
  });

  const getCategoryIcon = (category: string) => {
    const value = category.toLowerCase();

    if (value.includes('medical') || value.includes('health')) return 'heart';
    if (value.includes('shelter') || value.includes('housing')) return 'home';
    if (
      value.includes('food') ||
      value.includes('relief') ||
      value.includes('water')
    ) return 'package';
    if (value.includes('livelihood') || value.includes('financial')) {
      return 'briefcase';
    }

    return 'life-buoy';
  };

  const callResource = async (phone: string) => {
    try {
      await Linking.openURL(`tel:${phone.replace(/[^\d+]/g, '')}`);
    } catch {
      Alert.alert('Unable to Call', 'Your device could not open the phone app.');
    }
  };

  const openMap = async (resource: RecoveryResource) => {
    if (resource.latitude == null || resource.longitude == null) {
      Alert.alert(
        'Location Unavailable',
        'This resource does not have map coordinates yet.'
      );
      return;
    }

    try {
      await Linking.openURL(
        `https://www.google.com/maps/search/?api=1&query=${resource.latitude},${resource.longitude}`
      );
    } catch {
      Alert.alert('Unable to Open Map', 'Please try again.');
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
            <Text style={styles.title}>Recovery Resources</Text>
          </View>
        </View>

        <View style={styles.introCard}>
          <Feather name="heart" size={28} color="#1769AA" />
          <Text style={styles.introTitle}>Find verified support</Text>
          <Text style={styles.introDescription}>
            Browse recovery services and assistance resources. Only active,
            verified listings are shown.
          </Text>
        </View>

        <View style={styles.searchBox}>
          <Feather name="search" size={19} color="#64748B" />
          <TextInput
            style={styles.searchInput}
            value={search}
            onChangeText={setSearch}
            placeholder="Search resources or barangay"
            placeholderTextColor="#94A3B8"
          />
          {search.length > 0 && (
            <TouchableOpacity onPress={() => setSearch('')}>
              <Feather name="x-circle" size={18} color="#94A3B8" />
            </TouchableOpacity>
          )}
        </View>

        {!loading && resources.length > 0 && (
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={styles.categoryList}
          >
            {[
              'All',
              ...Array.from(new Set(resources.map((item) => item.category))),
            ].map((category) => {
              const selected = selectedCategory === category;

              return (
                <TouchableOpacity
                  key={category}
                  style={[
                    styles.categoryChip,
                    selected && styles.categoryChipSelected,
                  ]}
                  onPress={() => setSelectedCategory(category)}
                >
                  <Text
                    style={[
                      styles.categoryChipText,
                      selected && styles.categoryChipTextSelected,
                    ]}
                  >
                    {category}
                  </Text>
                </TouchableOpacity>
              );
            })}
          </ScrollView>
        )}

        {!loading && !errorMessage && (
          <Text style={styles.resultCount}>
            {filteredResources.length}{' '}
            {filteredResources.length === 1 ? 'resource' : 'resources'} found
          </Text>
        )}

        {loading && (
          <View style={styles.stateContainer}>
            <ActivityIndicator size="large" color="#1769AA" />
            <Text style={styles.stateText}>Loading verified resources...</Text>
          </View>
        )}

        {!loading && errorMessage !== '' && (
          <View style={styles.stateContainer}>
            <Feather name="alert-circle" size={38} color="#DC2626" />
            <Text style={styles.emptyTitle}>Unable to load resources</Text>
            <Text style={styles.stateText}>{errorMessage}</Text>
            <TouchableOpacity
              style={styles.retryButton}
              onPress={() => loadResources()}
            >
              <Text style={styles.retryButtonText}>Try Again</Text>
            </TouchableOpacity>
          </View>
        )}

        {!loading && !errorMessage && resources.length === 0 && (
          <View style={styles.stateContainer}>
            <Feather name="inbox" size={42} color="#94A3B8" />
            <Text style={styles.emptyTitle}>No verified resources yet</Text>
            <Text style={styles.stateText}>
              Listings will appear here when an authorized administrator adds
              and verifies recovery resources.
            </Text>
            <TouchableOpacity
              style={styles.retryButton}
              onPress={() => loadResources()}
            >
              <Text style={styles.retryButtonText}>Refresh</Text>
            </TouchableOpacity>
          </View>
        )}

        {!loading &&
          !errorMessage &&
          resources.length > 0 &&
          filteredResources.length === 0 && (
            <View style={styles.stateContainer}>
              <Feather name="search" size={38} color="#94A3B8" />
              <Text style={styles.emptyTitle}>No matching resources</Text>
              <Text style={styles.stateText}>
                Try another keyword or category.
              </Text>
            </View>
          )}

        {!loading &&
          !errorMessage &&
          filteredResources.map((resource) => (
            <View key={resource.id} style={styles.resourceCard}>
              <View style={styles.resourceHeader}>
                <View style={styles.resourceIcon}>
                  <Feather
                    name={getCategoryIcon(resource.category) as any}
                    size={23}
                    color="#2563EB"
                  />
                </View>

                <View style={styles.resourceHeading}>
                  <Text style={styles.resourceTitle}>{resource.title}</Text>
                  <Text style={styles.resourceCategory}>
                    {resource.category}
                  </Text>
                </View>

                <Feather name="check-circle" size={20} color="#15803D" />
              </View>

              <Text style={styles.description}>{resource.description}</Text>

              {(resource.barangay || resource.address) && (
                <View style={styles.detailRow}>
                  <Feather name="map-pin" size={16} color="#64748B" />
                  <Text style={styles.detailText}>
                    {[resource.address, resource.barangay]
                      .filter(Boolean)
                      .join(', ')}
                  </Text>
                </View>
              )}

              <View style={styles.actionRow}>
                {resource.contact_number && (
                  <TouchableOpacity
                    style={styles.secondaryButton}
                    onPress={() => callResource(resource.contact_number!)}
                  >
                    <Feather name="phone" size={16} color="#1769AA" />
                    <Text style={styles.secondaryButtonText}>Call</Text>
                  </TouchableOpacity>
                )}

                <TouchableOpacity
                  style={styles.mapButton}
                  onPress={() => openMap(resource)}
                >
                  <Feather name="map" size={16} color="#FFFFFF" />
                  <Text style={styles.mapButtonText}>View Map</Text>
                </TouchableOpacity>
              </View>
            </View>
          ))}

        <View style={styles.notice}>
          <Feather name="info" size={18} color="#92400E" />
          <Text style={styles.noticeText}>
            Please contact the listed provider to confirm availability before
            travelling. For immediate danger, contact local emergency services.
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
  introCard: {
    backgroundColor: '#EAF4FF', borderRadius: 18, padding: 18,
    marginBottom: 20, borderWidth: 1, borderColor: '#D5E8FC',
  },
  introTitle: {
    fontSize: 19, fontWeight: '800', color: '#123B5D', marginTop: 12,
    marginBottom: 6,
  },
  introDescription: { fontSize: 13, lineHeight: 20, color: '#475569' },
  searchBox: {
    flexDirection: 'row', alignItems: 'center', gap: 10,
    backgroundColor: '#FFFFFF', borderWidth: 1, borderColor: '#CBD5E1',
    borderRadius: 13, paddingHorizontal: 13, minHeight: 49,
  },
  searchInput: { flex: 1, color: '#0F172A', fontSize: 14, paddingVertical: 10 },
  categoryList: { gap: 8, paddingVertical: 14 },
  categoryChip: {
    paddingHorizontal: 13, paddingVertical: 9, borderRadius: 20,
    backgroundColor: '#FFFFFF', borderWidth: 1, borderColor: '#CBD5E1',
  },
  categoryChipSelected: { backgroundColor: '#DBEAFE', borderColor: '#2563EB' },
  categoryChipText: { fontSize: 12, color: '#475569', fontWeight: '600' },
  categoryChipTextSelected: { color: '#1D4ED8' },
  resultCount: { color: '#64748B', fontSize: 12, marginBottom: 12 },
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
  retryButton: {
    backgroundColor: '#2563EB', paddingHorizontal: 20, paddingVertical: 11,
    borderRadius: 10, marginTop: 15,
  },
  retryButtonText: { color: '#FFFFFF', fontWeight: '700', fontSize: 13 },
  resourceCard: {
    backgroundColor: '#FFFFFF', borderRadius: 17, padding: 16,
    marginBottom: 13, borderWidth: 1, borderColor: '#E2E8F0',
  },
  resourceHeader: { flexDirection: 'row', alignItems: 'center', gap: 11 },
  resourceIcon: {
    width: 46, height: 46, borderRadius: 13, backgroundColor: '#DBEAFE',
    alignItems: 'center', justifyContent: 'center',
  },
  resourceHeading: { flex: 1 },
  resourceTitle: { fontSize: 15, fontWeight: '800', color: '#1E293B' },
  resourceCategory: {
    fontSize: 12, color: '#2563EB', marginTop: 4, fontWeight: '600',
  },
  description: {
    fontSize: 13, lineHeight: 20, color: '#475569', marginTop: 14,
  },
  detailRow: {
    flexDirection: 'row', alignItems: 'flex-start', gap: 8, marginTop: 12,
  },
  detailText: { flex: 1, color: '#64748B', fontSize: 12, lineHeight: 18 },
  actionRow: { flexDirection: 'row', gap: 9, marginTop: 16 },
  secondaryButton: {
    flexDirection: 'row', alignItems: 'center', justifyContent: 'center',
    gap: 7, borderWidth: 1, borderColor: '#93C5FD', borderRadius: 10,
    paddingHorizontal: 15, paddingVertical: 10,
  },
  secondaryButtonText: { color: '#1769AA', fontWeight: '700', fontSize: 12 },
  mapButton: {
    flexDirection: 'row', alignItems: 'center', justifyContent: 'center',
    gap: 7, backgroundColor: '#2563EB', borderRadius: 10,
    paddingHorizontal: 15, paddingVertical: 10,
  },
  mapButtonText: { color: '#FFFFFF', fontWeight: '700', fontSize: 12 },
  notice: {
    flexDirection: 'row', alignItems: 'flex-start', gap: 9,
    backgroundColor: '#FFFBEB', borderRadius: 12, padding: 13,
    marginTop: 8, borderWidth: 1, borderColor: '#FDE68A',
  },
  noticeText: { flex: 1, color: '#92400E', fontSize: 12, lineHeight: 18 },
});
