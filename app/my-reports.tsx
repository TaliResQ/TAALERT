import { useCallback, useEffect, useMemo, useState } from 'react';

import {
    ActivityIndicator,

    FlatList,

    Modal,

    Pressable,

    RefreshControl,

    SafeAreaView,

    ScrollView,

    StatusBar,

    StyleSheet,

    Text,

    TextInput,

    View,
} from 'react-native';

import { Ionicons } from '@expo/vector-icons';

import { useFocusEffect } from 'expo-router';



// Change this import if your Supabase client is stored elsewhere.

import { supabase } from '@/src/services/supabase';



type IncidentStatus =

  | 'Submitted'

  | 'Under Review'

  | 'Verified'

  | 'Response in Progress'

  | 'Resolved'

  | 'Needs More Information'

  | 'Rejected';



type Incident = {

  id: string | number;

  report_id: string | null;

  created_at: string;

  updated_at: string | null;

  category: string | null;

  location: string | null;

  description: string | null;

  photo_url: string | null;

  user_id: string;

  status: string | null;

  resident_status: IncidentStatus | null;

  latest_update: string | null;

  resolved_at: string | null;

};



type HistoryItem = {

  id: number;

  incident_id: string;

  status: string;

  message: string | null;

  created_at: string;

};



const FILTERS = [

  'All',

  'Submitted',

  'Under Review',

  'Verified',

  'Response in Progress',

  'Resolved',

] as const;



const STATUS_STYLES: Record<

  string,

  { backgroundColor: string; color: string; icon: keyof typeof Ionicons.glyphMap }

> = {

  Submitted: {

    backgroundColor: '#E8EEF9',

    color: '#315DA8',

    icon: 'paper-plane-outline',

  },

  'Under Review': {

    backgroundColor: '#FFF1D6',

    color: '#96600A',

    icon: 'time-outline',

  },

  Verified: {

    backgroundColor: '#DDF5EB',

    color: '#137653',

    icon: 'checkmark-circle-outline',

  },

  'Response in Progress': {

    backgroundColor: '#E5E4FF',

    color: '#5145A6',

    icon: 'people-outline',

  },

  Resolved: {

    backgroundColor: '#DDF5E3',

    color: '#26733D',

    icon: 'checkmark-done-circle-outline',

  },

  'Needs More Information': {

    backgroundColor: '#FFF1D6',

    color: '#96600A',

    icon: 'help-circle-outline',

  },

  Rejected: {

    backgroundColor: '#FCE7E7',

    color: '#B42318',

    icon: 'close-circle-outline',

  },

};



function getResidentStatus(incident: Incident): string {

  // resolved_at is retained as a fallback for older resolved incidents.

  if (incident.resolved_at) return 'Resolved';

  return incident.resident_status || 'Submitted';

}



function formatDate(value?: string | null): string {

  if (!value) return 'Date unavailable';



  const date = new Date(value);

  if (Number.isNaN(date.getTime())) return 'Date unavailable';



  return date.toLocaleString([], {

    month: 'short',

    day: 'numeric',

    year: 'numeric',

    hour: 'numeric',

    minute: '2-digit',

  });

}



function StatusBadge({ status }: { status: string }) {

  const style = STATUS_STYLES[status] || STATUS_STYLES.Submitted;



  return (

    <View style={[styles.badge, { backgroundColor: style.backgroundColor }]}>

      <Ionicons name={style.icon} size={15} color={style.color} />

      <Text style={[styles.badgeText, { color: style.color }]}>{status}</Text>

    </View>

  );

}



export default function MyReportsScreen() {

  const [incidents, setIncidents] = useState<Incident[]>([]);

  const [userId, setUserId] = useState<string | null>(null);

  const [loading, setLoading] = useState(true);

  const [refreshing, setRefreshing] = useState(false);

  const [error, setError] = useState('');

  const [search, setSearch] = useState('');

  const [filter, setFilter] = useState<string>('All');



  const [selected, setSelected] = useState<Incident | null>(null);

  const [history, setHistory] = useState<HistoryItem[]>([]);

  const [historyLoading, setHistoryLoading] = useState(false);

  const [historyError, setHistoryError] = useState('');



  const loadReports = useCallback(async (showLoader = true) => {

    if (showLoader) setLoading(true);

    setError('');



    try {

      const { data: authData, error: authError } =

        await supabase.auth.getUser();



      if (authError) throw authError;



      const user = authData.user;



      if (!user) {

        setUserId(null);

        setIncidents([]);

        setError('Please sign in to view your reports.');

        return;

      }



      setUserId(user.id);



      const { data, error: queryError } = await supabase

        .from('incidents')

        .select(

          'id, report_id, created_at, updated_at, category, location, description, photo_url, user_id, status, resident_status, latest_update, resolved_at'

        )

        .eq('user_id', user.id)

        .order('created_at', { ascending: false });



      if (queryError) throw queryError;



      setIncidents((data || []) as Incident[]);

    } catch (err) {

      console.error('Failed to load incident reports:', err);

      setError('Unable to load your reports. Please try again.');

    } finally {

      setLoading(false);

      setRefreshing(false);

    }

  }, []);



  // Refresh when the resident returns to this tab.

  useFocusEffect(

    useCallback(() => {

      void loadReports();

    }, [loadReports])

  );



  // Load status history when a report is opened.

  useEffect(() => {

    let active = true;



    async function loadHistory() {

      if (!selected || !userId) {

        setHistory([]);

        setHistoryError('');

        return;

      }



      setHistoryLoading(true);

      setHistoryError('');



      try {

        // Fetch the history only for the selected incident.

        const { data, error: queryError } = await supabase

          .from('incident_status_history')

          .select('id, incident_id, status, message, created_at')

          .eq('incident_id', String(selected.id))

          .order('created_at', { ascending: true });



        if (queryError) throw queryError;

        if (active) setHistory((data || []) as HistoryItem[]);

      } catch (err) {

        console.error('Failed to load status history:', err);

        if (active) {

          setHistoryError('Status history is temporarily unavailable.');

        }

      } finally {

        if (active) setHistoryLoading(false);

      }

    }



    void loadHistory();



    return () => {

      active = false;

    };

  }, [selected, userId]);



  const visibleReports = useMemo(() => {

    const term = search.trim().toLowerCase();



    return incidents.filter((incident) => {

      const status = getResidentStatus(incident);



      const matchesFilter = filter === 'All' || status === filter;

      const matchesSearch =

        !term ||

        [incident.category, incident.location, incident.description, incident.report_id]

          .some((value) => value?.toLowerCase().includes(term));



      return matchesFilter && matchesSearch;

    });

  }, [incidents, search, filter]);



  const counts = useMemo(() => {

    return {

      total: incidents.length,

      active: incidents.filter(

        (item) => getResidentStatus(item) !== 'Resolved'

      ).length,

      resolved: incidents.filter(

        (item) => getResidentStatus(item) === 'Resolved'

      ).length,

    };

  }, [incidents]);



  function openReport(incident: Incident) {

    setHistory([]);

    setHistoryError('');

    setSelected(incident);

  }



  function renderReport({ item }: { item: Incident }) {

    const status = getResidentStatus(item);



    return (

      <Pressable

        style={({ pressed }) => [

          styles.reportCard,

          pressed && styles.pressed,

        ]}

        onPress={() => openReport(item)}

        accessibilityRole="button"

        accessibilityLabel={`View ${item.category || 'incident'} report, status ${status}`}

      >

        <View style={styles.cardTop}>

          <View style={styles.categoryIcon}>

            <Ionicons

              name="alert-circle-outline"

              size={23}

              color="#145DA0"

            />

          </View>



          <View style={styles.cardHeading}>

            <Text style={styles.category} numberOfLines={1}>

              {item.category || 'Incident Report'}

            </Text>

            <Text style={styles.reportNumber} numberOfLines={1}>

              Report ID: {item.report_id || String(item.id)}

            </Text>

          </View>



          <Ionicons name="chevron-forward" size={20} color="#98A2B3" />

        </View>



        <View style={styles.locationRow}>

          <Ionicons name="location-outline" size={16} color="#667085" />

          <Text style={styles.location} numberOfLines={2}>

            {item.location || 'Location not provided'}

          </Text>

        </View>



        <StatusBadge status={status} />



        <View style={styles.updateBox}>

          <Text style={styles.updateLabel}>LATEST UPDATE</Text>

          <Text style={styles.updateText} numberOfLines={3}>

            {item.latest_update ||

              'Your report has been submitted and is awaiting review.'}

          </Text>

          <Text style={styles.updatedAt}>

            Updated: {formatDate(item.updated_at || item.created_at)}

          </Text>

        </View>



        <View style={styles.cardFooter}>

          <Ionicons name="calendar-outline" size={15} color="#667085" />

          <Text style={styles.dateText}>

            Submitted {formatDate(item.created_at)}

          </Text>

          <Text style={styles.detailsLink}>View details</Text>

        </View>

      </Pressable>

    );

  }



  return (

    <SafeAreaView style={styles.safeArea}>

      <StatusBar barStyle="dark-content" backgroundColor="#F5F8FC" />



      <View style={styles.header}>

        <View>

          <Text style={styles.eyebrow}>TALIRESQ</Text>

          <Text style={styles.title}>My Reports</Text>

          <Text style={styles.subtitle}>

            Track your submitted incidents and updates.

          </Text>

        </View>

        <View style={styles.headerIcon}>

          <Ionicons name="document-text-outline" size={26} color="#145DA0" />

        </View>

      </View>



      <View style={styles.summaryRow}>

        <View style={styles.summaryCard}>

          <Text style={styles.summaryNumber}>{counts.total}</Text>

          <Text style={styles.summaryLabel}>Total reports</Text>

        </View>

        <View style={styles.summaryCard}>

          <Text style={styles.summaryNumber}>{counts.active}</Text>

          <Text style={styles.summaryLabel}>Active</Text>

        </View>

        <View style={styles.summaryCard}>

          <Text style={styles.summaryNumber}>{counts.resolved}</Text>

          <Text style={styles.summaryLabel}>Resolved</Text>

        </View>

      </View>



      <View style={styles.searchBox}>

        <Ionicons name="search-outline" size={20} color="#667085" />

        <TextInput

          value={search}

          onChangeText={setSearch}

          placeholder="Search by location or category"

          placeholderTextColor="#98A2B3"

          style={styles.searchInput}

          returnKeyType="search"

          accessibilityLabel="Search reports"

        />

        {search.length > 0 && (

          <Pressable onPress={() => setSearch('')} hitSlop={8}>

            <Ionicons name="close-circle" size={20} color="#98A2B3" />

          </Pressable>

        )}

      </View>



      <ScrollView

        horizontal

        showsHorizontalScrollIndicator={false}

        style={styles.filterScroll}

        contentContainerStyle={styles.filterContent}

      >

        {FILTERS.map((item) => {

          const active = filter === item;



          return (

            <Pressable

              key={item}

              onPress={() => setFilter(item)}

              style={[styles.filterChip, active && styles.filterChipActive]}

            >

              <Text

                style={[

                  styles.filterText,

                  active && styles.filterTextActive,

                ]}

              >

                {item}

              </Text>

            </Pressable>

          );

        })}

      </ScrollView>



      {loading ? (

        <View style={styles.centerState}>

          <ActivityIndicator size="large" color="#145DA0" />

          <Text style={styles.stateText}>Loading your reports...</Text>

        </View>

      ) : error ? (

        <View style={styles.centerState}>

          <Ionicons

            name="cloud-offline-outline"

            size={44}

            color="#98A2B3"

          />

          <Text style={styles.stateTitle}>Could not load reports</Text>

          <Text style={styles.stateText}>{error}</Text>

          <Pressable

            style={styles.retryButton}

            onPress={() => void loadReports()}

          >

            <Text style={styles.retryText}>Try again</Text>

          </Pressable>

        </View>

      ) : (

        <FlatList

          data={visibleReports}

          keyExtractor={(item) => String(item.id)}

          renderItem={renderReport}

          contentContainerStyle={styles.listContent}

          showsVerticalScrollIndicator={false}

          refreshControl={

            <RefreshControl

              refreshing={refreshing}

              onRefresh={() => {

                setRefreshing(true);

                void loadReports(false);

              }}

              tintColor="#145DA0"

            />

          }

          ListEmptyComponent={

            <View style={styles.centerState}>

              <View style={styles.emptyIcon}>

                <Ionicons

                  name="document-text-outline"

                  size={38}

                  color="#145DA0"

                />

              </View>

              <Text style={styles.stateTitle}>

                {search || filter !== 'All'

                  ? 'No matching reports'

                  : 'No reports yet'}

              </Text>

              <Text style={styles.stateText}>

                {search || filter !== 'All'

                  ? 'Try another search or status filter.'

                  : 'Reports you submit will appear here so you can track their progress.'}

              </Text>

              {(search || filter !== 'All') && (

                <Pressable

                  onPress={() => {

                    setSearch('');

                    setFilter('All');

                  }}

                >

                  <Text style={styles.clearFilters}>Clear filters</Text>

                </Pressable>

              )}

            </View>

          }

        />

      )}



      <Modal

        visible={selected !== null}

        animationType="slide"

        presentationStyle="pageSheet"

        onRequestClose={() => setSelected(null)}

      >

        {selected && (

          <SafeAreaView style={styles.modalSafe}>

            <View style={styles.modalHeader}>

              <Pressable

                onPress={() => setSelected(null)}

                style={styles.backButton}

                accessibilityRole="button"

                accessibilityLabel="Close report details"

              >

                <Ionicons name="arrow-back" size={23} color="#101828" />

              </Pressable>

              <Text style={styles.modalTitle}>Report Details</Text>

              <View style={{ width: 42 }} />

            </View>



            <ScrollView

              contentContainerStyle={styles.detailContent}

              showsVerticalScrollIndicator={false}

            >

              <Text style={styles.detailEyebrow}>

                REPORT ID: {selected.report_id || String(selected.id)}

              </Text>

              <Text style={styles.detailCategory}>

                {selected.category || 'Incident Report'}

              </Text>



              <StatusBadge status={getResidentStatus(selected)} />



              <View style={styles.detailPanel}>

                <Text style={styles.sectionTitle}>Incident information</Text>

                <DetailRow

                  icon="location-outline"

                  label="Location"

                  value={selected.location || 'Not provided'}

                />

                <DetailRow

                  icon="calendar-outline"

                  label="Date submitted"

                  value={formatDate(selected.created_at)}

                />

                <DetailRow

                  icon="time-outline"

                  label="Last updated"

                  value={formatDate(selected.updated_at || selected.created_at)}

                />

                <View style={styles.descriptionBlock}>

                  <Text style={styles.fieldLabel}>DESCRIPTION</Text>

                  <Text style={styles.description}>

                    {selected.description || 'No description provided.'}

                  </Text>

                </View>

              </View>



              <View style={styles.latestPanel}>

                <Text style={styles.sectionTitle}>Latest update</Text>

                <Text style={styles.latestMessage}>

                  {selected.latest_update ||

                    'Your report has been submitted and is awaiting review.'}

                </Text>

                <Text style={styles.updatedAt}>

                  {formatDate(selected.updated_at || selected.created_at)}

                </Text>

              </View>



              <View style={styles.timelinePanel}>

                <Text style={styles.sectionTitle}>Status history</Text>

                <Text style={styles.timelineSubtitle}>

                  Follow the progress of your report.

                </Text>



                {historyLoading ? (

                  <ActivityIndicator

                    style={{ marginVertical: 24 }}

                    color="#145DA0"

                  />

                ) : historyError ? (

                  <Text style={styles.stateText}>{historyError}</Text>

                ) : (

                  <>

                    {history.map((entry, index) => {

                      const statusStyle =

                        STATUS_STYLES[entry.status] ||

                        STATUS_STYLES.Submitted;

                      const last = index === history.length - 1;



                      return (

                        <View key={entry.id} style={styles.timelineItem}>

                          <View style={styles.timelineRail}>

                            <View

                              style={[

                                styles.timelineDot,

                                { backgroundColor: statusStyle.color },

                              ]}

                            />

                            {!last && <View style={styles.timelineLine} />}

                          </View>

                          <View style={styles.timelineEntry}>

                            <StatusBadge status={entry.status} />

                            <Text style={styles.timelineMessage}>

                              {entry.message || `Status changed to ${entry.status}.`}

                            </Text>

                            <Text style={styles.timelineDate}>

                              {formatDate(entry.created_at)}

                            </Text>

                          </View>

                        </View>

                      );

                    })}



                    {history.length === 0 && (

                      <View style={styles.historyEmpty}>

                        <Ionicons

                          name="time-outline"

                          size={24}

                          color="#98A2B3"

                        />

                        <Text style={styles.stateText}>

                          No status history is available yet.

                        </Text>

                        <Text style={styles.historyHint}>

                          New administrator updates will appear here when they

                          are recorded in the history table.

                        </Text>

                      </View>

                    )}

                  </>

                )}

              </View>

            </ScrollView>

          </SafeAreaView>

        )}

      </Modal>

    </SafeAreaView>

  );

}



function DetailRow({

  icon,

  label,

  value,

}: {

  icon: keyof typeof Ionicons.glyphMap;

  label: string;

  value: string;

}) {

  return (

    <View style={styles.detailRow}>

      <Ionicons name={icon} size={19} color="#667085" />

      <View style={styles.detailRowText}>

        <Text style={styles.fieldLabel}>{label}</Text>

        <Text style={styles.detailValue}>{value}</Text>

      </View>

    </View>

  );

}



const styles = StyleSheet.create({

  safeArea: {

    flex: 1,

    backgroundColor: '#F5F8FC',

  },

  header: {

    paddingHorizontal: 20,

    paddingTop: 16,

    paddingBottom: 18,

    flexDirection: 'row',

    justifyContent: 'space-between',

    alignItems: 'center',

  },

  eyebrow: {

    color: '#145DA0',

    fontSize: 11,

    fontWeight: '800',

    letterSpacing: 2,

    marginBottom: 5,

  },

  title: {

    fontSize: 28,

    fontWeight: '800',

    color: '#101828',

  },

  subtitle: {

    fontSize: 13,

    color: '#667085',

    marginTop: 5,

  },

  headerIcon: {

    width: 52,

    height: 52,

    borderRadius: 17,

    backgroundColor: '#E5EFFB',

    alignItems: 'center',

    justifyContent: 'center',

  },

  summaryRow: {

    flexDirection: 'row',

    paddingHorizontal: 16,

    gap: 10,

    marginBottom: 16,

  },

  summaryCard: {

    flex: 1,

    padding: 13,

    backgroundColor: '#FFFFFF',

    borderRadius: 15,

    borderWidth: 1,

    borderColor: '#E7ECF3',

  },

  summaryNumber: {

    fontSize: 23,

    fontWeight: '800',

    color: '#145DA0',

  },

  summaryLabel: {

    fontSize: 11,

    color: '#667085',

    marginTop: 4,

  },

  searchBox: {

    marginHorizontal: 16,

    marginBottom: 12,

    paddingHorizontal: 13,

    height: 48,

    borderRadius: 13,

    borderWidth: 1,

    borderColor: '#E2E8F0',

    backgroundColor: '#FFFFFF',

    flexDirection: 'row',

    alignItems: 'center',

    gap: 9,

  },

  searchInput: {

    flex: 1,

    fontSize: 14,

    color: '#101828',

    paddingVertical: 0,

  },

  filterScroll: {

    flexGrow: 0,

    marginBottom: 8,

  },

  filterContent: {

    paddingHorizontal: 16,

    paddingBottom: 8,

    gap: 8,

  },

  filterChip: {

    borderRadius: 20,

    borderWidth: 1,

    borderColor: '#DCE3EC',

    backgroundColor: '#FFFFFF',

    paddingHorizontal: 14,

    paddingVertical: 9,

  },

  filterChipActive: {

    backgroundColor: '#145DA0',

    borderColor: '#145DA0',

  },

  filterText: {

    color: '#475467',

    fontSize: 12,

    fontWeight: '600',

  },

  filterTextActive: {

    color: '#FFFFFF',

  },

  listContent: {

    paddingHorizontal: 16,

    paddingTop: 4,

    paddingBottom: 32,

    flexGrow: 1,

  },

  reportCard: {

    backgroundColor: '#FFFFFF',

    borderRadius: 18,

    padding: 16,

    marginBottom: 13,

    borderWidth: 1,

    borderColor: '#E6EBF2',

  },

  pressed: {

    opacity: 0.78,

  },

  cardTop: {

    flexDirection: 'row',

    alignItems: 'center',

    gap: 11,

  },

  categoryIcon: {

    height: 43,

    width: 43,

    borderRadius: 13,

    backgroundColor: '#EAF2FC',

    alignItems: 'center',

    justifyContent: 'center',

  },

  cardHeading: {

    flex: 1,

  },

  category: {

    fontSize: 16,

    fontWeight: '700',

    color: '#101828',

  },

  reportNumber: {

    fontSize: 11,

    color: '#667085',

    marginTop: 4,

  },

  locationRow: {

    flexDirection: 'row',

    alignItems: 'flex-start',

    gap: 6,

    marginTop: 14,

    marginBottom: 12,

  },

  location: {

    flex: 1,

    fontSize: 13,

    color: '#475467',

    lineHeight: 19,

  },

  badge: {

    flexDirection: 'row',

    alignItems: 'center',

    alignSelf: 'flex-start',

    gap: 5,

    borderRadius: 8,

    paddingHorizontal: 9,

    paddingVertical: 6,

  },

  badgeText: {

    fontSize: 11,

    fontWeight: '700',

  },

  updateBox: {

    backgroundColor: '#F7F9FC',

    borderRadius: 12,

    padding: 12,

    marginTop: 13,

  },

  updateLabel: {

    fontSize: 10,

    letterSpacing: 1,

    color: '#667085',

    fontWeight: '800',

    marginBottom: 7,

  },

  updateText: {

    color: '#344054',

    fontSize: 13,

    lineHeight: 19,

  },

  updatedAt: {

    color: '#667085',

    fontSize: 11,

    marginTop: 8,

  },

  cardFooter: {

    flexDirection: 'row',

    alignItems: 'center',

    gap: 6,

    marginTop: 14,

  },

  dateText: {

    flex: 1,

    fontSize: 11,

    color: '#667085',

  },

  detailsLink: {

    color: '#145DA0',

    fontSize: 12,

    fontWeight: '700',

  },

  centerState: {

    flexGrow: 1,

    alignItems: 'center',

    justifyContent: 'center',

    paddingHorizontal: 30,

    paddingVertical: 35,

  },

  stateTitle: {

    fontSize: 17,

    fontWeight: '700',

    color: '#101828',

    textAlign: 'center',

    marginTop: 12,

  },

  stateText: {

    fontSize: 13,

    lineHeight: 20,

    color: '#667085',

    textAlign: 'center',

    marginTop: 8,

  },

  retryButton: {

    backgroundColor: '#145DA0',

    borderRadius: 10,

    paddingHorizontal: 20,

    paddingVertical: 11,

    marginTop: 16,

  },

  retryText: {

    color: '#FFFFFF',

    fontSize: 13,

    fontWeight: '700',

  },

  emptyIcon: {

    width: 72,

    height: 72,

    borderRadius: 24,

    alignItems: 'center',

    justifyContent: 'center',

    backgroundColor: '#E5EFFB',

  },

  clearFilters: {

    marginTop: 15,

    color: '#145DA0',

    fontSize: 13,

    fontWeight: '700',

  },

  modalSafe: {

    flex: 1,

    backgroundColor: '#F5F8FC',

  },

  modalHeader: {

    flexDirection: 'row',

    alignItems: 'center',

    justifyContent: 'space-between',

    paddingHorizontal: 16,

    paddingVertical: 12,

    backgroundColor: '#FFFFFF',

    borderBottomWidth: 1,

    borderBottomColor: '#E6EBF2',

  },

  backButton: {

    width: 42,

    height: 42,

    borderRadius: 13,

    alignItems: 'center',

    justifyContent: 'center',

    backgroundColor: '#F2F4F7',

  },

  modalTitle: {

    fontSize: 17,

    fontWeight: '700',

    color: '#101828',

  },

  detailContent: {

    padding: 18,

    paddingBottom: 36,

  },

  detailEyebrow: {

    color: '#667085',

    fontSize: 10,

    fontWeight: '800',

    letterSpacing: 1,

  },

  detailCategory: {

    color: '#101828',

    fontSize: 25,

    fontWeight: '800',

    marginTop: 7,

    marginBottom: 13,

  },

  detailPanel: {

    backgroundColor: '#FFFFFF',

    padding: 16,

    borderRadius: 16,

    borderWidth: 1,

    borderColor: '#E6EBF2',

    marginTop: 18,

  },

  sectionTitle: {

    fontSize: 16,

    fontWeight: '700',

    color: '#101828',

    marginBottom: 15,

  },

  detailRow: {

    flexDirection: 'row',

    gap: 12,

    alignItems: 'flex-start',

    marginBottom: 16,

  },

  detailRowText: {

    flex: 1,

  },

  fieldLabel: {

    color: '#667085',

    fontSize: 10,

    fontWeight: '700',

    textTransform: 'uppercase',

    letterSpacing: 0.6,

    marginBottom: 4,

  },

  detailValue: {

    color: '#344054',

    fontSize: 13,

    lineHeight: 19,

  },

  descriptionBlock: {

    borderTopWidth: 1,

    borderTopColor: '#EAECF0',

    paddingTop: 14,

  },

  description: {

    color: '#344054',

    fontSize: 13,

    lineHeight: 21,

  },

  latestPanel: {

    backgroundColor: '#EAF2FC',

    borderRadius: 16,

    padding: 16,

    marginTop: 14,

  },

  latestMessage: {

    color: '#344054',

    fontSize: 14,

    lineHeight: 22,

  },

  timelinePanel: {

    backgroundColor: '#FFFFFF',

    borderRadius: 16,

    padding: 16,

    marginTop: 14,

    borderWidth: 1,

    borderColor: '#E6EBF2',

  },

  timelineSubtitle: {

    color: '#667085',

    fontSize: 12,

    marginTop: -8,

    marginBottom: 20,

  },

  timelineItem: {

    flexDirection: 'row',

    minHeight: 85,

  },

  timelineRail: {

    width: 25,

    alignItems: 'center',

    marginRight: 10,

  },

  timelineDot: {

    width: 11,

    height: 11,

    borderRadius: 6,

    marginTop: 5,

  },

  timelineLine: {

    width: 2,

    flex: 1,

    minHeight: 55,

    backgroundColor: '#E4E7EC',

    marginTop: 5,

  },

  timelineEntry: {

    flex: 1,

    paddingBottom: 22,

    alignItems: 'flex-start',

  },

  timelineMessage: {

    fontSize: 13,

    lineHeight: 20,

    color: '#344054',

    marginTop: 9,

  },

  timelineDate: {

    fontSize: 11,

    color: '#667085',

    marginTop: 7,

  },

  historyEmpty: {

    alignItems: 'center',

    paddingVertical: 14,

  },

  historyHint: {

    color: '#667085',

    fontSize: 12,

    textAlign: 'center',

    lineHeight: 18,

    marginTop: 6,

  },

});
