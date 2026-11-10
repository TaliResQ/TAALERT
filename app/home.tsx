
import { supabase } from '@/src/services/supabase';
import { Feather } from '@expo/vector-icons';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
  Alert,
  Animated,
  Modal,
  RefreshControl,
  SafeAreaView,
  ScrollView,
  StatusBar,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';

import { useRouter } from 'expo-router';
import BottomNavbar from '../components/BottomNavbar';
import { COLORS, IndexStyles as styles } from '../constants/theme';

// ========================================
// TYPES
// ========================================

type CommunityActivity = {
  activity_id: string;
  title: string;
  activity_date: string;
  description: string | null;
  target_area: string;
};

type ActivityNotification = {
  notification_id: string;
  activity_id: string;
  title: string;
  message: string;
  created_at: string;
};

// ========================================
// DATE FORMATTER
// ========================================

const formatActivityDate = (dateString: string) => {
  const [year, month, day] = dateString.split('-').map(Number);

  const date = new Date(year, month - 1, day);

  return date.toLocaleDateString('en-US', {
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  });
};

// ========================================
// MAIN SCREEN
// ========================================

export default function DisasterAppUI() {
  const router = useRouter();

  // ========================================
  // ANIMATIONS
  // ========================================

  const fadeAnim = useRef(new Animated.Value(0)).current;
  const slideUpAnim = useRef(new Animated.Value(30)).current;

  // ========================================
  // REFRESH
  // ========================================

  const [refreshing, setRefreshing] = useState(false);

  // ========================================
  // USER
  // ========================================

  const [userName, setUserName] = useState('User');

  // ========================================
  // COMMUNITY ACTIVITIES
  // ========================================

  const [activities, setActivities] = useState<CommunityActivity[]>([]);
  const [activitiesLoading, setActivitiesLoading] = useState(true);
  const [activitiesError, setActivitiesError] = useState<string | null>(null);

  // ========================================
  // NOTIFICATIONS
  // ========================================

  const [notifications, setNotifications] = useState<ActivityNotification[]>([]);
  const [readNotificationIds, setReadNotificationIds] = useState<string[]>([]);

  const [notificationsVisible, setNotificationsVisible] = useState(false);
  const [notificationLoading, setNotificationLoading] = useState(false);
  const [notificationError, setNotificationError] = useState<string | null>(null);

  // ========================================
  // ACTIVITY DETAILS MODAL
  // ========================================

  const [selectedActivity, setSelectedActivity] =
    useState<CommunityActivity | null>(null);

  const [activityModalVisible, setActivityModalVisible] = useState(false);

  // ========================================
  // SOS
  // ========================================

  const [isSosExpanded, setIsSosExpanded] = useState(false);
  const [holdProgress, setHoldProgress] = useState(5);

  const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);

  // ========================================
  // UNREAD NOTIFICATION COUNT
  // ========================================

  const unreadCount = notifications.filter(
    (item) => !readNotificationIds.includes(item.notification_id)
  ).length;

  // ========================================
  // FETCH USER INFORMATION
  // ========================================

  const fetchUser = useCallback(async () => {
    try {
      const {
        data: { user },
        error: authError,
      } = await supabase.auth.getUser();

      if (authError || !user) {
        console.error(
          'Error fetching authenticated user:',
          authError?.message
        );

        setUserName('User');
        return;
      }

      const { data: profile, error: profileError } = await supabase
        .from('users')
        .select('fullname')
        .eq('id', user.id)
        .maybeSingle();

      if (profileError) {
        console.error('Error fetching profile:', profileError.message);
        setUserName('User');
        return;
      }

      if (!profile) {
        console.error('No matching profile found for Auth ID:', user.id);
        setUserName('User');
        return;
      }

      setUserName(profile.fullname?.trim() || 'User');

    } catch (error) {
      console.error('Unexpected user fetch error:', error);
      setUserName('User');
    }
  }, []);

  // ========================================
  // FETCH COMMUNITY ACTIVITIES
  // ========================================

  const fetchActivities = useCallback(async () => {
    setActivitiesLoading(true);
    setActivitiesError(null);

    try {
      const { data, error } = await supabase
        .from('community_activities')
        .select(
          'activity_id, title, activity_date, description, target_area'
        )
        .eq('is_published', true)
        .order('activity_date', { ascending: true })
        .limit(10);

      if (error) {
        console.error('Error fetching activities:', error.message);
        setActivitiesError('Unable to load community activities.');
        return;
      }

      setActivities(data ?? []);

    } catch (error) {
      console.error('Unexpected activity error:', error);
      setActivitiesError('Something went wrong. Please try again.');

    } finally {
      setActivitiesLoading(false);
    }
  }, []);

  // ========================================
  // FETCH NOTIFICATIONS
  // ========================================

  const fetchNotifications = useCallback(async () => {
    setNotificationLoading(true);
    setNotificationError(null);

    try {
      const {
        data: { user },
        error: userError,
      } = await supabase.auth.getUser();

      if (userError || !user) {
        setNotifications([]);
        setReadNotificationIds([]);

        setNotificationError(
          'Please sign in to view your notifications.'
        );

        return;
      }

      const [notificationResult, readResult] = await Promise.all([
        supabase
          .from('activity_notifications')
          .select(
            'notification_id, activity_id, title, message, created_at'
          )
          .order('created_at', { ascending: false })
          .limit(30),

        supabase
          .from('user_notification_reads')
          .select('notification_id')
          .eq('user_id', user.id),
      ]);

      if (notificationResult.error) {
        console.error(
          'Notification fetch failed:',
          notificationResult.error.message
        );

        setNotificationError('Unable to load notifications.');
        return;
      }

      if (readResult.error) {
        console.error(
          'Read status fetch failed:',
          readResult.error.message
        );

        setNotificationError(
          'Unable to load notification read status.'
        );

        return;
      }

      setNotifications(notificationResult.data ?? []);

      setReadNotificationIds(
        (readResult.data ?? []).map(
          (item) => item.notification_id
        )
      );

    } catch (error) {
      console.error('Unexpected notification error:', error);

      setNotificationError(
        'Something went wrong while loading notifications.'
      );

    } finally {
      setNotificationLoading(false);
    }
  }, []);

  // ========================================
  // OPEN ACTIVITY DETAILS
  // ========================================

  const openActivity = (activity: CommunityActivity) => {
    setSelectedActivity(activity);
    setActivityModalVisible(true);
  };

  // ========================================
  // CLOSE ACTIVITY DETAILS
  // ========================================

  const closeActivity = () => {
    setActivityModalVisible(false);
  };

  // ========================================
  // MARK NOTIFICATION AS READ
  // ========================================

  const markNotificationAsRead = async (
    notificationId: string
  ): Promise<boolean> => {
    try {
      const {
        data: { user },
        error: userError,
      } = await supabase.auth.getUser();

      if (userError || !user) {
        Alert.alert(
          'Sign in required',
          'Please sign in to update your notification.'
        );

        return false;
      }

      // Already read
      if (readNotificationIds.includes(notificationId)) {
        return true;
      }

      const { error } = await supabase
        .from('user_notification_reads')
        .upsert(
          {
            notification_id: notificationId,
            user_id: user.id,
          },
          {
            onConflict: 'notification_id,user_id',
            ignoreDuplicates: true,
          }
        );

      if (error) {
        console.error(
          'Error marking notification as read:',
          error.message
        );

        Alert.alert(
          'Error',
          'Could not update notification status. Please try again.'
        );

        return false;
      }

      setReadNotificationIds((previous) =>
        previous.includes(notificationId)
          ? previous
          : [...previous, notificationId]
      );

      return true;

    } catch (error) {
      console.error('Unexpected notification update error:', error);

      Alert.alert(
        'Error',
        'Something went wrong while updating the notification.'
      );

      return false;
    }
  };

  // ========================================
  // OPEN ACTIVITY FROM NOTIFICATION
  // ========================================

  const handleNotificationPress = async (
    notification: ActivityNotification
  ) => {
    // Mark notification as read first
    const markedRead = await markNotificationAsRead(
      notification.notification_id
    );

    if (!markedRead) {
      return;
    }

    try {
      // Retrieve the corresponding community activity
      const { data, error } = await supabase
        .from('community_activities')
        .select(
          'activity_id, title, activity_date, description, target_area'
        )
        .eq('activity_id', notification.activity_id)
        .maybeSingle();

      if (error) {
        console.error('Error retrieving activity:', error.message);

        Alert.alert(
          'Error',
          'Unable to retrieve the community activity.'
        );

        return;
      }

      if (!data) {
        Alert.alert(
          'Activity unavailable',
          'This community activity could not be found or is no longer available.'
        );

        return;
      }

      // Close notifications modal
      setNotificationsVisible(false);

      // Open expanded activity card
      openActivity(data as CommunityActivity);

    } catch (error) {
      console.error('Unexpected activity retrieval error:', error);

      Alert.alert(
        'Error',
        'Something went wrong while opening the activity.'
      );
    }
  };

  // ========================================
  // PULL DOWN TO REFRESH
  // ========================================

  const onRefresh = async () => {
    setRefreshing(true);

    try {
      await Promise.all([
        fetchUser(),
        fetchActivities(),
        fetchNotifications(),
      ]);

    } catch (error) {
      console.error('Error refreshing home screen:', error);

    } finally {
      setRefreshing(false);
    }
  };

  // ========================================
  // INITIAL SCREEN SETUP
  // ========================================

  useEffect(() => {
    Animated.parallel([
      Animated.timing(fadeAnim, {
        toValue: 1,
        duration: 600,
        useNativeDriver: true,
      }),

      Animated.spring(slideUpAnim, {
        toValue: 0,
        friction: 6,
        useNativeDriver: true,
      }),
    ]).start();

    fetchUser();
    fetchActivities();
    fetchNotifications();

    return () => {
      if (timerRef.current) {
        clearInterval(timerRef.current);
        timerRef.current = null;
      }
    };
  }, [
    fadeAnim,
    slideUpAnim,
    fetchUser,
    fetchActivities,
    fetchNotifications,
  ]);

  // ========================================
  // SOS BUTTON LOGIC
  // ========================================

  const handlePressIn = () => {
    let count = 5;

    setHoldProgress(5);

    if (timerRef.current) {
      clearInterval(timerRef.current);
      timerRef.current = null;
    }

    timerRef.current = setInterval(() => {
      count -= 1;

      if (count > 0) {
        setHoldProgress(count);

      } else {
        if (timerRef.current) {
          clearInterval(timerRef.current);
          timerRef.current = null;
        }

        triggerSos();
      }
    }, 1000);
  };

  const handlePressOut = () => {
    if (timerRef.current) {
      clearInterval(timerRef.current);
      timerRef.current = null;
    }

    setHoldProgress(5);
  };

  const triggerSos = () => {
    setIsSosExpanded(false);
    setHoldProgress(5);

    Alert.alert(
      'SOS Button Triggered',
      'This is currently a demo confirmation. Your location has not been sent to emergency responders yet.',
      [{ text: 'OK' }]
    );
  };

  const cancelSos = () => {
    if (timerRef.current) {
      clearInterval(timerRef.current);
      timerRef.current = null;
    }

    setIsSosExpanded(false);
    setHoldProgress(5);
  };

  // ========================================
  // RENDER SCREEN
  // ========================================

  return (
    <SafeAreaView style={styles.container}>
      <Animated.View
        style={{
          flex: 1,
          opacity: fadeAnim,
          transform: [{ translateY: slideUpAnim }],
        }}
      >
        <StatusBar barStyle="dark-content" />

        <ScrollView
          contentContainerStyle={styles.scrollContent}
          showsVerticalScrollIndicator={false}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={onRefresh}
              colors={['#25A5FE']}
              tintColor="#25A5FE"
              title="Refreshing home..."
              titleColor="#64748B"
            />
          }
        >

          {/* ========================================
              HEADER
          ======================================== */}

          <View style={styles.header}>
            <View>
              <Text style={styles.greetingText}>Hello,</Text>

              <Text style={styles.nameText}>
                {userName} 👋
              </Text>
            </View>

            <TouchableOpacity
              style={styles.notificationBtn}
              accessibilityLabel={`Notifications, ${unreadCount} unread`}
              onPress={() => {
                setNotificationsVisible(true);
                fetchNotifications();
              }}
            >
              <Feather
                name="bell"
                color={COLORS.primaryBlue}
                size={24}
              />

              {unreadCount > 0 && (
                <View
                  style={{
                    position: 'absolute',
                    top: 0,
                    right: 0,
                    minWidth: 18,
                    height: 18,
                    borderRadius: 9,
                    backgroundColor: COLORS.danger,
                    alignItems: 'center',
                    justifyContent: 'center',
                    paddingHorizontal: 3,
                  }}
                >
                  <Text
                    style={{
                      color: '#FFFFFF',
                      fontSize: 10,
                      fontWeight: 'bold',
                    }}
                  >
                    {unreadCount > 99 ? '99+' : unreadCount}
                  </Text>
                </View>
              )}
            </TouchableOpacity>
          </View>

          {/* ========================================
              VOLCANO STATUS CARD
          ======================================== */}

          <View style={styles.statusCard}>
            <View style={styles.statusHeader}>
              <Feather
                name="shield"
                color={COLORS.white}
                size={16}
              />

              <Text style={styles.statusHeaderText}>
                CURRENT STATUS
              </Text>
            </View>

            <Text style={styles.volcanoName}>
              Taal Volcano
            </Text>

            <Text style={styles.alertLevel}>
              Alert Level 1 · Normal conditions
            </Text>

            <View style={styles.statusBadgesRow}>
              <View style={styles.statusBadge}>
                <Text style={styles.statusBadgeText}>
                  Updated 2 min ago
                </Text>
              </View>

              <View style={styles.statusBadge}>
                <Feather
                  name="wind"
                  color={COLORS.white}
                  size={14}
                  style={{ marginRight: 4 }}
                />

                <Text style={styles.statusBadgeText}>
                  SW wind
                </Text>
              </View>
            </View>
          </View>

          {/* ========================================
              QUICK ACTIONS
          ======================================== */}

          <Text style={styles.sectionTitle}>
            QUICK ACTIONS
          </Text>

          <View style={styles.quickActionsContainer}>

            {/* SOS */}

            <TouchableOpacity
              style={styles.sosCard}
              onPress={() => setIsSosExpanded(true)}
            >
              <View style={styles.sosIconContainer}>
                <Feather
                  name="alert-circle"
                  color={COLORS.white}
                  size={28}
                />
              </View>

              <View>
                <Text style={styles.sosTitle}>
                  Emergency SOS
                </Text>

                <Text style={styles.sosSub}>
                  Press to alert responders
                </Text>
              </View>
            </TouchableOpacity>

            <View style={styles.rightActionsColumn}>

              {/* Hotlines */}

              <TouchableOpacity
                style={styles.actionCard}
                onPress={() => router.push('/hotlines')}
              >
                <View style={styles.actionIconContainer}>
                  <Feather
                    name="phone"
                    color={COLORS.primaryBlue}
                    size={20}
                  />
                </View>

                <Text style={styles.actionTitle}>
                  Hotlines
                </Text>

                <Text style={styles.actionSub}>
                  911 · PHIVOLCS
                </Text>
              </TouchableOpacity>

              {/* Shelters */}

              <TouchableOpacity
                style={styles.actionCard}
                onPress={() => router.push('/map')}
              >
                <View style={styles.actionIconContainer}>
                  <Feather
                    name="plus-square"
                    color={COLORS.primaryBlue}
                    size={20}
                  />
                </View>

                <Text style={styles.actionTitle}>
                  Shelters
                </Text>

                <Text style={styles.actionSub}>
                  View nearby centers
                </Text>
              </TouchableOpacity>

            </View>
          </View>

          {/* ========================================
              RECENT ACTIVITIES
          ======================================== */}

          <View style={styles.activityHeader}>
            <Text style={styles.sectionTitleDark}>
              Recent Activities
            </Text>
          </View>

          {/* Loading */}

          {activitiesLoading ? (
            <View style={styles.activityCard}>
              <Text style={styles.activityDesc}>
                Loading community activities...
              </Text>
            </View>

          ) : activitiesError ? (

            /* Error */

            <View style={styles.activityCard}>
              <Feather
                name="alert-circle"
                size={24}
                color={COLORS.danger}
              />

              <Text style={styles.activityDesc}>
                {activitiesError}
              </Text>

              <TouchableOpacity onPress={fetchActivities}>
                <Text style={styles.seeAllText}>
                  Try again
                </Text>
              </TouchableOpacity>
            </View>

          ) : activities.length === 0 ? (

            /* Empty state */

            <View style={styles.activityCard}>
              <Feather
                name="calendar"
                size={24}
                color={COLORS.primaryBlue}
              />

              <Text style={styles.activityTitle}>
                No activities yet
              </Text>

              <Text style={styles.activityDesc}>
                Community activities and preparedness events
                will appear here when published.
              </Text>

              <TouchableOpacity onPress={fetchActivities}>
                <Text style={styles.seeAllText}>
                  Refresh
                </Text>
              </TouchableOpacity>
            </View>

          ) : (

            /* Activity Cards */

            activities.map((item) => (
              <TouchableOpacity
                key={item.activity_id}
                style={styles.activityCard}
                activeOpacity={0.75}
                onPress={() => openActivity(item)}
              >

                <View style={styles.activityCardHeader}>
                  <View style={styles.timeContainer}>
                    <Feather
                      name="calendar"
                      color={COLORS.primaryBlue}
                      size={16}
                      style={{ marginRight: 6 }}
                    />

                    <Text style={styles.timeText}>
                      {formatActivityDate(item.activity_date)}
                    </Text>
                  </View>

                  <Feather
                    name="chevron-right"
                    color="#94A3B8"
                    size={20}
                  />
                </View>

                <Text style={styles.activityTitle}>
                  {item.title}
                </Text>

                {!!item.description && (
                  <Text
                    style={styles.activityDesc}
                    numberOfLines={2}
                  >
                    {item.description}
                  </Text>
                )}

                {!!item.target_area && (
                  <View
                    style={{
                      flexDirection: 'row',
                      alignItems: 'center',
                      marginTop: 8,
                    }}
                  >
                    <Feather
                      name="map-pin"
                      size={14}
                      color={COLORS.primaryBlue}
                    />

                    <Text
                      style={[
                        styles.activityDesc,
                        { marginLeft: 5 },
                      ]}
                    >
                      {item.target_area}
                    </Text>
                  </View>
                )}

                <Text
                  style={{
                    color: COLORS.primaryBlue,
                    fontSize: 12,
                    fontWeight: '600',
                    marginTop: 12,
                  }}
                >
                  Tap to view details
                </Text>

              </TouchableOpacity>
            ))
          )}

          <View style={{ height: 100 }} />

        </ScrollView>
      </Animated.View>

      {/* ========================================
          BOTTOM NAVIGATION
      ======================================== */}

      <BottomNavbar activeTab="home" />

      {/* ========================================
          NOTIFICATIONS MODAL
      ======================================== */}

      <Modal
        visible={notificationsVisible}
        transparent
        animationType="slide"
        onRequestClose={() => setNotificationsVisible(false)}
      >
        <View
          style={{
            flex: 1,
            backgroundColor: 'rgba(0,0,0,0.4)',
            justifyContent: 'flex-end',
          }}
        >
          <View
            style={{
              backgroundColor: COLORS.background,
              borderTopLeftRadius: 24,
              borderTopRightRadius: 24,
              padding: 20,
              maxHeight: '75%',
              minHeight: 200,
            }}
          >

            {/* Modal Header */}

            <View
              style={{
                flexDirection: 'row',
                justifyContent: 'space-between',
                alignItems: 'center',
                marginBottom: 16,
              }}
            >
              <Text style={styles.sectionTitleDark}>
                Notifications
              </Text>

              <TouchableOpacity
                accessibilityLabel="Close notifications"
                onPress={() => setNotificationsVisible(false)}
              >
                <Feather
                  name="x"
                  size={24}
                  color={COLORS.primaryBlue}
                />
              </TouchableOpacity>
            </View>

            {/* Loading */}

            {notificationLoading ? (

              <Text style={styles.activityDesc}>
                Loading notifications...
              </Text>

            ) : notificationError ? (

              /* Error */

              <View style={styles.activityCard}>
                <Text style={styles.activityDesc}>
                  {notificationError}
                </Text>

                <TouchableOpacity onPress={fetchNotifications}>
                  <Text style={styles.seeAllText}>
                    Try again
                  </Text>
                </TouchableOpacity>
              </View>

            ) : notifications.length === 0 ? (

              /* Empty state */

              <View style={styles.activityCard}>
                <Feather
                  name="bell"
                  size={26}
                  color={COLORS.primaryBlue}
                />

                <Text style={styles.activityTitle}>
                  No notifications yet
                </Text>

                <Text style={styles.activityDesc}>
                  New community activity announcements will appear
                  here when published.
                </Text>
              </View>

            ) : (

              /* Notification List */

              <ScrollView
                showsVerticalScrollIndicator={false}
                contentContainerStyle={{ paddingBottom: 20 }}
              >
                {notifications.map((item) => {
                  const isRead = readNotificationIds.includes(
                    item.notification_id
                  );

                  return (
                    <TouchableOpacity
                      key={item.notification_id}
                      activeOpacity={0.75}
                      onPress={() => handleNotificationPress(item)}
                      style={[
                        styles.activityCard,
                        !isRead && {
                          borderLeftWidth: 4,
                          borderLeftColor: COLORS.primaryBlue,
                        },
                      ]}
                    >

                      {/* Notification Header */}

                      <View
                        style={{
                          flexDirection: 'row',
                          alignItems: 'center',
                        }}
                      >
                        <Feather
                          name="bell"
                          size={18}
                          color={COLORS.primaryBlue}
                        />

                        <Text
                          style={[
                            styles.activityTitle,
                            {
                              marginLeft: 8,
                              flex: 1,
                            },
                          ]}
                        >
                          {item.title}
                        </Text>

                        {!isRead && (
                          <View
                            style={{
                              width: 8,
                              height: 8,
                              borderRadius: 4,
                              backgroundColor: COLORS.danger,
                            }}
                          />
                        )}
                      </View>

                      {/* Notification Message */}

                      <Text style={styles.activityDesc}>
                        {item.message}
                      </Text>

                      {/* Notification Date */}

                      <Text
                        style={[
                          styles.activityDesc,
                          {
                            marginTop: 6,
                            fontSize: 12,
                          },
                        ]}
                      >
                        {new Date(item.created_at).toLocaleString()}
                      </Text>

                      {/* View Activity */}

                      <View
                        style={{
                          flexDirection: 'row',
                          alignItems: 'center',
                          marginTop: 12,
                        }}
                      >
                        <Text
                          style={[
                            styles.seeAllText,
                            {
                              fontWeight: 'bold',
                            },
                          ]}
                        >
                          View Activity
                        </Text>

                        <Feather
                          name="arrow-right"
                          size={15}
                          color={COLORS.primaryBlue}
                          style={{ marginLeft: 5 }}
                        />
                      </View>

                    </TouchableOpacity>
                  );
                })}
              </ScrollView>
            )}

          </View>
        </View>
      </Modal>

      {/* ========================================
          EXPANDED ACTIVITY DETAILS MODAL
      ======================================== */}

      <Modal
        visible={activityModalVisible}
        transparent
        animationType="slide"
        onRequestClose={closeActivity}
      >
        <View
          style={{
            flex: 1,
            backgroundColor: 'rgba(0,0,0,0.45)',
            justifyContent: 'flex-end',
          }}
        >
          <View
            style={{
              backgroundColor: COLORS.background,
              borderTopLeftRadius: 28,
              borderTopRightRadius: 28,
              maxHeight: '85%',
              overflow: 'hidden',
            }}
          >

            {/* Modal Content */}

            <ScrollView
              showsVerticalScrollIndicator={false}
              contentContainerStyle={{
                padding: 24,
                paddingBottom: 35,
              }}
            >

              {/* Modal Header */}

              <View
                style={{
                  flexDirection: 'row',
                  justifyContent: 'space-between',
                  alignItems: 'center',
                  marginBottom: 24,
                }}
              >
                <View
                  style={{
                    flexDirection: 'row',
                    alignItems: 'center',
                  }}
                >
                  <Feather
                    name="calendar"
                    size={20}
                    color={COLORS.primaryBlue}
                  />

                  <Text
                    style={{
                      marginLeft: 8,
                      fontSize: 15,
                      fontWeight: 'bold',
                      color: COLORS.primaryBlue,
                      letterSpacing: 0.5,
                    }}
                  >
                    COMMUNITY ACTIVITY
                  </Text>
                </View>

                <TouchableOpacity
                  accessibilityLabel="Close activity details"
                  onPress={closeActivity}
                  style={{
                    padding: 5,
                  }}
                >
                  <Feather
                    name="x"
                    size={24}
                    color="#64748B"
                  />
                </TouchableOpacity>
              </View>

              {selectedActivity && (
                <>


                  {/* Activity Date */}

                  <View
                    style={{
                      backgroundColor: '#E8F4FF',
                      paddingHorizontal: 12,
                      paddingVertical: 8,
                      borderRadius: 10,
                      alignSelf: 'flex-start',
                      marginBottom: 16,
                    }}
                  >
                    <Text
                      style={{
                        color: COLORS.primaryBlue,
                        fontSize: 13,
                        fontWeight: '600',
                      }}
                    >
                      {formatActivityDate(
                        selectedActivity.activity_date
                      )}
                    </Text>
                  </View>

                  {/* Activity Title */}

                  <Text
                    style={{
                      fontSize: 23,
                      fontWeight: 'bold',
                      color: '#1E293B',
                      marginBottom: 22,
                      lineHeight: 31,
                    }}
                  >
                    {selectedActivity.title}
                  </Text>

                  {/* Divider */}

                  <View
                    style={{
                      height: 1,
                      backgroundColor: '#E2E8F0',
                      marginBottom: 22,
                    }}
                  />

                  {/* Description */}

                  <Text
                    style={{
                      fontSize: 13,
                      fontWeight: 'bold',
                      color: '#64748B',
                      marginBottom: 10,
                      letterSpacing: 0.5,
                    }}
                  >
                    ABOUT THIS ACTIVITY
                  </Text>

                  <Text
                    style={{
                      fontSize: 15,
                      lineHeight: 25,
                      color: '#475569',
                      marginBottom: 25,
                    }}
                  >
                    {selectedActivity.description ||
                      'No additional description is available for this activity.'}
                  </Text>

                  {/* Target Area */}

                  <View
                    style={{
                      backgroundColor: '#F1F5F9',
                      borderRadius: 14,
                      padding: 16,
                      flexDirection: 'row',
                      alignItems: 'center',
                      marginBottom: 25,
                    }}
                  >
                    <View
                      style={{
                        width: 42,
                        height: 42,
                        borderRadius: 12,
                        backgroundColor: '#E0F0FF',
                        alignItems: 'center',
                        justifyContent: 'center',
                      }}
                    >
                      <Feather
                        name="map-pin"
                        size={21}
                        color={COLORS.primaryBlue}
                      />
                    </View>

                    <View
                      style={{
                        marginLeft: 12,
                        flex: 1,
                      }}
                    >
                      <Text
                        style={{
                          fontSize: 12,
                          color: '#64748B',
                          marginBottom: 5,
                          fontWeight: '600',
                        }}
                      >
                        TARGET AREA
                      </Text>

                      <Text
                        style={{
                          fontSize: 15,
                          fontWeight: '600',
                          color: '#1E293B',
                        }}
                      >
                        {selectedActivity.target_area ||
                          'Not specified'}
                      </Text>
                    </View>
                  </View>

                  {/* Close Button */}

                  <TouchableOpacity
                    activeOpacity={0.8}
                    onPress={closeActivity}
                    style={{
                      backgroundColor: COLORS.primaryBlue,
                      paddingVertical: 15,
                      borderRadius: 12,
                      alignItems: 'center',
                    }}
                  >
                    <Text
                      style={{
                        color: COLORS.white,
                        fontSize: 15,
                        fontWeight: 'bold',
                      }}
                    >
                      Close
                    </Text>
                  </TouchableOpacity>

                </>
              )}

            </ScrollView>
          </View>
        </View>
      </Modal>

      {/* ========================================
          SOS CONFIRMATION MODAL
      ======================================== */}

      <Modal
        visible={isSosExpanded}
        transparent
        animationType="fade"
        onRequestClose={cancelSos}
      >
        <View style={styles.sosModalOverlay}>
          <View style={styles.sosModalContent}>

            <Feather
              name="alert-triangle"
              color={COLORS.danger}
              size={48}
              style={{ marginBottom: 16 }}
            />

            <Text style={styles.sosModalTitle}>
              EMERGENCY SOS
            </Text>

            <Text style={styles.sosModalDesc}>
              Are you sure you need emergency assistance?
            </Text>

            <Text style={styles.holdInstruction}>
              Press and hold for 5 seconds
            </Text>

            <TouchableOpacity
              activeOpacity={0.8}
              onPressIn={handlePressIn}
              onPressOut={handlePressOut}
              style={styles.holdButton}
            >
              <Text style={styles.holdButtonText}>
                {holdProgress}
              </Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={styles.cancelSosBtn}
              onPress={cancelSos}
            >
              <Text style={styles.cancelSosText}>
                Cancel
              </Text>
            </TouchableOpacity>

          </View>
        </View>
      </Modal>

    </SafeAreaView>
  );
}
