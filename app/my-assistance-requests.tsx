
import { supabase } from '@/src/services/supabase';
import { Feather } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import {
    ActivityIndicator,
    RefreshControl,
    SafeAreaView,
    ScrollView,
    StyleSheet,
    Text,
    TouchableOpacity,
    View,
} from 'react-native';
import BottomNavbar from '../components/BottomNavbar';

type AssistanceRequest = {
  id: string;
  request_category: string;
  barangay: string | null;
  address: string | null;
  description: string | null;
  status: string;
  admin_notes: string | null;
  created_at: string;
  updated_at: string | null;
};

const statusLabels: Record<string, string> = {
  pending: 'Pending',
  under_review: 'Under Review',
  verified: 'Verified',
  assistance_coordinated: 'Assistance Coordinated',
  completed: 'Completed',
  rejected: 'Rejected',
  needs_more_information: 'Needs More Information',
};

const statusColors: Record<
  string,
  { backgroundColor: string; color: string }
> = {
  pending: { backgroundColor: '#FEF3C7', color: '#92400E' },
  under_review: { backgroundColor: '#DBEAFE', color: '#1D4ED8' },
  verified: { backgroundColor: '#D1FAE5', color: '#065F46' },
  assistance_coordinated: {
    backgroundColor: '#EDE9FE',
    color: '#5B21B6',
  },
  completed: { backgroundColor: '#DCFCE7', color: '#166534' },
  rejected: { backgroundColor: '#FEE2E2', color: '#991B1B' },
  needs_more_information: {
    backgroundColor: '#FFEDD5',
    color: '#9A3412',
  },
};

function formatDate(value: string | null) {
  if (!value) return 'Not available';

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) return 'Not available';

  return date.toLocaleString();
}

export default function MyAssistanceRequestsScreen() {
  const router = useRouter();

  const [requests, setRequests] = useState<AssistanceRequest[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');

  const loadRequests = useCallback(async () => {
    setError('');

    try {
      const {
        data: { user },
        error: userError,
      } = await supabase.auth.getUser();

      if (userError) throw userError;

      if (!user) {
        setRequests([]);
        setError('Please log in to view your assistance requests.');
        return;
      }

      const { data, error: queryError } = await supabase
        .from('assistance_requests')
        .select(
          'id, request_category, barangay, address, description, status, admin_notes, created_at, updated_at'
        )
        .eq('user_id', user.id)
        .order('created_at', { ascending: false });

      if (queryError) throw queryError;

      setRequests((data ?? []) as AssistanceRequest[]);
    } catch (err) {
      console.error('Failed to load assistance requests:', err);
      setError('Unable to load your requests. Please try again.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    loadRequests();
  }, [loadRequests]);

  const onRefresh = () => {
    setRefreshing(true);
    loadRequests();
  };

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView
        contentContainerStyle={styles.content}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={onRefresh}
            tintColor="#1769AA"
          />
        }
      >
        <View style={styles.header}>
          <TouchableOpacity
            style={styles.backButton}
            onPress={() => router.back()}
            accessibilityLabel="Go back"
          >
            <Feather name="arrow-left" size={22} color="#123B5D" />
          </TouchableOpacity>

          <View style={styles.headerText}>
            <Text style={styles.subtitle}>RECOVERY ASSISTANCE</Text>
            <Text style={styles.title}>My Requests</Text>
          </View>
        </View>

        <View style={styles.introCard}>
          <Feather name="clipboard" size={27} color="#1769AA" />
          <Text style={styles.introTitle}>Track your requests</Text>
          <Text style={styles.introDescription}>
            Check the latest status of assistance requests you have submitted.
            Pull down to refresh your updates.
          </Text>
        </View>

        {loading ? (
          <View style={styles.centerState}>
            <ActivityIndicator size="large" color="#1769AA" />
            <Text style={styles.stateText}>Loading your requests...</Text>
          </View>
        ) : error ? (
          <View style={styles.emptyCard}>
            <Feather name="alert-circle" size={34} color="#DC2626" />
            <Text style={styles.emptyTitle}>Unable to load requests</Text>
            <Text style={styles.emptyText}>{error}</Text>

            <TouchableOpacity
              style={styles.primaryButton}
              onPress={() => {
                setLoading(true);
                loadRequests();
              }}
            >
              <Text style={styles.primaryButtonText}>Try Again</Text>
            </TouchableOpacity>
          </View>
        ) : requests.length === 0 ? (
          <View style={styles.emptyCard}>
            <Feather name="clipboard" size={38} color="#94A3B8" />
            <Text style={styles.emptyTitle}>No requests yet</Text>
            <Text style={styles.emptyText}>
              Your submitted recovery assistance requests will appear here.
            </Text>

            <TouchableOpacity
              style={styles.primaryButton}
              onPress={() => router.push('/request-assistance')}
            >
              <Text style={styles.primaryButtonText}>
                Request Assistance
              </Text>
            </TouchableOpacity>
          </View>
        ) : (
          requests.map((request) => {
            const status = request.status?.toLowerCase() ?? 'pending';
            const colors = statusColors[status] ?? {
              backgroundColor: '#E2E8F0',
              color: '#334155',
            };

            return (
              <View key={request.id} style={styles.requestCard}>
                <View style={styles.requestHeader}>
                  <View style={styles.categoryIcon}>
                    <Feather
                      name="clipboard"
                      size={21}
                      color="#1769AA"
                    />
                  </View>

                  <View style={styles.categoryContainer}>
                    <Text style={styles.category}>
                      {request.request_category}
                    </Text>
                    <Text style={styles.date}>
                      Submitted {formatDate(request.created_at)}
                    </Text>
                  </View>
                </View>

                <View
                  style={[
                    styles.statusBadge,
                    { backgroundColor: colors.backgroundColor },
                  ]}
                >
                  <Text style={[styles.statusText, { color: colors.color }]}>
                    {statusLabels[status] ?? status.replace(/_/g, ' ')}
                  </Text>
                </View>

                <View style={styles.detailRow}>
                  <Feather name="map-pin" size={16} color="#64748B" />
                  <Text style={styles.detailText}>
                    {[request.barangay, request.address]
                      .filter(Boolean)
                      .join(', ') || 'No location provided'}
                  </Text>
                </View>

                {!!request.description && (
                  <View style={styles.descriptionBox}>
                    <Text style={styles.detailLabel}>Request details</Text>
                    <Text style={styles.detailText}>
                      {request.description}
                    </Text>
                  </View>
                )}

                {!!request.admin_notes?.trim() && (
                  <View style={styles.notesBox}>
                    <Text style={styles.notesTitle}>
                      <Feather name="message-circle" size={15} /> Admin Update
                    </Text>
                    <Text style={styles.notesText}>
                      {request.admin_notes}
                    </Text>
                  </View>
                )}

                {!!request.updated_at && (
                  <Text style={styles.updatedText}>
                    Last updated: {formatDate(request.updated_at)}
                  </Text>
                )}
              </View>
            );
          })
        )}

        <View style={{ height: 20 }} />
      </ScrollView>

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
    paddingHorizontal: 20,
    paddingTop: 12,
    paddingBottom: 24,
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
  headerText: {
    flex: 1,
  },
  subtitle: {
    color: '#64748B',
    fontSize: 10,
    fontWeight: '700',
    letterSpacing: 1,
    marginBottom: 4,
  },
  title: {
    color: '#123B5D',
    fontSize: 24,
    fontWeight: '800',
  },
  introCard: {
    backgroundColor: '#EAF4FF',
    borderColor: '#D5E8FC',
    borderWidth: 1,
    borderRadius: 18,
    padding: 18,
    marginBottom: 22,
  },
  introTitle: {
    color: '#123B5D',
    fontSize: 19,
    fontWeight: '800',
    marginTop: 12,
    marginBottom: 6,
  },
  introDescription: {
    color: '#475569',
    fontSize: 14,
    lineHeight: 21,
  },
  centerState: {
    alignItems: 'center',
    padding: 35,
  },
  stateText: {
    color: '#64748B',
    marginTop: 12,
  },
  emptyCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 17,
    padding: 25,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#E2E8F0',
  },
  emptyTitle: {
    fontSize: 17,
    fontWeight: '800',
    color: '#123B5D',
    marginTop: 14,
    marginBottom: 7,
  },
  emptyText: {
    textAlign: 'center',
    color: '#64748B',
    lineHeight: 21,
  },
  primaryButton: {
    marginTop: 18,
    backgroundColor: '#1769AA',
    borderRadius: 10,
    paddingVertical: 12,
    paddingHorizontal: 18,
  },
  primaryButtonText: {
    color: '#FFFFFF',
    fontWeight: '700',
  },
  requestCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 17,
    padding: 16,
    marginBottom: 15,
    borderWidth: 1,
    borderColor: '#E2E8F0',
  },
  requestHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 14,
  },
  categoryIcon: {
    width: 43,
    height: 43,
    borderRadius: 12,
    backgroundColor: '#DBEAFE',
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12,
  },
  categoryContainer: {
    flex: 1,
  },
  category: {
    fontSize: 16,
    fontWeight: '800',
    color: '#123B5D',
  },
  date: {
    fontSize: 11,
    color: '#64748B',
    marginTop: 4,
  },
  statusBadge: {
    alignSelf: 'flex-start',
    paddingVertical: 7,
    paddingHorizontal: 11,
    borderRadius: 20,
    marginBottom: 13,
  },
  statusText: {
    fontSize: 12,
    fontWeight: '800',
  },
  detailRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 8,
    marginBottom: 10,
  },
  detailText: {
    flex: 1,
    color: '#475569',
    fontSize: 13,
    lineHeight: 20,
  },
  descriptionBox: {
    paddingTop: 10,
    borderTopWidth: 1,
    borderTopColor: '#E2E8F0',
  },
  detailLabel: {
    fontSize: 12,
    fontWeight: '800',
    color: '#123B5D',
    marginBottom: 5,
  },
  notesBox: {
    backgroundColor: '#F0F9FF',
    borderRadius: 10,
    padding: 12,
    marginTop: 12,
    borderLeftWidth: 3,
    borderLeftColor: '#1769AA',
  },
  notesTitle: {
    color: '#123B5D',
    fontWeight: '800',
    fontSize: 13,
    marginBottom: 7,
  },
  notesText: {
    color: '#334155',
    fontSize: 13,
    lineHeight: 20,
  },
  updatedText: {
    fontSize: 11,
    color: '#64748B',
    marginTop: 13,
  },
});
