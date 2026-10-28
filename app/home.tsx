import { supabase } from '@/src/services/supabase';
import { Feather } from '@expo/vector-icons';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
  Alert,
  Animated,
  Modal,
  SafeAreaView,
  ScrollView,
  StatusBar,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';

import BottomNavbar from '../components/BottomNavbar';
import { COLORS, IndexStyles as styles } from '../constants/theme';

// TYPES

type CommunityActivity = {
  id: string;
  title: string;
  activity_date: string;
  description: string | null;
  target_area: string;
};

type ActivityNotification = {
  id: string;
  activity_id: string;
  title: string;
  message: string;
  created_at: string;
};

// DATE FORMATTER

const formatActivityDate = (dateString: string) => {
  const [year, month, day] = dateString.split('-').map(Number);

  const date = new Date(year, month - 1, day);

  return date.toLocaleDateString('en-US', {
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  });
};

// MAIN SCREEN

export default function DisasterAppUI() {
  // Animations
  const fadeAnim = useRef(new Animated.Value(0)).current;
  const slideUpAnim = useRef(new Animated.Value(30)).current;

  // User
  const [userName, setUserName] = useState('User');

  // Community activities
  const [activities, setActivities] = useState<CommunityActivity[]>([]);
  const [activitiesLoading, setActivitiesLoading] = useState(true);
  const [activitiesError, setActivitiesError] = useState<string | null>(
    null
  );

  // Notifications
  const [notifications, setNotifications] = useState<
    ActivityNotification[]
  >([]);
  const [readNotificationIds, setReadNotificationIds] = useState<string[]>(
    []
  );
  const [notificationsVisible, setNotificationsVisible] = useState(false);
  const [notificationLoading, setNotificationLoading] = useState(false);
  const [notificationError, setNotificationError] = useState<string | null>(
    null
  );

  // SOS
  const [isSosExpanded, setIsSosExpanded] = useState(false);
  const [holdProgress, setHoldProgress] = useState(5);
  const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);

  // Number of unread notifications
  const unreadCount = notifications.filter(
    (item) => !readNotificationIds.includes(item.id)
  ).length;

  // FETCH USER INFORMATION

  const fetchUser = useCallback(async () => {
    const {
      data: { user },
      error,
    } = await supabase.auth.getUser();

    if (error) {
      console.error('Error fetching user:', error.message);
      return;
    }

    if (!user) {
      setUserName('User');
      return;
    }

    setUserName(
      user.user_metadata?.full_name ||
        user.user_metadata?.name ||
        user.email?.split('@')[0] ||
        'User'
    );
  }, []);

  // FETCH COMMUNITY ACTIVITIES

  const fetchActivities = useCallback(async () => {
    setActivitiesLoading(true);
    setActivitiesError(null);

    try {
      const { data, error } = await supabase
        .from('community_activities')
        .select(
          'id, title, activity_date, description, target_area'
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

  // FETCH NOTIFICATIONS

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
          .select('id, activity_id, title, message, created_at')
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

  // INITIAL SCREEN SETUP

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

  // MARK NOTIFICATION AS READ

  const markNotificationAsRead = async (
    notificationId: string
  ): Promise<boolean> => {
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

    // Already read on this device/session
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
      console.error('Error marking notification as read:', error.message);

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
  };

  // SOS BUTTON LOGIC

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

  // RENDER SCREEN

  return (
    <SafeAreaView style={styles.container}>
      <Animated.View
        style={{
          flex: 1,
          opacity: fadeAnim,
          transform: [{ translateY: slideUpAnim }],
        }}
      >
        <StatusBar
          barStyle="dark-content"
          backgroundColor={COLORS.background}
        />

        <ScrollView
          contentContainerStyle={styles.scrollContent}
          showsVerticalScrollIndicator={false}
        >
          {/* HEADER */}

          <View style={styles.header}>
            <View>
              <Text style={styles.greetingText}>Hello,</Text>
              <Text style={styles.nameText}>{userName} 👋</Text>
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

          {/* VOLCANO STATUS CARD */}

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

            <Text style={styles.volcanoName}>Taal Volcano</Text>

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
                <Text style={styles.statusBadgeText}>SW wind</Text>
              </View>
            </View>
          </View>

          {/* QUICK ACTIONS */}

          <Text style={styles.sectionTitle}>QUICK ACTIONS</Text>

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
                  Emergency{'\n'}SOS
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
                onPress={() =>
                  Alert.alert(
                    'Emergency Hotlines',
                    'Emergency number: 911\n\nPlease verify your local emergency contacts before relying on this app.'
                  )
                }
              >
                <View style={styles.actionIconContainer}>
                  <Feather
                    name="phone"
                    color={COLORS.primaryBlue}
                    size={20}
                  />
                </View>

                <Text style={styles.actionTitle}>Hotlines</Text>
                <Text style={styles.actionSub}>911 · PHIVOLCS</Text>
              </TouchableOpacity>

              {/* Shelters */}
              <TouchableOpacity
                style={styles.actionCard}
                onPress={() =>
                  Alert.alert(
                    'Evacuation Shelters',
                    'Connect this button to your evacuation center screen to view nearby shelters.'
                  )
                }
              >
                <View style={styles.actionIconContainer}>
                  <Feather
                    name="plus-square"
                    color={COLORS.primaryBlue}
                    size={20}
                  />
                </View>

                <Text style={styles.actionTitle}>Shelters</Text>
                <Text style={styles.actionSub}>View nearby centers</Text>
              </TouchableOpacity>
            </View>
          </View>

          {/* RECENT ACTIVITIES */}

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
                <Text style={styles.seeAllText}>Try again</Text>
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
                <Text style={styles.seeAllText}>Refresh</Text>
              </TouchableOpacity>
            </View>
          ) : (
            /* Activity cards */
            activities.map((item) => (
              <View key={item.id} style={styles.activityCard}>
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
                </View>

                <Text style={styles.activityTitle}>
                  {item.title}
                </Text>

                {!!item.description && (
                  <Text style={styles.activityDesc}>
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
              </View>
            ))
          )}

          <View style={{ height: 100 }} />
        </ScrollView>
      </Animated.View>

      {/* BOTTOM NAVIGATION */}

      <BottomNavbar activeTab="home" />

      {/* NOTIFICATIONS MODAL */}

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
            {/* Modal header */}
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
                  <Text style={styles.seeAllText}>Try again</Text>
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
              /* Notification list */
              <ScrollView
                showsVerticalScrollIndicator={false}
                contentContainerStyle={{ paddingBottom: 20 }}
              >
                {notifications.map((item) => {
                  const isRead = readNotificationIds.includes(item.id);

                  return (
                    <View
                      key={item.id}
                      style={[
                        styles.activityCard,
                        !isRead && {
                          borderLeftWidth: 4,
                          borderLeftColor: COLORS.primaryBlue,
                        },
                      ]}
                    >
                      {/* Notification title */}
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
                            { marginLeft: 8, flex: 1 },
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

                      {/* Notification message */}
                      <Text style={styles.activityDesc}>
                        {item.message}
                      </Text>

                      {/* Notification date */}
                      <Text
                        style={[
                          styles.activityDesc,
                          { marginTop: 6, fontSize: 12 },
                        ]}
                      >
                        {new Date(item.created_at).toLocaleString()}
                      </Text>

                      {/* View activity */}
                      <TouchableOpacity
                        onPress={async () => {
                          const markedRead =
                            await markNotificationAsRead(item.id);

                          if (!markedRead) {
                            return;
                          }

                          setNotificationsVisible(false);

                          Alert.alert(
                            'Community Activity',
                            'Check Recent Activities on your home screen for the published activity.',
                            [{ text: 'OK' }]
                          );
                        }}
                      >
                        <Text
                          style={[
                            styles.seeAllText,
                            {
                              marginTop: 12,
                              fontWeight: 'bold',
                            },
                          ]}
                        >
                          View Activity →
                        </Text>
                      </TouchableOpacity>
                    </View>
                  );
                })}
              </ScrollView>
            )}
          </View>
        </View>
      </Modal>

      {/* SOS CONFIRMATION MODAL */}

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
              <Text style={styles.cancelSosText}>Cancel</Text>
            </TouchableOpacity>
          </View>
        </View>
      </Modal>
    </SafeAreaView>
  );
}