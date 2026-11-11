
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
  resource_id: string;
  title: string;
  category: string;
  description: string;
  barangay: string | null;
  address: string | null;
  contact_number: string | null;
};

export default function NearbyAssistanceScreen() {
  const router = useRouter();

  const [resources, setResources] = useState<RecoveryResource[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [search, setSearch] = useState('');
  const [errorMessage, setErrorMessage] = useState('');

  // Load verified recovery resources from Supabase
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
          'resource_id, title, category, description, barangay, address, contact_number'
        )
        .eq('is_active', true)
        .eq('verified', true)
        .order('title', { ascending: true });

      if (error) throw error;

      setResources((data ?? []) as RecoveryResource[]);
    } catch (error) {
      console.error('Unable to load recovery resources:', error);
      setErrorMessage(
        'Could not load assistance resources. Please try again.'
      );
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    loadResources();
  }, [loadResources]);

  // Search and filter resources
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

  // Call assistance provider
  const callResource = async (phone: string) => {
    const cleanedPhone = phone.replace(/[^\d+]/g, '');

    try {
      await Linking.openURL(`tel:${cleanedPhone}`);
    } catch {
      Alert.alert(
        'Unable to Call',
        'Your device could not open the phone app. Please try again.'
      );
    }
  };

  // Category icon
  const getCategoryIcon = (category: string): string => {
    const value = category.toLowerCase();

    if (value.includes('medical') || value.includes('health')) {
      return 'heart';
    }

    if (value.includes('shelter') || value.includes('housing')) {
      return 'home';
    }

    if (
      value.includes('food') ||
      value.includes('relief') ||
      value.includes('water')
    ) {
      return 'package';
    }

    if (
      value.includes('livelihood') ||
      value.includes('financial')
    ) {
      return 'briefcase';
    }

    return 'life-buoy';
  };

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView
        contentContainerStyle={styles.content}
        showsVerticalScrollIndicator={false}
        keyboardShouldPersistTaps="handled"
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={() => loadResources(true)}
            tintColor="#1769AA"
          />
        }
      >
        {/* Header */}
        <View style={styles.header}>
          <TouchableOpacity
            style={styles.backButton}
            onPress={() => router.back()}
          >
            <Feather
              name="arrow-left"
              size={21}
              color="#123B5D"
            />
          </TouchableOpacity>

          <View style={{ flex: 1 }}>
            <Text style={styles.eyebrow}>RECOVERY SUPPORT</Text>
            <Text style={styles.title}>Nearby Assistance</Text>
          </View>
        </View>

        {/* Introduction */}
        <View style={styles.locationCard}>
          <Feather
            name="life-buoy"
            size={25}
            color="#1769AA"
          />

          <View style={{ flex: 1 }}>
            <Text style={styles.locationTitle}>
              Verified Recovery Support
            </Text>

            <Text style={styles.locationText}>
              Browse available recovery services and contact providers
              directly for assistance.
            </Text>
          </View>
        </View>

        {/* Search */}
        <View style={styles.searchBox}>
          <Feather
            name="search"
            size={19}
            color="#64748B"
          />

          <TextInput
            style={styles.searchInput}
            value={search}
            onChangeText={setSearch}
            placeholder="Search shelters, relief, medical..."
            placeholderTextColor="#94A3B8"
          />

          {search.length > 0 && (
            <TouchableOpacity onPress={() => setSearch('')}>
              <Feather
                name="x-circle"
                size={18}
                color="#94A3B8"
              />
            </TouchableOpacity>
          )}
        </View>

        {/* Result Count */}
        {!loading && !errorMessage && (
          <Text style={styles.resultCount}>
            {filteredResources.length} verified{' '}
            {filteredResources.length === 1
              ? 'resource'
              : 'resources'}{' '}
            found
          </Text>
        )}

        {/* Loading */}
        {loading && (
          <View style={styles.stateContainer}>
            <ActivityIndicator
              size="large"
              color="#1769AA"
            />

            <Text style={styles.stateText}>
              Finding assistance resources...
            </Text>
          </View>
        )}

        {/* Error */}
        {!loading && errorMessage !== '' && (
          <View style={styles.stateContainer}>
            <Feather
              name="alert-circle"
              size={38}
              color="#DC2626"
            />

            <Text style={styles.emptyTitle}>
              Unable to Load Resources
            </Text>

            <Text style={styles.stateText}>
              {errorMessage}
            </Text>

            <TouchableOpacity
              style={styles.primaryButton}
              onPress={() => loadResources()}
            >
              <Text style={styles.primaryButtonText}>
                Try Again
              </Text>
            </TouchableOpacity>
          </View>
        )}

        {/* Empty Resources */}
        {!loading &&
          !errorMessage &&
          resources.length === 0 && (
            <View style={styles.stateContainer}>
              <Feather
                name="inbox"
                size={42}
                color="#94A3B8"
              />

              <Text style={styles.emptyTitle}>
                No Verified Resources Yet
              </Text>

              <Text style={styles.stateText}>
                Verified recovery assistance listings will appear
                here once authorized administrators add resources.
              </Text>

              <TouchableOpacity
                style={styles.primaryButton}
                onPress={() => loadResources()}
              >
                <Text style={styles.primaryButtonText}>
                  Refresh Resources
                </Text>
              </TouchableOpacity>
            </View>
          )}

        {/* No Search Results */}
        {!loading &&
          !errorMessage &&
          resources.length > 0 &&
          filteredResources.length === 0 && (
            <View style={styles.stateContainer}>
              <Feather
                name="search"
                size={38}
                color="#94A3B8"
              />

              <Text style={styles.emptyTitle}>
                No Matching Resources
              </Text>

              <Text style={styles.stateText}>
                Try another keyword or search for a different
                category or barangay.
              </Text>
            </View>
          )}

        {/* Resource Cards */}
        {!loading &&
          !errorMessage &&
          filteredResources.map((resource) => (
            <View
              key={resource.resource_id}
              style={styles.resourceCard}
            >
              {/* Resource Header */}
              <View style={styles.resourceHeader}>
                <View style={styles.resourceIcon}>
                  <Feather
                    name={getCategoryIcon(resource.category) as any}
                    size={23}
                    color="#2563EB"
                  />
                </View>

                <View style={styles.resourceHeading}>
                  <Text style={styles.resourceTitle}>
                    {resource.title}
                  </Text>

                  <Text style={styles.category}>
                    {resource.category}
                  </Text>
                </View>

                <Feather
                  name="check-circle"
                  size={20}
                  color="#15803D"
                />
              </View>

              {/* Description */}
              <Text style={styles.description}>
                {resource.description}
              </Text>

              {/* Address */}
              {(resource.address || resource.barangay) && (
                <View style={styles.detailRow}>
                  <Feather
                    name="map-pin"
                    size={16}
                    color="#64748B"
                  />

                  <Text style={styles.detailText}>
                    {[resource.address, resource.barangay]
                      .filter(Boolean)
                      .join(', ')}
                  </Text>
                </View>
              )}

              {/* Contact Number */}
              {resource.contact_number && (
                <View style={styles.detailRow}>
                  <Feather
                    name="phone"
                    size={15}
                    color="#64748B"
                  />

                  <Text style={styles.detailText}>
                    {resource.contact_number}
                  </Text>
                </View>
              )}

              {/* Action Buttons */}
              <View style={styles.actionRow}>
                {resource.contact_number ? (
                  <TouchableOpacity
                    style={styles.primaryButton}
                    onPress={() =>
                      callResource(resource.contact_number!)
                    }
                  >
                    <Feather
                      name="phone"
                      size={16}
                      color="#FFFFFF"
                    />

                    <Text style={styles.primaryButtonText}>
                      Call Provider
                    </Text>
                  </TouchableOpacity>
                ) : (
                  <View style={styles.noContact}>
                    <Feather
                      name="info"
                      size={15}
                      color="#94A3B8"
                    />

                    <Text style={styles.noContactText}>
                      No contact number available
                    </Text>
                  </View>
                )}
              </View>
            </View>
          ))}

        {/* Information Notice */}
        <View style={styles.notice}>
          <Feather
            name="info"
            size={18}
            color="#92400E"
          />

          <Text style={styles.noticeText}>
            Please contact the listed provider to confirm
            availability before travelling. Resource listings are
            subject to change. For immediate danger, contact local
            emergency services.
          </Text>
        </View>

        <View style={{ height: 110 }} />
      </ScrollView>

      {/* Bottom Navigation */}
      <BottomNavbar activeTab="resources" />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#F8FAFC',
  },

  content: {
    padding: 20,
    paddingBottom: 130,
  },

  header: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 22,
  },

  backButton: {
    width: 42,
    height: 42,
    borderRadius: 13,
    backgroundColor: '#FFFFFF',
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12,
    borderWidth: 1,
    borderColor: '#E2E8F0',
  },

  eyebrow: {
    fontSize: 10,
    fontWeight: '700',
    letterSpacing: 1,
    color: '#64748B',
    marginBottom: 4,
  },

  title: {
    fontSize: 23,
    fontWeight: '800',
    color: '#123B5D',
  },

  locationCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    backgroundColor: '#EAF4FF',
    borderRadius: 14,
    padding: 16,
    borderWidth: 1,
    borderColor: '#D5E8FC',
    marginBottom: 18,
  },

  locationTitle: {
    fontSize: 14,
    fontWeight: '800',
    color: '#123B5D',
  },

  locationText: {
    fontSize: 12,
    color: '#475569',
    marginTop: 5,
    lineHeight: 18,
  },

  searchBox: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#CBD5E1',
    borderRadius: 13,
    paddingHorizontal: 13,
    minHeight: 49,
  },

  searchInput: {
    flex: 1,
    color: '#0F172A',
    fontSize: 14,
    paddingVertical: 10,
  },

  resultCount: {
    fontSize: 12,
    color: '#64748B',
    marginTop: 16,
    marginBottom: 12,
  },

  stateContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    padding: 25,
    backgroundColor: '#FFFFFF',
    borderRadius: 16,
    marginVertical: 10,
  },

  stateText: {
    fontSize: 13,
    color: '#64748B',
    lineHeight: 20,
    textAlign: 'center',
    marginTop: 9,
  },

  emptyTitle: {
    fontSize: 16,
    fontWeight: '800',
    color: '#123B5D',
    marginTop: 12,
    textAlign: 'center',
  },

  resourceCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 17,
    padding: 16,
    marginBottom: 13,
    borderWidth: 1,
    borderColor: '#E2E8F0',
  },

  resourceHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 11,
  },

  resourceIcon: {
    width: 45,
    height: 45,
    borderRadius: 13,
    backgroundColor: '#DBEAFE',
    alignItems: 'center',
    justifyContent: 'center',
  },

  resourceHeading: {
    flex: 1,
  },

  resourceTitle: {
    fontSize: 15,
    fontWeight: '800',
    color: '#1E293B',
  },

  category: {
    fontSize: 12,
    color: '#2563EB',
    marginTop: 4,
    fontWeight: '600',
  },

  description: {
    fontSize: 13,
    lineHeight: 20,
    color: '#475569',
    marginTop: 13,
  },

  detailRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 8,
    marginTop: 11,
  },

  detailText: {
    flex: 1,
    color: '#64748B',
    fontSize: 12,
    lineHeight: 18,
  },

  actionRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    marginTop: 15,
  },

  primaryButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 7,
    backgroundColor: '#2563EB',
    borderRadius: 10,
    paddingHorizontal: 16,
    paddingVertical: 11,
    marginTop: 12,
  },

  primaryButtonText: {
    color: '#FFFFFF',
    fontWeight: '700',
    fontSize: 12,
  },

  noContact: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 7,
    paddingVertical: 10,
  },

  noContactText: {
    fontSize: 12,
    color: '#94A3B8',
  },

  notice: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 9,
    backgroundColor: '#FFFBEB',
    borderRadius: 12,
    padding: 13,
    marginTop: 8,
    borderWidth: 1,
    borderColor: '#FDE68A',
  },

  noticeText: {
    flex: 1,
    color: '#92400E',
    fontSize: 12,
    lineHeight: 18,
  },
});
