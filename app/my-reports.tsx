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

import { supabase } from '@/src/services/supabase';
import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect } from 'expo-router';

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
  priority_level: string | null;
  is_escalated: boolean | null;
  escalation_reason: string | null;
  escalated_at: string | null;
};

type HistoryItem = {
  id: number;
  incident_id: string;
  status: string;
  message: string | null;
  created_at: string;
};

type EscalationHistoryItem = {
  id: number;
  incident_id: string;
  priority_level: string;
  escalation_reason: string;
  created_at: string;
};

const FILTERS = [
  'All',
  'Submitted',
  'Under Review',
  'Verified',
  'Response in Progress',
  'Needs More Information',
  'Resolved',
  'Rejected',
] as const;

const STATUS_STYLES: Record<
  string,
  {
    backgroundColor: string;
    color: string;
    icon: keyof typeof Ionicons.glyphMap;
  }
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

const INCIDENT_STEPS = [
  {
    key: 'Submitted',
    title: 'Report Submitted',
    description: 'Your incident report has been received.',
  },
  {
    key: 'Under Review',
    title: 'Under Review',
    description: 'The response team is reviewing the details.',
  },
  {
    key: 'Verified',
    title: 'Report Verified',
    description: 'The incident details have been verified.',
  },
  {
    key: 'Response in Progress',
    title: 'Response in Progress',
    description: 'The appropriate team is working on the response.',
  },
  {
    key: 'Resolved',
    title: 'Resolved',
    description: 'The incident has been marked as resolved.',
  },
] as const;

function getResidentStatus(incident: Incident): string {
  if (incident.resolved_at) return 'Resolved';
  return incident.resident_status || 'Submitted';
}

function formatDate(value?: string | null): string {
  if (!value) return 'Date unavailable';

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return 'Date unavailable';
  }

  return date.toLocaleString([], {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
}

function getPriorityStyle(priority?: string | null) {
  switch ((priority || 'Medium').trim().toLowerCase()) {
    case 'low':
      return {
        backgroundColor: '#E8F5E9',
        color: '#2E7D32',
        icon: 'flag-outline' as const,
      };

    case 'high':
      return {
        backgroundColor: '#FFF0E5',
        color: '#C2410C',
        icon: 'alert-circle-outline' as const,
      };

    case 'critical':
      return {
        backgroundColor: '#FCE7E7',
        color: '#B42318',
        icon: 'warning-outline' as const,
      };

    default:
      return {
        backgroundColor: '#FFF4D6',
        color: '#946200',
        icon: 'flag-outline' as const,
      };
  }
}

function StatusBadge({ status }: { status: string }) {
  const style = STATUS_STYLES[status] || STATUS_STYLES.Submitted;

  return (
    <View
      style={[
        styles.badge,
        { backgroundColor: style.backgroundColor },
      ]}
    >
      <Ionicons name={style.icon} size={15} color={style.color} />
      <Text style={[styles.badgeText, { color: style.color }]}>
        {status}
      </Text>
    </View>
  );
}

function PriorityBadge({
  priority,
}: {
  priority?: string | null;
}) {
  const style = getPriorityStyle(priority);

  return (
    <View
      style={[
        styles.priorityBadge,
        { backgroundColor: style.backgroundColor },
      ]}
    >
      <Ionicons name={style.icon} size={14} color={style.color} />
      <Text
        style={[
          styles.priorityBadgeText,
          { color: style.color },
        ]}
      >
        {priority || 'Medium'} Priority
      </Text>
    </View>
  );
}

function EscalationNotice({
  reason,
  escalatedAt,
}: {
  reason?: string | null;
  escalatedAt?: string | null;
}) {
  return (
    <View style={styles.escalationNotice}>
      <View style={styles.escalationHeading}>
        <Ionicons
          name="alert-circle"
          size={22}
          color="#B42318"
        />

        <Text style={styles.escalationTitle}>
          Escalated Incident
        </Text>
      </View>

      <Text style={styles.escalationMessage}>
        Your report has been escalated for additional attention.
      </Text>

      {!!reason?.trim() && (
        <View style={styles.escalationReasonBox}>
          <Text style={styles.escalationReasonLabel}>
            REASON
          </Text>

          <Text style={styles.escalationReasonText}>
            {reason}
          </Text>
        </View>
      )}

      {!!escalatedAt && (
        <Text style={styles.escalationDate}>
          Escalated: {formatDate(escalatedAt)}
        </Text>
      )}
    </View>
  );
}

function StatusProgressTimeline({
  status,
}: {
  status: string;
}) {
  const normalized = status.trim().toLowerCase().replace(/_/g, ' ');

  const specialStatus =
    normalized === 'rejected' ||
    normalized === 'needs more information';

  if (specialStatus) {
    const rejected = normalized === 'rejected';

    return (
      <View
        style={[
          styles.progressNotice,
          rejected
            ? styles.progressNoticeError
            : styles.progressNoticeWarning,
        ]}
      >
        <Ionicons
          name={
            rejected
              ? 'close-circle-outline'
              : 'information-circle-outline'
          }
          size={23}
          color={rejected ? '#B42318' : '#B54708'}
        />

        <View style={styles.progressNoticeContent}>
          <Text
            style={[
              styles.progressNoticeTitle,
              { color: rejected ? '#B42318' : '#B54708' },
            ]}
          >
            {rejected
              ? 'Report Rejected'
              : 'More Information Needed'}
          </Text>

          <Text style={styles.progressNoticeText}>
            {rejected
              ? 'This report was not approved. Review the latest update or status history for more details.'
              : 'The response team needs additional details. Check the latest update or status history for instructions.'}
          </Text>
        </View>
      </View>
    );
  }

  const currentIndex = INCIDENT_STEPS.findIndex(
    (step) => step.key.toLowerCase() === normalized,
  );

  const activeIndex = currentIndex < 0 ? 0 : currentIndex;

  return (
    <View style={styles.progressPanel}>
      <Text style={styles.sectionTitle}>Report Progress</Text>

      <Text style={styles.progressSubtitle}>
        Track each stage of your incident report.
      </Text>

      {INCIDENT_STEPS.map((step, index) => {
        const resolved = normalized === 'resolved';
        const completed = index < activeIndex || resolved;
        const current = index === activeIndex && !resolved;
        const reached = index <= activeIndex;
        const last = index === INCIDENT_STEPS.length - 1;

        return (
          <View key={step.key} style={styles.progressStepRow}>
            <View style={styles.progressRail}>
              <View
                style={[
                  styles.progressDot,
                  reached && styles.progressDotReached,
                  current && styles.progressDotCurrent,
                  resolved && styles.progressDotReached,
                ]}
              >
                {(completed || (resolved && index === activeIndex)) && (
                  <Ionicons
                    name="checkmark"
                    size={13}
                    color="#FFFFFF"
                  />
                )}

                {current && (
                  <View style={styles.progressCurrentDot} />
                )}
              </View>

              {!last && (
                <View
                  style={[
                    styles.progressLine,
                    (index < activeIndex || resolved) &&
                      styles.progressLineCompleted,
                  ]}
                />
              )}
            </View>

            <View style={styles.progressStepContent}>
              <Text
                style={[
                  styles.progressStepTitle,
                  reached && styles.progressStepTitleReached,
                ]}
              >
                {step.title}
                {current ? ' · Current status' : ''}
              </Text>

              <Text style={styles.progressStepDescription}>
                {step.description}
              </Text>
            </View>
          </View>
        );
      })}
    </View>
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

  const [escalationHistory, setEscalationHistory] = useState<
    EscalationHistoryItem[]
  >([]);

  const [escalationHistoryLoading, setEscalationHistoryLoading] =
    useState(false);

  const [escalationHistoryError, setEscalationHistoryError] =
    useState('');

  const loadReports = useCallback(async (showLoader = true) => {
    if (showLoader) setLoading(true);

    setError('');

    try {
      const {
        data: authData,
        error: authError,
      } = await supabase.auth.getUser();

      if (authError) throw authError;

      const user = authData.user;

      if (!user) {
        setUserId(null);
        setIncidents([]);
        setSelected(null);
        setError('Please sign in to view your reports.');
        return;
      }

      setUserId(user.id);

      const { data, error: queryError } = await supabase
        .from('incidents')
        .select(`
          id,
          report_id,
          created_at,
          updated_at,
          category,
          location,
          description,
          photo_url,
          user_id,
          status,
          resident_status,
          latest_update,
          resolved_at,
          priority_level,
          is_escalated,
          escalation_reason,
          escalated_at
        `)
        .eq('user_id', user.id)
        .order('created_at', { ascending: false });

      if (queryError) throw queryError;

      const refreshedIncidents = (data || []) as Incident[];

      setIncidents(refreshedIncidents);

      // Update the open details modal when the admin changes a report.
      setSelected((currentSelected) => {
        if (!currentSelected) return null;

        const refreshedSelected = refreshedIncidents.find(
          (incident) =>
            String(incident.id) === String(currentSelected.id),
        );

        if (!refreshedSelected) return currentSelected;

        const fieldsToCompare: (keyof Incident)[] = [
          'resident_status',
          'latest_update',
          'updated_at',
          'resolved_at',
          'priority_level',
          'is_escalated',
          'escalation_reason',
          'escalated_at',
        ];

        const unchanged = fieldsToCompare.every(
          (field) =>
            refreshedSelected[field] === currentSelected[field],
        );

        return unchanged ? currentSelected : refreshedSelected;
      });
    } catch (err) {
      console.error('Failed to load incident reports:', err);
      setError('Unable to load your reports. Please try again.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  // Refresh the report list when the screen is focused.
  // The open report also stays synchronized with admin edits.
  useFocusEffect(
    useCallback(() => {
      void loadReports();

      const refreshInterval = setInterval(() => {
        void loadReports(false);
      }, 10000);

      return () => clearInterval(refreshInterval);
    }, [loadReports]),
  );

  // Reload history when the selected report or its update fields change.
  useEffect(() => {
    let active = true;

    async function loadSelectedHistory() {
      if (!selected || !userId) {
        setHistory([]);
        setHistoryError('');
        setEscalationHistory([]);
        setEscalationHistoryError('');
        return;
      }

      const incidentId = String(selected.id);

      setHistoryLoading(true);
      setHistoryError('');
      setEscalationHistoryLoading(true);
      setEscalationHistoryError('');

      const statusHistoryPromise = supabase
        .from('incident_status_history')
        .select('id, incident_id, status, message, created_at')
        .eq('incident_id', incidentId)
        .order('created_at', { ascending: true });

      const escalationHistoryPromise = supabase
        .from('incident_escalation_history')
        .select(
          'id, incident_id, priority_level, escalation_reason, created_at',
        )
        .eq('incident_id', incidentId)
        .order('created_at', { ascending: true });

      try {
        const { data, error: queryError } = await statusHistoryPromise;

        if (queryError) throw queryError;

        if (active) {
          setHistory((data || []) as HistoryItem[]);
        }
      } catch (err) {
        console.error('Failed to load status history:', err);

        if (active) {
          setHistoryError(
            'Status history is temporarily unavailable.',
          );
        }
      } finally {
        if (active) setHistoryLoading(false);
      }

      try {
        const {
          data,
          error: escalationQueryError,
        } = await escalationHistoryPromise;

        if (escalationQueryError) throw escalationQueryError;

        if (active) {
          setEscalationHistory(
            (data || []) as EscalationHistoryItem[],
          );
        }
      } catch (err) {
        console.error('Failed to load escalation history:', err);

        if (active) {
          setEscalationHistoryError(
            'Escalation history is unavailable. Check your table permissions and schema.',
          );
        }
      } finally {
        if (active) setEscalationHistoryLoading(false);
      }
    }

    void loadSelectedHistory();

    return () => {
      active = false;
    };
  }, [
    selected?.id,
    selected?.resident_status,
    selected?.latest_update,
    selected?.updated_at,
    selected?.resolved_at,
    selected?.priority_level,
    selected?.is_escalated,
    selected?.escalation_reason,
    selected?.escalated_at,
    userId,
  ]);

  const visibleReports = useMemo(() => {
    const term = search.trim().toLowerCase();

    return incidents.filter((incident) => {
      const status = getResidentStatus(incident);

      const matchesFilter = filter === 'All' || status === filter;

      const searchableValues = [
        incident.category,
        incident.location,
        incident.description,
        incident.report_id,
        incident.priority_level,
        incident.escalation_reason,
      ];

      const matchesSearch =
        !term ||
        searchableValues.some((value) =>
          value?.toLowerCase().includes(term),
        );

      return matchesFilter && matchesSearch;
    });
  }, [incidents, search, filter]);

  const counts = useMemo(() => {
    return {
      total: incidents.length,

      active: incidents.filter(
        (item) => getResidentStatus(item) !== 'Resolved',
      ).length,

      resolved: incidents.filter(
        (item) => getResidentStatus(item) === 'Resolved',
      ).length,

      escalated: incidents.filter(
        (item) => item.is_escalated === true,
      ).length,
    };
  }, [incidents]);

  function openReport(incident: Incident) {
    setHistory([]);
    setHistoryError('');
    setEscalationHistory([]);
    setEscalationHistoryError('');
    setSelected(incident);
  }

  function closeReport() {
    setSelected(null);
    setHistory([]);
    setHistoryError('');
    setEscalationHistory([]);
    setEscalationHistoryError('');
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

          <Ionicons
            name="chevron-forward"
            size={20}
            color="#98A2B3"
          />
        </View>

        <View style={styles.locationRow}>
          <Ionicons
            name="location-outline"
            size={16}
            color="#667085"
          />

          <Text style={styles.location} numberOfLines={2}>
            {item.location || 'Location not provided'}
          </Text>
        </View>

        <View style={styles.badgeRow}>
          <StatusBadge status={status} />
          <PriorityBadge priority={item.priority_level} />
        </View>

        {item.is_escalated === true && (
          <View style={styles.cardEscalation}>
            <Ionicons
              name="alert-circle"
              size={16}
              color="#B42318"
            />

            <Text style={styles.cardEscalationText}>
              Escalated — requires additional attention
            </Text>
          </View>
        )}

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
          <Ionicons
            name="calendar-outline"
            size={15}
            color="#667085"
          />

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
      <StatusBar
        barStyle="dark-content"
        backgroundColor="#F5F8FC"
      />

      <View style={styles.header}>
        <View style={styles.headerTextContainer}>
          <Text style={styles.eyebrow}>TALIRESQ</Text>

          <Text style={styles.title}>My Reports</Text>

          <Text style={styles.subtitle}>
            Track your submitted incidents and updates.
          </Text>
        </View>

        <View style={styles.headerIcon}>
          <Ionicons
            name="document-text-outline"
            size={26}
            color="#145DA0"
          />
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

      {counts.escalated > 0 && (
        <View style={styles.escalatedSummary}>
          <Ionicons
            name="alert-circle"
            size={19}
            color="#B42318"
          />

          <Text style={styles.escalatedSummaryText}>
            {counts.escalated}{' '}
            {counts.escalated === 1 ? 'report is' : 'reports are'}{' '}
            currently escalated.
          </Text>
        </View>
      )}

      <View style={styles.searchBox}>
        <Ionicons
          name="search-outline"
          size={20}
          color="#667085"
        />

        <TextInput
          value={search}
          onChangeText={setSearch}
          placeholder="Search by location, category, priority"
          placeholderTextColor="#98A2B3"
          style={styles.searchInput}
          returnKeyType="search"
          accessibilityLabel="Search reports"
        />

        {search.length > 0 && (
          <Pressable
            onPress={() => setSearch('')}
            hitSlop={8}
          >
            <Ionicons
              name="close-circle"
              size={20}
              color="#98A2B3"
            />
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
              style={[
                styles.filterChip,
                active && styles.filterChipActive,
              ]}
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

          <Text style={styles.stateText}>
            Loading your reports...
          </Text>
        </View>
      ) : error ? (
        <View style={styles.centerState}>
          <Ionicons
            name="cloud-offline-outline"
            size={44}
            color="#98A2B3"
          />

          <Text style={styles.stateTitle}>
            Could not load reports
          </Text>

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
                  <Text style={styles.clearFilters}>
                    Clear filters
                  </Text>
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
        onRequestClose={closeReport}
      >
        {selected && (
          <SafeAreaView style={styles.modalSafe}>
            <View style={styles.modalHeader}>
              <Pressable
                onPress={closeReport}
                style={styles.backButton}
                accessibilityRole="button"
                accessibilityLabel="Close report details"
              >
                <Ionicons
                  name="arrow-back"
                  size={23}
                  color="#101828"
                />
              </Pressable>

              <Text style={styles.modalTitle}>
                Report Details
              </Text>

              <View style={styles.headerSpacer} />
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

              <View style={styles.badgeRow}>
                <StatusBadge
                  status={getResidentStatus(selected)}
                />

                <PriorityBadge
                  priority={selected.priority_level}
                />
              </View>

              {selected.is_escalated === true && (
                <EscalationNotice
                  reason={selected.escalation_reason}
                  escalatedAt={selected.escalated_at}
                />
              )}

              <StatusProgressTimeline
                status={getResidentStatus(selected)}
              />

              <View style={styles.detailPanel}>
                <Text style={styles.sectionTitle}>
                  Incident information
                </Text>

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
                  value={formatDate(
                    selected.updated_at || selected.created_at,
                  )}
                />

                <View style={styles.descriptionBlock}>
                  <Text style={styles.fieldLabel}>
                    DESCRIPTION
                  </Text>

                  <Text style={styles.description}>
                    {selected.description || 'No description provided.'}
                  </Text>
                </View>
              </View>

              <View style={styles.latestPanel}>
                <Text style={styles.sectionTitle}>
                  Latest update
                </Text>

                <Text style={styles.latestMessage}>
                  {selected.latest_update ||
                    'Your report has been submitted and is awaiting review.'}
                </Text>

                <Text style={styles.updatedAt}>
                  {formatDate(
                    selected.updated_at || selected.created_at,
                  )}
                </Text>
              </View>

              <View style={styles.timelinePanel}>
                <Text style={styles.sectionTitle}>
                  Status history
                </Text>

                <Text style={styles.timelineSubtitle}>
                  Follow the progress of your report.
                </Text>

                {historyLoading ? (
                  <ActivityIndicator
                    style={styles.historyLoader}
                    color="#145DA0"
                  />
                ) : historyError ? (
                  <Text style={styles.stateText}>
                    {historyError}
                  </Text>
                ) : history.length === 0 ? (
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
                      New administrator updates will appear here when
                      they are recorded in the history table.
                    </Text>
                  </View>
                ) : (
                  history.map((entry, index) => {
                    const statusStyle =
                      STATUS_STYLES[entry.status] ||
                      STATUS_STYLES.Submitted;

                    const last = index === history.length - 1;

                    return (
                      <View
                        key={entry.id}
                        style={styles.timelineItem}
                      >
                        <View style={styles.timelineRail}>
                          <View
                            style={[
                              styles.timelineDot,
                              {
                                backgroundColor: statusStyle.color,
                              },
                            ]}
                          />

                          {!last && (
                            <View style={styles.timelineLine} />
                          )}
                        </View>

                        <View style={styles.timelineEntry}>
                          <StatusBadge status={entry.status} />

                          <Text style={styles.timelineMessage}>
                            {entry.message ||
                              `Status changed to ${entry.status}.`}
                          </Text>

                          <Text style={styles.timelineDate}>
                            {formatDate(entry.created_at)}
                          </Text>
                        </View>
                      </View>
                    );
                  })
                )}
              </View>

              <View style={styles.timelinePanel}>
                <Text style={styles.sectionTitle}>
                  Escalation history
                </Text>

                <Text style={styles.timelineSubtitle}>
                  Review priority changes and escalation reasons recorded
                  by the response team.
                </Text>

                {escalationHistoryLoading ? (
                  <ActivityIndicator
                    style={styles.historyLoader}
                    color="#145DA0"
                  />
                ) : escalationHistoryError ? (
                  <Text style={styles.stateText}>
                    {escalationHistoryError}
                  </Text>
                ) : escalationHistory.length === 0 ? (
                  <View style={styles.historyEmpty}>
                    <Ionicons
                      name="shield-checkmark-outline"
                      size={25}
                      color="#98A2B3"
                    />

                    <Text style={styles.stateText}>
                      No escalation history is available.
                    </Text>
                  </View>
                ) : (
                  escalationHistory.map((entry) => (
                    <View
                      key={entry.id}
                      style={styles.escalationHistoryItem}
                    >
                      <View style={styles.escalationHistoryHeader}>
                        <Ionicons
                          name="alert-circle"
                          size={18}
                          color="#B42318"
                        />

                        <Text style={styles.escalationHistoryTitle}>
                          Incident escalated
                        </Text>
                      </View>

                      <View style={styles.escalationHistoryPriority}>
                        <PriorityBadge
                          priority={entry.priority_level}
                        />
                      </View>

                      <Text style={styles.escalationHistoryReason}>
                        {entry.escalation_reason}
                      </Text>

                      <Text style={styles.timelineDate}>
                        {formatDate(entry.created_at)}
                      </Text>
                    </View>
                  ))
                )}
              </View>
            </ScrollView>
          </SafeAreaView>
        )}
      </Modal>
    </SafeAreaView>
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

  headerTextContainer: {
    flex: 1,
    paddingRight: 12,
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

  escalatedSummary: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginHorizontal: 16,
    marginBottom: 12,
    padding: 11,
    borderRadius: 11,
    backgroundColor: '#FEF2F2',
    borderWidth: 1,
    borderColor: '#FECACA',
  },

  escalatedSummaryText: {
    flex: 1,
    color: '#B42318',
    fontSize: 12,
    fontWeight: '700',
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

  badgeRow: {
    flexDirection: 'row',
    alignItems: 'center',
    flexWrap: 'wrap',
    gap: 8,
    marginTop: 2,
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

  priorityBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    alignSelf: 'flex-start',
    gap: 5,
    borderRadius: 8,
    paddingHorizontal: 9,
    paddingVertical: 6,
  },

  priorityBadgeText: {
    fontSize: 11,
    fontWeight: '700',
  },

  cardEscalation: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 7,
    marginTop: 11,
    padding: 10,
    backgroundColor: '#FEF2F2',
    borderRadius: 10,
    borderWidth: 1,
    borderColor: '#FECACA',
  },

  cardEscalationText: {
    flex: 1,
    fontSize: 12,
    fontWeight: '700',
    color: '#B42318',
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

  headerSpacer: {
    width: 42,
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

  escalationNotice: {
    marginTop: 14,
    padding: 15,
    backgroundColor: '#FEF2F2',
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#FECACA',
  },

  escalationHeading: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 7,
  },

  escalationTitle: {
    fontSize: 15,
    fontWeight: '800',
    color: '#B42318',
  },

  escalationMessage: {
    fontSize: 13,
    lineHeight: 20,
    color: '#7F1D1D',
  },

  escalationReasonBox: {
    marginTop: 12,
    padding: 11,
    backgroundColor: '#FFFFFF',
    borderRadius: 10,
  },

  escalationReasonLabel: {
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 0.8,
    color: '#B42318',
    marginBottom: 5,
  },

  escalationReasonText: {
    fontSize: 13,
    lineHeight: 20,
    color: '#344054',
  },

  escalationDate: {
    marginTop: 10,
    fontSize: 11,
    color: '#7F1D1D',
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

  progressPanel: {
    backgroundColor: '#FFFFFF',
    borderRadius: 16,
    padding: 16,
    marginTop: 14,
    borderWidth: 1,
    borderColor: '#E6EBF2',
  },

  progressSubtitle: {
    color: '#667085',
    fontSize: 12,
    lineHeight: 18,
    marginTop: -8,
    marginBottom: 18,
  },

  progressStepRow: {
    flexDirection: 'row',
    minHeight: 66,
  },

  progressRail: {
    width: 27,
    alignItems: 'center',
    marginRight: 10,
  },

  progressDot: {
    width: 23,
    height: 23,
    borderRadius: 12,
    borderWidth: 2,
    borderColor: '#CBD5E1',
    backgroundColor: '#FFFFFF',
    alignItems: 'center',
    justifyContent: 'center',
    zIndex: 1,
  },

  progressDotReached: {
    backgroundColor: '#16A34A',
    borderColor: '#16A34A',
  },

  progressDotCurrent: {
    backgroundColor: '#FFFFFF',
    borderColor: '#2563EB',
  },

  progressCurrentDot: {
    width: 9,
    height: 9,
    borderRadius: 5,
    backgroundColor: '#2563EB',
  },

  progressLine: {
    position: 'absolute',
    top: 23,
    width: 2,
    height: 43,
    backgroundColor: '#CBD5E1',
  },

  progressLineCompleted: {
    backgroundColor: '#16A34A',
  },

  progressStepContent: {
    flex: 1,
    paddingBottom: 16,
  },

  progressStepTitle: {
    fontSize: 13,
    fontWeight: '700',
    color: '#64748B',
  },

  progressStepTitleReached: {
    color: '#123B5D',
  },

  progressStepDescription: {
    fontSize: 12,
    lineHeight: 18,
    color: '#64748B',
    marginTop: 4,
  },

  progressNotice: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 10,
    padding: 13,
    borderRadius: 12,
    marginTop: 14,
  },

  progressNoticeError: {
    backgroundColor: '#FEF2F2',
  },

  progressNoticeWarning: {
    backgroundColor: '#FFFBEB',
  },

  progressNoticeContent: {
    flex: 1,
  },

  progressNoticeTitle: {
    fontSize: 14,
    fontWeight: '800',
    marginBottom: 4,
  },

  progressNoticeText: {
    color: '#475467',
    fontSize: 12,
    lineHeight: 18,
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
    lineHeight: 18,
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

  historyLoader: {
    marginVertical: 24,
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

  escalationHistoryItem: {
    padding: 13,
    backgroundColor: '#FFF8F7',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#FEE2E2',
    marginBottom: 12,
  },

  escalationHistoryHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 7,
    marginBottom: 10,
  },

  escalationHistoryTitle: {
    fontSize: 13,
    fontWeight: '800',
    color: '#B42318',
  },

  escalationHistoryPriority: {
    alignItems: 'flex-start',
    marginBottom: 10,
  },

  escalationHistoryReason: {
    fontSize: 13,
    lineHeight: 20,
    color: '#344054',
  },
});