
import { supabase } from '@/src/services/supabase';
import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
  Animated,
  RefreshControl,
  SafeAreaView,
  ScrollView,
  Text,
  TouchableOpacity,
  View
} from 'react-native';

import BottomNavbar from '../components/BottomNavbar';
import { ProfileStyles as styles } from '../constants/theme';

export default function ProfileScreen() {
  const fadeAnim = useRef(new Animated.Value(0)).current;
  const slideUpAnim = useRef(new Animated.Value(30)).current;

  // Refresh state
  const [refreshing, setRefreshing] = useState(false);

  // Dynamic user data
  const [userName, setUserName] = useState('Loading...');
  const [barangay, setBarangay] = useState('Loading...');
  const [verificationStatus, setVerificationStatus] = useState('PENDING');
  const [reportCount, setReportCount] = useState(0);

  // Fetch user profile and report count
  const fetchUserData = useCallback(async () => {
    try {
      // 1. Get authenticated user
      const {
        data: { user },
        error: authError
      } = await supabase.auth.getUser();

      if (authError || !user) {
        console.error('Authentication error:', authError?.message);
        return;
      }

      // 2. Fetch user profile
      const { data: profile, error: profileError } = await supabase
        .from('users')
        .select('fullname, barangay, verification_status')
        .eq('id', user.id)
        .single();

      if (!profileError && profile) {
        setUserName(profile.fullname || 'User');
        setBarangay(profile.barangay || 'Update your address');

        setVerificationStatus(
          profile.verification_status?.toUpperCase() || 'PENDING'
        );
      } else {
        console.error('Profile fetch error:', profileError?.message);

        setUserName(
          user.user_metadata?.full_name ||
          user.user_metadata?.name ||
          'User'
        );

        setBarangay('No barangay set');
      }

      // 3. Fetch total submitted reports
      const { count, error: countError } = await supabase
        .from('incidents')
        .select('*', { count: 'exact', head: true })
        .eq('user_id', user.id);

      if (!countError) {
        setReportCount(count || 0);
      } else {
        console.error('Report count error:', countError.message);
      }

    } catch (error) {
      console.error('Error fetching profile data:', error);
    }
  }, []);

  // Pull-down-to-refresh function
  const onRefresh = async () => {
    setRefreshing(true);

    try {
      await fetchUserData();
    } finally {
      setRefreshing(false);
    }
  };

  // Initial load and animations
  useEffect(() => {
    Animated.parallel([
      Animated.timing(fadeAnim, {
        toValue: 1,
        duration: 600,
        useNativeDriver: true
      }),

      Animated.spring(slideUpAnim, {
        toValue: 0,
        friction: 6,
        useNativeDriver: true
      }),
    ]).start();

    fetchUserData();
  }, [fetchUserData, fadeAnim, slideUpAnim]);

  return (
    <SafeAreaView style={styles.container}>

      {/* Header */}
      <View style={styles.header}>
        <Text style={styles.headerTitle}>Profile</Text>

        <TouchableOpacity
          style={styles.settingsBtn}
          onPress={() => router.push('/edit-profile')}
        >
          <Feather
            name="settings"
            size={20}
            color="#1E293B"
          />
        </TouchableOpacity>
      </View>

      <Animated.View
        style={{
          flex: 1,
          opacity: fadeAnim,
          transform: [{ translateY: slideUpAnim }]
        }}
      >

        <ScrollView
          contentContainerStyle={styles.scrollContent}
          showsVerticalScrollIndicator={false}

          // Pull down to refresh
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={onRefresh}
              colors={['#25A5FE']}
              tintColor="#25A5FE"
              title="Refreshing profile..."
              titleColor="#64748B"
            />
          }
        >

          {/* Main Profile Card */}
          <View style={styles.profileCard}>

            <View style={styles.avatar}>
              <Text style={styles.avatarText}>
                {userName !== 'Loading...'
                  ? userName.charAt(0).toUpperCase()
                  : ''}
              </Text>
            </View>

            <View style={styles.profileInfo}>

              <Text style={styles.userName}>
                {userName}
              </Text>

              <View style={styles.locationRow}>
                <Feather
                  name="map-pin"
                  size={14}
                  color="#64748B"
                />

                <Text style={styles.locationText}>
                  {barangay !== 'Update your address' &&
                   barangay !== 'Loading...' &&
                   barangay !== 'No barangay set'
                    ? `Brgy. ${barangay}, Talisay, Batangas`
                    : barangay}
                </Text>
              </View>

              {/* Verification Badge */}
              <View
                style={[
                  styles.badge,
                  verificationStatus === 'APPROVED' && {
                    backgroundColor: '#DCFCE7'
                  }
                ]}
              >
                <Text
                  style={[
                    styles.badgeText,
                    verificationStatus === 'APPROVED' && {
                      color: '#16A34A'
                    }
                  ]}
                >
                  {verificationStatus} RESIDENT
                </Text>
              </View>

            </View>
          </View>

          {/* Stats Row */}
          <View style={styles.statsRow}>
            <StatCard
              value={reportCount}
              label="REPORTS"
            />

            <StatCard
              value="0"
              label="ALERTS"
            />

            <StatCard
              value="0"
              label="SAFE DAYS"
            />
          </View>

          {/* Menu List */}
          <View style={styles.menuContainer}>

            <MenuItem
              icon="file-text"
              label="My Reports"
              sub={`View and track your ${reportCount} submitted reports`}
              color="#25A5FE"
              bg="#E0F2FE"
              onPress={() => router.push('/my-reports')}
            />

            <MenuItem
              icon="bell"
              label="Notification preferences"
              color="#25A5FE"
              bg="#E0F2FE"
              onPress={() => {}}
            />

            <MenuItem
              icon="shield"
              label="Emergency contacts"
              color="#25A5FE"
              bg="#E0F2FE"
              onPress={() => {}}
            />

            <MenuItem
              icon="log-out"
              label="Sign out"
              color="#EF4444"
              bg="#FEE2E2"
              isLast
              onPress={async () => {
                await supabase.auth.signOut();
                router.replace('/');
              }}
            />

          </View>

          {/* Bottom Spacing */}
          <View style={{ height: 100 }} />

        </ScrollView>
      </Animated.View>

      {/* Bottom Navigation */}
      <BottomNavbar activeTab="profile" />

    </SafeAreaView>
  );
}

// ========================================
// STAT CARD COMPONENT
// ========================================
const StatCard = ({ value, label }: any) => (
  <View style={styles.statCard}>
    <Text style={styles.statValue}>
      {value}
    </Text>

    <Text style={styles.statLabel}>
      {label}
    </Text>
  </View>
);

// ========================================
// MENU ITEM COMPONENT
// ========================================
const MenuItem = ({
  icon,
  label,
  sub,
  color,
  bg,
  isLast,
  onPress
}: any) => (
  <TouchableOpacity
    style={[
      styles.menuItem,
      isLast && { borderBottomWidth: 0 }
    ]}
    onPress={onPress}
  >

    <View
      style={[
        styles.menuIconBox,
        { backgroundColor: bg }
      ]}
    >
      <Feather
        name={icon}
        size={18}
        color={color}
      />
    </View>

    <View style={styles.menuTextContainer}>
      <Text
        style={[
          styles.menuLabel,
          icon === 'log-out' && { color: '#EF4444' }
        ]}
      >
        {label}
      </Text>

      {sub && (
        <Text style={styles.menuSubText}>
          {sub}
        </Text>
      )}
    </View>

    <Feather
      name="chevron-right"
      size={18}
      color="#CBD5E1"
    />

  </TouchableOpacity>
);
