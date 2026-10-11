
import { Feather } from '@expo/vector-icons';
import * as Location from 'expo-location';
import { useRouter } from 'expo-router';
import { useState } from 'react';
import {
    ActivityIndicator,
    Alert,
    Keyboard,
    SafeAreaView,
    ScrollView,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    View,
} from 'react-native';
import BottomNavbar from '../components/BottomNavbar';

type LocationArea = 'inside' | 'outside';

export default function RecoveryAssistanceScreen() {
  const router = useRouter();

  const [locationArea, setLocationArea] =
    useState<LocationArea>('inside');
  const [locationMethod, setLocationMethod] =
    useState<'gps' | 'manual'>('manual');
  const [manualLocation, setManualLocation] = useState('');
  const [currentLocation, setCurrentLocation] = useState('');
  const [loadingLocation, setLoadingLocation] = useState(false);

  const getCurrentLocation = async () => {
    try {
      setLoadingLocation(true);
      Keyboard.dismiss();

      const permission =
        await Location.requestForegroundPermissionsAsync();

      if (permission.status !== 'granted') {
        Alert.alert(
          'Location Permission Required',
          'Location access was denied. You can still enter your location manually.'
        );
        return;
      }

      const position = await Location.getCurrentPositionAsync({
        accuracy: Location.Accuracy.Balanced,
      });

      const { latitude, longitude } = position.coords;

      let formattedAddress = '';

      try {
        const addresses = await Location.reverseGeocodeAsync({
          latitude,
          longitude,
        });

        if (addresses.length > 0) {
          const address = addresses[0];

          formattedAddress = [
            address.name,
            address.street,
            address.district,
            address.city,
            address.subregion,
            address.region,
          ]
            .filter(
              (part, index, array) =>
                Boolean(part) && array.indexOf(part) === index
            )
            .join(', ');
        }
      } catch {
        // Coordinates can still be used if reverse geocoding fails.
      }

      const locationText =
        formattedAddress ||
        `${latitude.toFixed(5)}, ${longitude.toFixed(5)}`;

      setCurrentLocation(locationText);
      setManualLocation(locationText);
      setLocationMethod('gps');

      Alert.alert(
        'Location Captured',
        'Your current location has been added. Please check that it is correct.'
      );
    } catch (error) {
      console.error('Location error:', error);

      Alert.alert(
        'Unable to Get Location',
        'Please try again or enter your location manually.'
      );
    } finally {
      setLoadingLocation(false);
    }
  };

  const handleLocationArea = (area: LocationArea) => {
    setLocationArea(area);
    setCurrentLocation('');
    setManualLocation('');
    setLocationMethod('manual');
  };

  const getSelectedLocation = () => {
    return manualLocation.trim() || currentLocation.trim();
  };

  const handleOptionPress = (
    option: 'nearby' | 'request' | 'resources'
  ) => {
    Keyboard.dismiss();

    const selectedLocation = getSelectedLocation();

    if (!selectedLocation) {
      Alert.alert(
        'Location Needed',
        'Please use your current location or enter a location before continuing.'
      );
      return;
    }

    const locationDescription =
      locationArea === 'inside'
        ? 'Currently in Talisay'
        : 'Currently outside Talisay';

    const message =
      `Selected area: ${locationDescription}\n` +
      `Location: ${selectedLocation}\n\n` +
      'This section will be connected in the next implementation steps.';

    const titles = {
      nearby: 'Find Nearby Assistance',
      request: 'Request Assistance',
      resources: 'Recovery Resources',
    };

    Alert.alert(titles[option], message);
  };

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
        keyboardShouldPersistTaps="handled"
      >
        {/* Header */}
        <View style={styles.header}>
          <TouchableOpacity
            style={styles.backButton}
            onPress={() => router.back()}
            accessibilityLabel="Go back"
          >
            <Feather name="arrow-left" size={22} color="#123B5D" />
          </TouchableOpacity>

          <View style={styles.headerTextContainer}>
            <Text style={styles.headerSubtitle}>SUPPORT AFTER A DISASTER</Text>
            <Text style={styles.headerTitle}>Recovery Assistance</Text>
          </View>
        </View>

        {/* Intro */}
        <View style={styles.introCard}>
          <View style={styles.introIcon}>
            <Feather name="life-buoy" size={30} color="#1769AA" />
          </View>

          <Text style={styles.introTitle}>
            Find the help you need
          </Text>

          <Text style={styles.introDescription}>
            Find recovery resources or request assistance for yourself
            and your household. You can search even if you have evacuated
            outside Talisay.
          </Text>
        </View>

        {/* Location Selection */}
        <Text style={styles.sectionTitle}>
          Where are you currently located?
        </Text>

        <TouchableOpacity
          activeOpacity={0.8}
          style={[
            styles.locationChoice,
            locationArea === 'inside' && styles.locationChoiceSelected,
          ]}
          onPress={() => handleLocationArea('inside')}
        >
          <View
            style={[
              styles.radioCircle,
              locationArea === 'inside' && styles.radioCircleSelected,
            ]}
          >
            {locationArea === 'inside' && (
              <View style={styles.radioInner} />
            )}
          </View>

          <View style={styles.locationChoiceText}>
            <Text style={styles.locationChoiceTitle}>
              I am currently in Talisay
            </Text>
            <Text style={styles.locationChoiceDescription}>
              Find support within Talisay, Batangas.
            </Text>
          </View>

          <Feather name="map-pin" size={21} color="#1769AA" />
        </TouchableOpacity>

        <TouchableOpacity
          activeOpacity={0.8}
          style={[
            styles.locationChoice,
            locationArea === 'outside' && styles.locationChoiceSelected,
          ]}
          onPress={() => handleLocationArea('outside')}
        >
          <View
            style={[
              styles.radioCircle,
              locationArea === 'outside' && styles.radioCircleSelected,
            ]}
          >
            {locationArea === 'outside' && (
              <View style={styles.radioInner} />
            )}
          </View>

          <View style={styles.locationChoiceText}>
            <Text style={styles.locationChoiceTitle}>
              I am currently outside Talisay
            </Text>
            <Text style={styles.locationChoiceDescription}>
              I have evacuated or am staying elsewhere.
            </Text>
          </View>

          <Feather name="navigation" size={21} color="#1769AA" />
        </TouchableOpacity>

        {/* Location Input */}
        <View style={styles.locationSection}>
          <Text style={styles.sectionTitle}>Your current location</Text>
          <Text style={styles.helperText}>
            Use GPS or enter your location yourself. GPS is optional.
          </Text>

          <TouchableOpacity
            activeOpacity={0.8}
            style={styles.gpsButton}
            onPress={getCurrentLocation}
            disabled={loadingLocation}
          >
            {loadingLocation ? (
              <ActivityIndicator color="#1769AA" />
            ) : (
              <Feather name="crosshair" size={19} color="#1769AA" />
            )}

            <Text style={styles.gpsButtonText}>
              {loadingLocation
                ? 'Getting your location...'
                : 'Use My Current Location'}
            </Text>
          </TouchableOpacity>

          <View style={styles.orDivider}>
            <View style={styles.dividerLine} />
            <Text style={styles.orText}>OR ENTER MANUALLY</Text>
            <View style={styles.dividerLine} />
          </View>

          <TextInput
            style={styles.locationInput}
            value={manualLocation}
            onChangeText={(text) => {
              setManualLocation(text);
              setLocationMethod('manual');
              setCurrentLocation('');
            }}
            placeholder={
              locationArea === 'inside'
                ? 'Barangay, street, or address in Talisay'
                : 'City, municipality, barangay, or current address'
            }
            placeholderTextColor="#94A3B8"
            autoCapitalize="words"
            returnKeyType="done"
            onSubmitEditing={Keyboard.dismiss}
          />

          {getSelectedLocation() !== '' && (
            <View style={styles.locationConfirmation}>
              <Feather name="check-circle" size={17} color="#15803D" />
              <Text style={styles.locationConfirmationText}>
                Location entered. Please make sure it is accurate.
              </Text>
            </View>
          )}
        </View>

        {/* Assistance Options */}
        <Text style={styles.sectionTitle}>
          How can we help you?
        </Text>

        <TouchableOpacity
          activeOpacity={0.8}
          style={styles.optionCard}
          onPress={() => handleOptionPress('nearby')}
        >
          <View style={[styles.optionIcon, { backgroundColor: '#DBEAFE' }]}>
            <Feather name="map-pin" size={24} color="#2563EB" />
          </View>

          <View style={styles.optionText}>
            <Text style={styles.optionTitle}>
              Find Nearby Assistance
            </Text>
            <Text style={styles.optionDescription}>
              Look for shelters, food, water, medical support, and relief.
            </Text>
          </View>

          <Feather name="chevron-right" size={22} color="#94A3B8" />
        </TouchableOpacity>


<TouchableOpacity
  activeOpacity={0.8}
  style={styles.optionCard}
  onPress={() => {
    const selectedLocation = getSelectedLocation();

    if (!selectedLocation) {
      Alert.alert(
        'Location Needed',
        'Please use your current location or enter a location before continuing.'
      );
      return;
    }

    router.push({
      pathname: '/request-assistance',
      params: {
        location: selectedLocation,
        area: locationArea,
      },
    });
  }}
>

          <View style={[styles.optionIcon, { backgroundColor: '#D1FAE5' }]}>
            <Feather name="clipboard" size={24} color="#047857" />
          </View>

          <View style={styles.optionText}>
            <Text style={styles.optionTitle}>Request Assistance</Text>
            <Text style={styles.optionDescription}>
              Submit a request for your household's recovery needs.
            </Text>
          </View>

          <Feather name="chevron-right" size={22} color="#94A3B8" />
        </TouchableOpacity>

        <TouchableOpacity
          activeOpacity={0.8}
          style={styles.optionCard}
onPress={() => {
  const selectedLocation = getSelectedLocation();

  if (!selectedLocation) {
    Alert.alert(
      'Location Needed',
      'Please enter your current location before continuing.'
    );
    return;
  }

  router.push({
    pathname: '/recovery-resources',
    params: {
      location: selectedLocation,
      area: locationArea,
    },
  });
}}        >
          <View style={[styles.optionIcon, { backgroundColor: '#FEF3C7' }]}>
            <Feather name="heart" size={24} color="#B45309" />
          </View>

          <View style={styles.optionText}>
            <Text style={styles.optionTitle}>Recovery Resources</Text>
            <Text style={styles.optionDescription}>
              Browse organizations and verified recovery services.
            </Text>
          </View>

          <Feather name="chevron-right" size={22} color="#94A3B8" />
        </TouchableOpacity>

        {/* Safety Note */}
        <View style={styles.safetyNote}>
          <Feather name="info" size={19} color="#92400E" />
          <Text style={styles.safetyNoteText}>
            This feature is for recovery support. If you are in immediate
            danger, contact the appropriate local emergency services.
          </Text>
        </View>

        <View style={{ height: 24 }} />
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
  scrollContent: {
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
  headerTextContainer: {
    flex: 1,
  },
  headerSubtitle: {
    color: '#64748B',
    fontSize: 10,
    fontWeight: '700',
    letterSpacing: 1,
    marginBottom: 4,
  },
  headerTitle: {
    color: '#123B5D',
    fontSize: 23,
    fontWeight: '800',
  },
  introCard: {
    backgroundColor: '#EAF4FF',
    borderRadius: 20,
    padding: 20,
    marginBottom: 26,
    borderWidth: 1,
    borderColor: '#D5E8FC',
  },
  introIcon: {
    width: 54,
    height: 54,
    borderRadius: 16,
    backgroundColor: '#FFFFFF',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 14,
  },
  introTitle: {
    fontSize: 20,
    fontWeight: '800',
    color: '#123B5D',
    marginBottom: 8,
  },
  introDescription: {
    fontSize: 14,
    lineHeight: 21,
    color: '#475569',
  },
  sectionTitle: {
    fontSize: 17,
    fontWeight: '800',
    color: '#123B5D',
    marginBottom: 12,
  },
  locationChoice: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#FFFFFF',
    padding: 15,
    borderRadius: 15,
    borderWidth: 1,
    borderColor: '#E2E8F0',
    marginBottom: 10,
  },
  locationChoiceSelected: {
    backgroundColor: '#EFF6FF',
    borderColor: '#60A5FA',
  },
  radioCircle: {
    width: 21,
    height: 21,
    borderRadius: 11,
    borderWidth: 2,
    borderColor: '#CBD5E1',
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12,
  },
  radioCircleSelected: {
    borderColor: '#2563EB',
  },
  radioInner: {
    width: 11,
    height: 11,
    borderRadius: 6,
    backgroundColor: '#2563EB',
  },
  locationChoiceText: {
    flex: 1,
    marginRight: 8,
  },
  locationChoiceTitle: {
    color: '#1E293B',
    fontSize: 14,
    fontWeight: '700',
    marginBottom: 4,
  },
  locationChoiceDescription: {
    color: '#64748B',
    fontSize: 12,
    lineHeight: 18,
  },
  locationSection: {
    marginTop: 18,
    marginBottom: 28,
  },
  helperText: {
    fontSize: 13,
    lineHeight: 19,
    color: '#64748B',
    marginTop: -5,
    marginBottom: 14,
  },
  gpsButton: {
    minHeight: 49,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#93C5FD',
    borderRadius: 13,
    paddingHorizontal: 12,
    gap: 9,
  },
  gpsButtonText: {
    color: '#1769AA',
    fontSize: 14,
    fontWeight: '700',
  },
  orDivider: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    marginVertical: 17,
  },
  dividerLine: {
    flex: 1,
    height: 1,
    backgroundColor: '#E2E8F0',
  },
  orText: {
    fontSize: 10,
    color: '#94A3B8',
    fontWeight: '700',
    letterSpacing: 0.5,
  },
  locationInput: {
    minHeight: 50,
    borderRadius: 13,
    borderWidth: 1,
    borderColor: '#CBD5E1',
    backgroundColor: '#FFFFFF',
    paddingHorizontal: 14,
    paddingVertical: 12,
    color: '#1E293B',
    fontSize: 14,
  },
  locationConfirmation: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 7,
    marginTop: 10,
  },
  locationConfirmationText: {
    flex: 1,
    color: '#15803D',
    fontSize: 12,
    lineHeight: 18,
  },
  optionCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#FFFFFF',
    borderRadius: 17,
    padding: 15,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: '#E2E8F0',
  },
  optionIcon: {
    width: 49,
    height: 49,
    borderRadius: 14,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 13,
  },
  optionText: {
    flex: 1,
    marginRight: 8,
  },
  optionTitle: {
    color: '#1E293B',
    fontSize: 14,
    fontWeight: '800',
    marginBottom: 5,
  },
  optionDescription: {
    color: '#64748B',
    fontSize: 12,
    lineHeight: 18,
  },
  safetyNote: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    backgroundColor: '#FFFBEB',
    borderRadius: 13,
    padding: 13,
    marginTop: 10,
    gap: 9,
    borderWidth: 1,
    borderColor: '#FDE68A',
  },
  safetyNoteText: {
    flex: 1,
    fontSize: 12,
    lineHeight: 19,
    color: '#92400E',
  },
});
