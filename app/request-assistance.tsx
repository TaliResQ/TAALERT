
import { supabase } from '@/src/services/supabase';
import { Feather } from '@expo/vector-icons';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Keyboard,
  KeyboardAvoidingView,
  Modal,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';

import BottomNavbar from '../components/BottomNavbar';

const categories = [
  'Relief Goods',
  'Temporary Shelter',
  'Medical Assistance',
  'Financial Assistance',
  'Livelihood Support',
  'Other',
];

const BARANGAYS = [
  'Poblacion Barangay 1',
  'Poblacion Barangay 2',
  'Poblacion Barangay 3',
  'Poblacion Barangay 4',
  'Poblacion Barangay 5',
  'Poblacion Barangay 6',
  'Poblacion Barangay 7',
  'Poblacion Barangay 8',
  'Aya',
  'Balas',
  'Banga',
  'Buco',
  'Caloocan',
  'Leynes',
  'Miranda',
  'Quiling',
  'Sampaloc',
  'San Guillermo',
  'Santa Maria',
  'Tranca',
  'Tumaway',
];

export default function RequestAssistanceScreen() {
  const router = useRouter();

  const { location, area } = useLocalSearchParams<{
    location?: string;
    area?: 'inside' | 'outside';
  }>();

  const isInsideTalisay = area !== 'outside';

  const [category, setCategory] = useState('Relief Goods');

  const [barangay, setBarangay] = useState(
    isInsideTalisay &&
      typeof location === 'string' &&
      BARANGAYS.includes(location)
      ? location
      : ''
  );

  const [address, setAddress] = useState(
    typeof location === 'string' &&
      !(isInsideTalisay && BARANGAYS.includes(location))
      ? location
      : ''
  );

  const [description, setDescription] = useState('');
  const [contactNumber, setContactNumber] = useState('');
  const [loading, setLoading] = useState(false);
  const [showBarangayModal, setShowBarangayModal] = useState(false);

  const handleSubmit = async () => {
    Keyboard.dismiss();

    if (!barangay.trim() || !description.trim()) {
      Alert.alert(
        'Missing Information',
        'Please enter your barangay and describe the assistance you need.'
      );
      return;
    }

    setLoading(true);

    try {
      const {
        data: { user },
        error: userError,
      } = await supabase.auth.getUser();

      if (userError || !user) {
        Alert.alert(
          'Sign In Required',
          'Please sign in before submitting an assistance request.'
        );
        return;
      }

      const { error } = await supabase
        .from('assistance_requests')
        .insert({
          user_id: user.id,
          request_category: category,
          barangay: barangay.trim(),
          address: address.trim() || null,
          description: description.trim(),
          contact_number: contactNumber.trim() || null,
          status: 'pending',
        });

      if (error) {
        console.error('Assistance request error:', error);

        Alert.alert(
          'Submission Failed',
          error.message
        );

        return;
      }

      Alert.alert(
        'Request Submitted',
        'Your recovery assistance request has been submitted for review.',
        [
          {
            text: 'OK',
            onPress: () => router.replace('/recovery-assistance'),
          },
        ]
      );

      setCategory('Relief Goods');
      setBarangay('');
      setAddress('');
      setDescription('');
      setContactNumber('');

    } catch (error) {
      console.error('Unexpected submission error:', error);

      Alert.alert(
        'Error',
        'Something went wrong. Please try again.'
      );
    } finally {
      setLoading(false);
    }
  };

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <View style={styles.page}>
        <ScrollView
          contentContainerStyle={styles.content}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
        >
          {/* Back Button */}
          <TouchableOpacity
            style={styles.backButton}
            onPress={() => router.back()}
          >
            <Feather
              name="arrow-left"
              size={20}
              color="#1E293B"
            />

            <Text style={styles.backText}>
              Back
            </Text>
          </TouchableOpacity>

          {/* Heading */}
          <View style={styles.heading}>
            <View style={styles.iconCircle}>
              <Feather
                name="clipboard"
                size={26}
                color="#2563EB"
              />
            </View>

            <Text style={styles.title}>
              Request Assistance
            </Text>

            <Text style={styles.subtitle}>
              Tell us what help you need after a disaster.
              Your request will be reviewed by authorized personnel.
            </Text>
          </View>

          {/* Category */}
          <Text style={styles.label}>
            Type of Assistance *
          </Text>

          <View style={styles.categoryContainer}>
            {categories.map((item) => {
              const selected = category === item;

              return (
                <TouchableOpacity
                  key={item}
                  style={[
                    styles.categoryButton,
                    selected && styles.categorySelected,
                  ]}
                  onPress={() => setCategory(item)}
                  activeOpacity={0.8}
                >
                  <Text
                    style={[
                      styles.categoryText,
                      selected && styles.categoryTextSelected,
                    ]}
                  >
                    {item}
                  </Text>
                </TouchableOpacity>
              );
            })}
          </View>

          {/* Barangay */}
          <Text style={styles.label}>
            {isInsideTalisay ? 'Barangay *' : 'Current Barangay / Municipality *'}
          </Text>

          {isInsideTalisay ? (
            <TouchableOpacity
              style={styles.dropdown}
              activeOpacity={0.8}
              onPress={() => setShowBarangayModal(true)}
            >
              <Feather
                name="map-pin"
                size={18}
                color="#64748B"
              />

              <Text
                style={[
                  styles.dropdownText,
                  !barangay && styles.placeholderText,
                ]}
                numberOfLines={1}
              >
                {barangay || 'Select your barangay'}
              </Text>

              <Feather
                name="chevron-down"
                size={20}
                color="#64748B"
              />
            </TouchableOpacity>
          ) : (
            <TextInput
              style={styles.input}
              value={barangay}
              onChangeText={setBarangay}
              placeholder="Enter your barangay or municipality"
              placeholderTextColor="#94A3B8"
              autoCapitalize="words"
            />
          )}

          {/* Specific Address */}
          <Text style={styles.label}>
            Specific Address (Optional)
          </Text>

          <TextInput
            style={styles.input}
            value={address}
            onChangeText={setAddress}
            placeholder="Street, purok, or landmark"
            placeholderTextColor="#94A3B8"
            autoCapitalize="words"
          />

          {/* Contact Number */}
          <Text style={styles.label}>
            Contact Number (Optional)
          </Text>

          <TextInput
            style={styles.input}
            value={contactNumber}
            onChangeText={setContactNumber}
            placeholder="Enter a contact number"
            placeholderTextColor="#94A3B8"
            keyboardType="phone-pad"
          />

          {/* Description */}
          <Text style={styles.label}>
            Describe Your Situation *
          </Text>

          <TextInput
            style={[styles.input, styles.textArea]}
            value={description}
            onChangeText={setDescription}
            placeholder="Explain what happened and what assistance you need."
            placeholderTextColor="#94A3B8"
            multiline
            textAlignVertical="top"
            maxLength={1500}
          />

          <Text style={styles.characterCount}>
            {description.length}/1500 characters
          </Text>

          {/* Notice */}
          <View style={styles.notice}>
            <Feather
              name="info"
              size={18}
              color="#92400E"
            />

            <Text style={styles.noticeText}>
              Submitting this form does not guarantee immediate assistance.
              For emergencies, contact local emergency services directly.
            </Text>
          </View>

          {/* Submit */}
          <TouchableOpacity
            style={[
              styles.submitButton,
              loading && styles.submitDisabled,
            ]}
            onPress={handleSubmit}
            disabled={loading}
            activeOpacity={0.85}
          >
            {loading ? (
              <ActivityIndicator color="#FFFFFF" />
            ) : (
              <>
                <Feather
                  name="send"
                  size={18}
                  color="#FFFFFF"
                />

                <Text style={styles.submitText}>
                  Submit Request
                </Text>
              </>
            )}
          </TouchableOpacity>
        </ScrollView>
      </View>

      {/* Barangay Dropdown Modal */}
      <Modal
        visible={showBarangayModal}
        transparent
        animationType="slide"
        onRequestClose={() => setShowBarangayModal(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>

            {/* Modal Header */}
            <View style={styles.modalHeader}>
              <View>
                <Text style={styles.modalTitle}>
                  Select Barangay
                </Text>

                <Text style={styles.modalSubtitle}>
                  Choose your barangay in Talisay, Batangas
                </Text>
              </View>

              <TouchableOpacity
                onPress={() => setShowBarangayModal(false)}
                style={styles.closeButton}
              >
                <Feather
                  name="x"
                  size={22}
                  color="#64748B"
                />
              </TouchableOpacity>
            </View>

            {/* Barangay List */}
            <ScrollView
              showsVerticalScrollIndicator={false}
              nestedScrollEnabled
            >
              {BARANGAYS.map((item) => {
                const selected = barangay === item;

                return (
                  <TouchableOpacity
                    key={item}
                    style={[
                      styles.barangayOption,
                      selected && styles.selectedBarangay,
                    ]}
                    activeOpacity={0.7}
                    onPress={() => {
                      setBarangay(item);
                      setShowBarangayModal(false);
                    }}
                  >
                    <Text
                      style={[
                        styles.barangayText,
                        selected && styles.selectedBarangayText,
                      ]}
                    >
                      {item}
                    </Text>

                    {selected && (
                      <Feather
                        name="check-circle"
                        size={18}
                        color="#2563EB"
                      />
                    )}
                  </TouchableOpacity>
                );
              })}
            </ScrollView>

          </View>
        </View>
      </Modal>

      <BottomNavbar activeTab="resources" />
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#F8FAFC',
  },

  page: {
    flex: 1,
  },

  content: {
    paddingHorizontal: 22,
    paddingTop: 52,
    paddingBottom: 120,
  },

  backButton: {
    flexDirection: 'row',
    alignItems: 'center',
    alignSelf: 'flex-start',
    gap: 8,
    marginBottom: 24,
  },

  backText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#1E293B',
  },

  heading: {
    alignItems: 'center',
    marginBottom: 28,
  },

  iconCircle: {
    width: 60,
    height: 60,
    borderRadius: 30,
    backgroundColor: '#DBEAFE',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 14,
  },

  title: {
    fontSize: 25,
    fontWeight: '800',
    color: '#0F172A',
    textAlign: 'center',
  },

  subtitle: {
    fontSize: 13,
    color: '#64748B',
    textAlign: 'center',
    lineHeight: 20,
    marginTop: 8,
  },

  label: {
    fontSize: 14,
    fontWeight: '700',
    color: '#334155',
    marginBottom: 9,
    marginTop: 17,
  },

  categoryContainer: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 9,
  },

  categoryButton: {
    paddingHorizontal: 13,
    paddingVertical: 10,
    borderRadius: 12,
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#CBD5E1',
  },

  categorySelected: {
    backgroundColor: '#DBEAFE',
    borderColor: '#2563EB',
  },

  categoryText: {
    fontSize: 12,
    color: '#475569',
    fontWeight: '500',
  },

  categoryTextSelected: {
    color: '#1D4ED8',
    fontWeight: '700',
  },

  input: {
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#CBD5E1',
    borderRadius: 13,
    paddingHorizontal: 14,
    paddingVertical: 13,
    fontSize: 14,
    color: '#0F172A',
  },

  dropdown: {
    minHeight: 49,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#CBD5E1',
    borderRadius: 13,
    paddingHorizontal: 14,
  },

  dropdownText: {
    flex: 1,
    fontSize: 14,
    color: '#0F172A',
  },

  placeholderText: {
    color: '#94A3B8',
  },

  textArea: {
    minHeight: 125,
  },

  characterCount: {
    textAlign: 'right',
    fontSize: 11,
    color: '#94A3B8',
    marginTop: 5,
  },

  notice: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 10,
    backgroundColor: '#FEF3C7',
    padding: 13,
    borderRadius: 12,
    marginTop: 22,
  },

  noticeText: {
    flex: 1,
    fontSize: 12,
    lineHeight: 18,
    color: '#92400E',
  },

  submitButton: {
    backgroundColor: '#2563EB',
    borderRadius: 14,
    minHeight: 52,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
    marginTop: 22,
  },

  submitDisabled: {
    opacity: 0.6,
  },

  submitText: {
    color: '#FFFFFF',
    fontSize: 15,
    fontWeight: '700',
  },

  // Barangay Modal
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(15, 23, 42, 0.45)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },

  modalContent: {
    backgroundColor: '#FFFFFF',
    width: '100%',
    maxHeight: '75%',
    borderRadius: 18,
    padding: 20,
  },

  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 18,
  },

  modalTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#1E293B',
  },

  modalSubtitle: {
    fontSize: 12,
    color: '#64748B',
    marginTop: 4,
  },

  closeButton: {
    padding: 5,
  },

  barangayOption: {
    minHeight: 50,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#F1F5F9',
  },

  selectedBarangay: {
    backgroundColor: '#EFF6FF',
    borderRadius: 8,
  },

  barangayText: {
    fontSize: 14,
    color: '#334155',
  },

  selectedBarangayText: {
    color: '#2563EB',
    fontWeight: '700',
  },
});
