import { Feather } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import React, { useEffect, useRef } from 'react';
import {
  Animated,
  SafeAreaView,
  ScrollView,
  StatusBar,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { COLORS, WelcomeStyles as styles } from '../constants/theme';

export default function WelcomeScreen() {
  const router = useRouter();
  const fadeAnim = useRef(new Animated.Value(0)).current;
  const slideUpAnim = useRef(new Animated.Value(30)).current;

  useEffect(() => {
    Animated.parallel([
      Animated.timing(fadeAnim, { toValue: 1, duration: 800, useNativeDriver: true }),
      Animated.spring(slideUpAnim, { toValue: 0, friction: 8, useNativeDriver: true }),
    ]).start();
  }, []);

  return (
    <SafeAreaView style={styles.container}>
      <StatusBar barStyle="dark-content" backgroundColor={COLORS.background} />
      
      <Animated.View style={{ flex: 1, opacity: fadeAnim, transform: [{ translateY: slideUpAnim }] }}>
        <ScrollView contentContainerStyle={styles.scrollContent} showsVerticalScrollIndicator={false}>
          
          {/* --- Hero Branding Section --- */}
          <View style={styles.heroSection}>
            <View style={styles.logoContainer}>
              <Feather name="shield" color={COLORS.white} size={48} />
            </View>
            <Text style={styles.appName}>TaliResQ</Text>
            <Text style={styles.tagline}>Be Prepared. Stay Safe. Respond.</Text>
          </View>

          {/* --- Account & Authentication Section --- */}
          <View style={styles.authContainer}>
            <Text style={styles.unlockText}>
              Log in to unlock personalized features like <Text style={styles.highlightText}>Incident Reporting, Emergency SOS, Evacuation Routing,</Text> and <Text style={styles.highlightText}>Recovery Assistance.</Text>
            </Text>
            
            <View style={styles.buttonRow}>
              <TouchableOpacity 
                style={styles.loginBtn}
                onPress={() => router.push('/login')}
              >
                <Text style={styles.loginBtnText}>Log In</Text>
              </TouchableOpacity>
              
              <TouchableOpacity 
                style={styles.signUpBtn}
                onPress={() => router.push('/signup')}
              >
                <Text style={styles.signUpBtnText}>Sign Up</Text>
              </TouchableOpacity>
            </View>
          </View>

          {/* --- Public Resources Section (No Login Required) --- */}
          <View style={styles.publicResourcesHeader}>
            <Text style={styles.sectionTitle}>PUBLIC RESOURCES</Text>
            <Text style={styles.sectionSub}>Available without an account</Text>
          </View>

          <View style={styles.resourcesContainer}>
            
            <TouchableOpacity style={styles.resourceCard} onPress={() => navigation.navigate('CommunityPreparedness')}>
              <View style={[styles.resourceIconBox, { backgroundColor: '#E8F1FF' }]}>
                <Feather name="users" color={COLORS.primaryBlue} size={24} />
              </View>
              <View style={styles.resourceTextContent}>
                <Text style={styles.resourceTitle}>Community Preparedness</Text>
                <Text style={styles.resourceDesc}>Local guides, hazard maps, and readiness protocols.</Text>
              </View>
              <Feather name="chevron-right" color={COLORS.textMuted} size={20} />
            </TouchableOpacity>

            <TouchableOpacity style={styles.resourceCard} onPress={() => router.push('/FAQs')}>
              <View style={[styles.resourceIconBox, { backgroundColor: '#FFF4E5' }]}>
                <Feather name="info" color="#F59E0B" size={24} />
              </View>
              <View style={styles.resourceTextContent}>
                <Text style={styles.resourceTitle}>Emergency Info & FAQs</Text>
                <Text style={styles.resourceDesc}>Current disaster updates and frequently asked questions.</Text>
              </View>
              <Feather name="chevron-right" color={COLORS.textMuted} size={20} />
            </TouchableOpacity>

            <TouchableOpacity style={styles.resourceCard} onPress={() => navigation.navigate('EmergencyContacts')}>
              <View style={[styles.resourceIconBox, { backgroundColor: '#FEE2E2' }]}>
                <Feather name="phone-call" color={COLORS.danger} size={24} />
              </View>
              <View style={styles.resourceTextContent}>
                <Text style={styles.resourceTitle}>Emergency Contacts</Text>
                <Text style={styles.resourceDesc}>Hotlines, local responders, and medical facilities.</Text>
              </View>
              <Feather name="chevron-right" color={COLORS.textMuted} size={20} />
            </TouchableOpacity>

          </View>
          
          <View style={{ height: 40 }} />
        </ScrollView>
      </Animated.View>
    </SafeAreaView>
  );
}