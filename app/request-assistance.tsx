
import { supabase } from '@/src/services/supabase';
import { Feather } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { useState } from 'react';
import {
    ActivityIndicator,
    Alert,
    KeyboardAvoidingView,
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

export default function RequestAssistanceScreen() {
  const router = useRouter();

  const [category, setCategory] = useState('Relief Goods');
  const [barangay, setBarangay] = useState('');
  const [address, setAddress] = useState('');
  const [description, setDescription] = useState('');
  const [contactNumber, setContactNumber] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async () => {
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
          <TouchableOpacity
            style={styles.backButton}
            onPress={() => router.back()}
          >
            <Feather name="arrow-left" size={20} color="#1E293B" />
            <Text style={styles.backText}>Back</Text>
          </TouchableOpacity>

          <View style={styles.heading}>
            <View style={styles.iconCircle}>
              <Feather name="clipboard" size={26} color="#2563EB" />
            </View>
            <Text style={styles.title}>Request Assistance</Text>
            <Text style={styles.subtitle}>
              Tell us what help you need after a disaster.
              Your request will be reviewed by authorized personnel.
            </Text>
          </View>

          <Text style={styles.label}>Type of Assistance *</Text>
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

          <Text style={styles.label}>Barangay *</Text>
          <TextInput
            style={styles.input}
            value={barangay}
            onChangeText={setBarangay}
            placeholder="Enter your barangay"
            placeholderTextColor="#94A3B8"
          />

          <Text style={styles.label}>Specific Address (Optional)</Text>
          <TextInput
            style={styles.input}
            value={address}
            onChangeText={setAddress}
            placeholder="Street, purok, or landmark"
            placeholderTextColor="#94A3B8"
          />

          <Text style={styles.label}>Contact Number (Optional)</Text>
          <TextInput
            style={styles.input}
            value={contactNumber}
            onChangeText={setContactNumber}
            placeholder="Enter a contact number"
            placeholderTextColor="#94A3B8"
            keyboardType="phone-pad"
          />

          <Text style={styles.label}>Describe Your Situation *</Text>
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

          <View style={styles.notice}>
            <Feather name="info" size={18} color="#92400E" />
            <Text style={styles.noticeText}>
              Submitting this form does not guarantee immediate assistance.
              For emergencies, contact local emergency services directly.
            </Text>
          </View>

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
                <Feather name="send" size={18} color="#FFFFFF" />
                <Text style={styles.submitText}>Submit Request</Text>
              </>
            )}
          </TouchableOpacity>
        </ScrollView>
      </View>

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
    paddingBottom: 30,
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
});
