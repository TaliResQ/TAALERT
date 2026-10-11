import { supabase } from '@/src/services/supabase';
import { Feather } from '@expo/vector-icons';
import { useEffect, useRef, useState } from 'react';
import {
  Alert,
  Animated,
  Modal,
  SafeAreaView,
  ScrollView,
  StatusBar,
  Text,
  TouchableOpacity,
  View
} from 'react-native';
import BottomNavbar from '../components/BottomNavbar';
import { COLORS, IndexStyles as styles } from '../constants/theme';


type CommunityActivity = {
  id: string;
  title: string;
  activity_date: string;
  description: string | null;
  target_area: string;
};

const formatActivityDate = (dateString: string) => {
  const [year, month, day] = dateString.split('-').map(Number);

  const date = new Date(year, month - 1, day);

  return date.toLocaleDateString('en-US', {
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  });
};


export default function DisasterAppUI() {
  const fadeAnim = useRef(new Animated.Value(0)).current;
  const slideUpAnim = useRef(new Animated.Value(30)).current;
  const [userName, setUserName] = useState('User');

  // SOS State
  const [isSosExpanded, setIsSosExpanded] = useState(false);
  const [holdProgress, setHoldProgress] = useState(5);
  const timerRef = useRef<any>(null);

  const [activities, setActivities] = useState<CommunityActivity[]>([]);
  const [activitiesLoading, setActivitiesLoading] = useState(true);
  const [activitiesError, setActivitiesError] = useState<string | null>(null);

  useEffect(() => {
    Animated.parallel([
      Animated.timing(fadeAnim, { toValue: 1, duration: 600, useNativeDriver: true }),
      Animated.spring(slideUpAnim, { toValue: 0, friction: 6, useNativeDriver: true }),
    ]).start();

    const getUser = async () => {
      const {
        data: { user },
        error,
      } = await supabase.auth.getUser();

      if (error || !user) return;

      setUserName(
        user.user_metadata?.full_name ||
        user.user_metadata?.name ||
        user.email?.split('@')[0] ||
        'User'
      );
    };

    getUser();

    // Cleanup timer on unmount
    return () => {
      if (timerRef.current) clearInterval(timerRef.current);
    };
  }, []);


  useEffect(() => {
    let isMounted = true;

    const fetchActivities = async () => {
      setActivitiesLoading(true);
      setActivitiesError(null);

      const { data, error } = await supabase
        .from('community_activities')
        .select('id, title, activity_date, description, target_area')
        .eq('is_published', true)
        .order('activity_date', { ascending: true })
        .limit(10);

      if (error) {
        console.error('Error fetching activities:', error.message);
      } else {
        setActivities(data ?? []);
      }

      setActivitiesLoading(false);
    };

    fetchActivities();

    return () => {
      isMounted = false;
    };
  }, []);


  // --- SOS Button Logic ---
  const handlePressIn = () => {
    let count = 5;
    setHoldProgress(count);
    if (timerRef.current) {
      clearInterval(timerRef.current);
    }

    timerRef.current = setInterval(() => {
      count -= 1;
      if (count > 0) {
        setHoldProgress(count);
      } else {
        if (timerRef.current) {
          clearInterval(timerRef.current);
        }
        triggerSos();
      }
    }, 1000);
  };

  const handlePressOut = () => {
    if (timerRef.current) {
      clearInterval(timerRef.current);
    }
    // Reset back to 5 if they let go early
    setHoldProgress(5);
  };

  const triggerSos = () => {
    setIsSosExpanded(false);
    setHoldProgress(5);
    Alert.alert(
      "SOS Sent!",
      "Emergency responders have been alerted to your location.",
      [{ text: "OK" }]
    );
  };

  const cancelSos = () => {
    if (timerRef.current) clearInterval(timerRef.current);
    setIsSosExpanded(false);
    setHoldProgress(5);
  };

  return (
    <SafeAreaView style={styles.container}>
      <Animated.View style={{ flex: 1, opacity: fadeAnim, transform: [{ translateY: slideUpAnim }] }}>
        <StatusBar barStyle="dark-content" backgroundColor={COLORS.background} />

        <ScrollView
          contentContainerStyle={styles.scrollContent}
          showsVerticalScrollIndicator={false}
        >
          {/* --- Header --- */}
          <View style={styles.header}>
            <View>
              <Text style={styles.greetingText}>Hello,</Text>
              <Text style={styles.nameText}>{userName} 👋</Text>
            </View>
            <TouchableOpacity style={styles.notificationBtn}>
              <Feather name="bell" color={COLORS.primaryBlue} size={24} />
              <View style={styles.notificationDot} />
            </TouchableOpacity>
          </View>

          {/* --- Status Card --- */}
          <View style={styles.statusCard}>
            <View style={styles.statusHeader}>
              <Feather name="shield" color={COLORS.white} size={16} />
              <Text style={styles.statusHeaderText}>CURRENT STATUS</Text>
            </View>
            <Text style={styles.volcanoName}>Taal Volcano</Text>
            <Text style={styles.alertLevel}>Alert Level 1 · Normal conditions</Text>

            <View style={styles.statusBadgesRow}>
              <View style={styles.statusBadge}>
                <Text style={styles.statusBadgeText}>Updated 2 min ago</Text>
              </View>
              <View style={styles.statusBadge}>
                <Feather name="wind" color={COLORS.white} size={14} style={{ marginRight: 4 }} />
                <Text style={styles.statusBadgeText}>SW wind</Text>
              </View>
            </View>
          </View>

          {/* --- Quick Actions --- */}
          <Text style={styles.sectionTitle}>QUICK ACTIONS</Text>
          <View style={styles.quickActionsContainer}>
            {/* SOS Trigger Button */}
            <TouchableOpacity
              style={styles.sosCard}
              onPress={() => setIsSosExpanded(true)}
            >
              <View style={styles.sosIconContainer}>
                <Feather name="alert-circle" color={COLORS.white} size={28} />
              </View>
              <View>
                <Text style={styles.sosTitle}>Emergency{'\n'}SOS</Text>
                <Text style={styles.sosSub}>Press to alert responders</Text>
              </View>
            </TouchableOpacity>

            <View style={styles.rightActionsColumn}>
              <TouchableOpacity style={styles.actionCard}>
                <View style={styles.actionIconContainer}>
                  <Feather name="phone" color={COLORS.primaryBlue} size={20} />
                </View>
                <Text style={styles.actionTitle}>Hotlines</Text>
                <Text style={styles.actionSub}>911 · PHIVOLCS</Text>
              </TouchableOpacity>

              <TouchableOpacity style={styles.actionCard}>
                <View style={styles.actionIconContainer}>
                  <Feather name="plus-square" color={COLORS.primaryBlue} size={20} />
                </View>
                <Text style={styles.actionTitle}>Shelters</Text>
                <Text style={styles.actionSub}>3 nearby</Text>
              </TouchableOpacity>
            </View>
          </View>


          {/* --- Recent Activities --- */}
          <View style={styles.activityHeader}>
            <Text style={styles.sectionTitleDark}>
              Recent Activities
            </Text>
          </View>

          {activitiesLoading ? (
            <View style={styles.activityCard}>
              <Text style={styles.activityDesc}>
                Loading community activities...
              </Text>
            </View>
          ) : activitiesError ? (
            <View style={styles.activityCard}>
              <Text style={styles.activityDesc}>
                {activitiesError}
              </Text>
              <TouchableOpacity
                onPress={async () => {
                  setActivitiesLoading(true);
                  setActivitiesError(null);

                  const { data, error } = await supabase
                    .from('community_activities')
                    .select('id, title, activity_date, description, target_area')
                    .eq('is_published', true)
                    .order('activity_date', { ascending: true })
                    .limit(10);

                  if (error) {
                    setActivitiesError(
                      'Unable to load community activities.'
                    );
                  } else {
                    setActivities(data ?? []);
                  }

                  setActivitiesLoading(false);
                }}
              >
                <Text style={styles.seeAllText}>Try again</Text>
              </TouchableOpacity>
            </View>
          ) : activities.length === 0 ? (
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
            </View>
          ) : (
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
              </View>
            ))
          )}

          <View style={{ height: 100 }} />
        </ScrollView>
      </Animated.View>

      <BottomNavbar activeTab="home" />

      {/* --- Expanded SOS Confirmation Modal --- */}
      <Modal
        visible={isSosExpanded}
        transparent
        animationType="fade"
        onRequestClose={cancelSos}
      >
        <View style={styles.sosModalOverlay}>
          <View style={styles.sosModalContent}>
            <Feather name="alert-triangle" color={COLORS.danger} size={48} style={{ marginBottom: 16 }} />

            <Text style={styles.sosModalTitle}>EMERGENCY SOS</Text>
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
              <Text style={styles.holdButtonText}>{holdProgress}</Text>
            </TouchableOpacity>

            <TouchableOpacity style={styles.cancelSosBtn} onPress={cancelSos}>
              <Text style={styles.cancelSosText}>Cancel</Text>
            </TouchableOpacity>
          </View>
        </View>
      </Modal>

    </SafeAreaView>
  );
}