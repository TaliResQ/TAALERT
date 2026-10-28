import { Feather } from '@expo/vector-icons';
import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useRef } from 'react';
import {
  Animated,
  SafeAreaView,
  ScrollView,
  Text,
  TouchableOpacity,
  View
} from 'react-native';
import BottomNavbar from '../components/BottomNavbar';
import { ResourceStyles as styles } from '../constants/theme';

export default function ResourcesScreen() {
  const router = useRouter();
  const fadeAnim = useRef(new Animated.Value(0)).current;
  const slideUpAnim = useRef(new Animated.Value(30)).current;

  useFocusEffect(
    useCallback(() => {
      fadeAnim.setValue(0);
      slideUpAnim.setValue(30);

      Animated.parallel([
        Animated.timing(fadeAnim, { toValue: 1, duration: 600, useNativeDriver: true }),
        Animated.spring(slideUpAnim, { toValue: 0, friction: 6, useNativeDriver: true }),
      ]).start();

      return () => {
      };
    }, [fadeAnim, slideUpAnim]) 
  );

  return (
    <SafeAreaView style={styles.container}>
      {/* Header Area */}
      <View style={styles.header}>
        <View>
          <Text style={styles.headerSub}>Be prepared</Text>
          <Text style={styles.headerTitle}>Safety Info</Text>
        </View>
        <TouchableOpacity style={styles.topIconBtn}>
          <Feather name="book-open" size={20} color="#25A5FE" />
        </TouchableOpacity>
      </View>

      <Animated.View style={{ flex: 1, opacity: fadeAnim, transform: [{ translateY: slideUpAnim }] }}>
        <ScrollView contentContainerStyle={styles.scrollContent} showsVerticalScrollIndicator={false}>

          {/* Featured Card */}
          <View style={styles.featuredCard}>
            <Text style={styles.featuredLabel}>FEATURED</Text>
            <Text style={styles.featuredTitle}>Understanding Taal's 5 Alert Levels</Text>
            <Text style={styles.featuredSub}>5 min read · PHIVOLCS Official</Text>
            <TouchableOpacity style={styles.readBtn}>
              <Text style={styles.readBtnText}>Read guide</Text>
            </TouchableOpacity>
          </View>

          {/* Resource List */}

{/* Recovery Assistance Card */}
<TouchableOpacity
  activeOpacity={0.85}
  onPress={() => router.push('/recovery-assistance')}
  style={styles.recoveryCard}
>
  <View style={styles.recoveryCardHeader}>
    <View style={styles.recoveryIconBox}>
      <Feather name="life-buoy" size={25} color="#2563EB" />
    </View>

    <View style={styles.recoveryCardText}>
      <Text style={styles.recoveryCardTitle}>
        Recovery Assistance
      </Text>

      <Text style={styles.recoveryCardDescription}>
        Find help, request assistance, and explore recovery resources.
      </Text>
    </View>

    <Feather name="chevron-right" size={22} color="#2563EB" />
  </View>

  <View style={styles.recoveryTags}>
    <View style={styles.recoveryTag}>
      <Text style={styles.recoveryTagText}>Find Assistance</Text>
    </View>

    <View style={styles.recoveryTag}>
      <Text style={styles.recoveryTagText}>Request Help</Text>
    </View>

    <View style={styles.recoveryTag}>
      <Text style={styles.recoveryTagText}>Recovery Resources</Text>
    </View>
  </View>
</TouchableOpacity>

          <View style={styles.listContainer}>
            <ResourceItem
              title="Before an eruption"
              desc="Know the warning signs and alert levels."
              icon="alert-triangle"
              iconColor="#D97706"
              iconBg="#FEF3C7"
              onPress={() => router.push('/EruptionGuide')}
            />
            <ResourceItem
              title="Frequently Asked Questions"
              desc="Find answers to common questions about volcanic eruptions."
              icon="help-circle"
              iconColor="#7C3AED"
              iconBg="#d9c6fa"
              onPress={() => router.push('/FAQs')}
            />
            <ResourceItem
              title="Emergency Go-Bag"
              desc="12 essentials every household needs."
              icon="briefcase"
              iconColor="#2563EB"
              iconBg="#DBEAFE"
            />
            <ResourceItem
              title="Evacuation Plan"
              desc="Plan routes for your family."
              icon="home"
              iconColor="#059669"
              iconBg="#D1FAE5"
            />
            <ResourceItem
              title="First Aid Basics"
              desc="Treat ash exposure & burns."
              icon="heart"
              iconColor="#DC2626"
              iconBg="#FEE2E2"
            />
          </View>

          <View style={{ height: 100 }} />
        </ScrollView>
      </Animated.View>

      <BottomNavbar activeTab="resources" />
    </SafeAreaView>
  );
}

// List Items
const ResourceItem = ({ title, desc, icon, iconColor, iconBg, onPress }: any) => (
  <TouchableOpacity style={styles.card} onPress={onPress}>
    <View style={[styles.iconBox, { backgroundColor: iconBg }]}>
      <Feather name={icon} size={20} color={iconColor} />
    </View>
    <View style={styles.cardTextContainer}>
      <Text style={styles.cardTitle}>{title}</Text>
      <Text style={styles.cardDesc}>{desc}</Text>
    </View>
    <Feather name="chevron-right" size={18} color="#94A3B8" />
  </TouchableOpacity>
);