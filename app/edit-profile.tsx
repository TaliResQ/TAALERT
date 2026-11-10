
import { supabase } from '@/src/services/supabase';
import { Feather } from '@expo/vector-icons';
import DateTimePicker from '@react-native-community/datetimepicker';
import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import {
    ActivityIndicator,
    Alert,
    KeyboardAvoidingView,
    Modal,
    Platform,
    SafeAreaView,
    ScrollView,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    View,
} from 'react-native';

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

export default function EditProfileScreen() {
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);

    const [showBarangayModal, setShowBarangayModal] = useState(false);
const [showDatePicker, setShowDatePicker] = useState(false);

const [tempBirthdate, setTempBirthdate] = useState(new Date());

    const [fullname, setFullname] = useState('');
    const [contactNumber, setContactNumber] = useState('');
    const [barangay, setBarangay] = useState('');
    const [address, setAddress] = useState('');
    const [birthdate, setBirthdate] = useState('');

// Convert date to YYYY-MM-DD
const formatDate = (date: Date) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');

  return `${year}-${month}-${day}`;
};

// Convert stored birthdate to a local Date object
const getBirthdateDate = () => {
  if (!birthdate) return new Date();

  const [year, month, day] = birthdate.split('-').map(Number);

  return new Date(year, month - 1, day);
};

    useEffect(() => {
        fetchProfile();
    }, []);

    // Fetch current user's information
    const fetchProfile = async () => {
        try {
            const {
                data: { user },
                error: authError,
            } = await supabase.auth.getUser();

            if (authError || !user) {
                Alert.alert('Error', 'Please sign in again.');
                router.replace('/');
                return;
            }

            const { data, error } = await supabase
                .from('users')
                .select('fullname, contact_number, barangay, address, birthdate')
                .eq('id', user.id)
                .single();

            if (error) throw error;

            setFullname(data.fullname || '');
            setContactNumber(data.contact_number || '');
            setBarangay(data.barangay || '');
            setAddress(data.address || '');
            setBirthdate(data.birthdate || '');

        } catch (error: any) {
            console.error('Fetch profile error:', error.message);
            Alert.alert('Error', 'Unable to load your profile information.');
        } finally {
            setLoading(false);
        }
    };

    // Save updated profile information
    const handleSave = async () => {
        if (!fullname.trim()) {
            Alert.alert('Missing Information', 'Please enter your full name.');
            return;
        }

        if (birthdate.trim() && !/^\d{4}-\d{2}-\d{2}$/.test(birthdate.trim())) {
            Alert.alert(
                'Invalid Birthdate',
                'Please use YYYY-MM-DD format (e.g. 2004-06-07).'
            );
            return;
        }

        setSaving(true);

        try {
            const {
                data: { user },
                error: authError,
            } = await supabase.auth.getUser();

            if (authError || !user) {
                throw new Error('Your session has expired. Please sign in again.');
            }

            const { error } = await supabase
                .from('users')
                .update({
                    fullname: fullname.trim(),
                    contact_number: contactNumber.trim() || null,
                    barangay: barangay.trim() || null,
                    address: address.trim() || null,
                    birthdate: birthdate.trim() || null,
                })
                .eq('id', user.id);

            if (error) throw error;

            Alert.alert(
                'Profile Updated',
                'Your profile information has been successfully updated.',
                [
                    {
                        text: 'OK',
                        onPress: () => router.back(),
                    },
                ]
            );

        } catch (error: any) {
            console.error('Update profile error:', error.message);
            Alert.alert('Update Failed', error.message || 'Something went wrong.');
        } finally {
            setSaving(false);
        }
    };

    // Reusable input component
    if (loading) {
        return (
            <SafeAreaView style={styles.loadingContainer}>
                <ActivityIndicator size="large" color="#25A5FE" />
                <Text style={styles.loadingText}>Loading your profile...</Text>
            </SafeAreaView>
        );
    }

    return (
        <SafeAreaView style={styles.container}>
            <KeyboardAvoidingView
                style={{ flex: 1 }}
                behavior={Platform.OS === 'ios' ? 'padding' : undefined}
            >
                {/* Header */}
                <View style={styles.header}>
                    <TouchableOpacity
                        style={styles.backButton}
                        onPress={() => router.back()}
                    >
                        <Feather name="arrow-left" size={22} color="#1E293B" />
                    </TouchableOpacity>

                    <Text style={styles.headerTitle}>Edit Profile</Text>

                    <View style={{ width: 42 }} />
                </View>

                <ScrollView
                    contentContainerStyle={styles.scrollContent}
                    showsVerticalScrollIndicator={false}
                    keyboardShouldPersistTaps="handled"
                >
                    {/* Intro */}
                    <View style={styles.intro}>
                        <View style={styles.avatar}>
                            <Feather name="user" size={30} color="#25A5FE" />
                        </View>

                        <Text style={styles.introTitle}>Personal Information</Text>
                        <Text style={styles.introSub}>
                            Keep your information updated so emergency responders
                            can better assist you when needed.
                        </Text>
                    </View>

                    {/* Form */}
                    <View style={styles.formCard}>
                        <ProfileInput
                            label="Full Name"
                            value={fullname}
                            onChangeText={setFullname}
                            placeholder="Enter your full name"
                            icon="user"
                        />

                        <ProfileInput
                            label="Contact Number"
                            value={contactNumber}
                            onChangeText={setContactNumber}
                            placeholder="e.g. 09123456789"
                            icon="phone"
                            keyboardType="phone-pad"
                        />

<View style={styles.inputGroup}>
  <Text style={styles.inputLabel}>Barangay</Text>

  <TouchableOpacity
    style={styles.inputWrapper}
    onPress={() => setShowBarangayModal(true)}
    activeOpacity={0.7}
  >
    <Feather
      name="map-pin"
      size={18}
      color="#64748B"
      style={styles.inputIcon}
    />

    <Text
      style={{
        flex: 1,
        fontSize: 14,
        color: barangay ? '#1E293B' : '#94A3B8',
      }}
    >
      {barangay || 'Select your barangay'}
    </Text>

    <Feather name="chevron-down" size={18} color="#64748B" />
  </TouchableOpacity>
</View>

                        <ProfileInput
                            label="Complete Address"
                            value={address}
                            onChangeText={setAddress}
                            placeholder="House number, street, subdivision..."
                            icon="home"
                            multiline
                        />

<View style={styles.inputGroup}>
  <Text style={styles.inputLabel}>Birthdate</Text>

  <TouchableOpacity
    style={styles.inputWrapper}
    onPress={() => {
      setTempBirthdate(getBirthdateDate());
      setShowDatePicker(true);
    }}
    activeOpacity={0.7}
  >
    <Feather
      name="calendar"
      size={18}
      color="#64748B"
      style={styles.inputIcon}
    />

    <Text
      style={{
        flex: 1,
        fontSize: 14,
        color: birthdate ? '#1E293B' : '#94A3B8',
      }}
    >
      {birthdate || 'Select your birthdate'}
    </Text>

    <Feather name="calendar" size={18} color="#25A5FE" />
  </TouchableOpacity>
</View>

                        <Text style={styles.helperText}>
                            Please use YYYY-MM-DD format for your birthdate.
                        </Text>
                    </View>

                    {/* Save Button */}
                    <TouchableOpacity
                        style={[styles.saveButton, saving && styles.disabledButton]}
                        onPress={handleSave}
                        disabled={saving}
                    >
                        {saving ? (
                            <ActivityIndicator color="#FFFFFF" />
                        ) : (
                            <>
                                <Feather name="save" size={18} color="#FFFFFF" />
                                <Text style={styles.saveButtonText}>Save Changes</Text>
                            </>
                        )}
                    </TouchableOpacity>

                    <TouchableOpacity
                        style={styles.cancelButton}
                        onPress={() => router.back()}
                    >
                        <Text style={styles.cancelButtonText}>Cancel</Text>
                    </TouchableOpacity>

                    <View style={{ height: 40 }} />
                </ScrollView>
                {/* Barangay Dropdown Modal */}
<Modal
  visible={showBarangayModal}
  transparent
  animationType="slide"
  onRequestClose={() => setShowBarangayModal(false)}
>
  <View style={styles.modalOverlay}>
    <View style={styles.modalContent}>
      <View style={styles.modalHeader}>
        <View>
          <Text style={styles.modalTitle}>Select Barangay</Text>
          <Text style={styles.modalSubtitle}>
            Choose your barangay in Talisay
          </Text>
        </View>

        <TouchableOpacity
          onPress={() => setShowBarangayModal(false)}
        >
          <Feather name="x" size={23} color="#64748B" />
        </TouchableOpacity>
      </View>

      <ScrollView
        showsVerticalScrollIndicator={false}
        nestedScrollEnabled
      >
        {BARANGAYS.map((item, index) => (
          <TouchableOpacity
            key={item}
            style={[
              styles.barangayOption,
              barangay === item && styles.selectedBarangay,
            ]}
            onPress={() => {
              setBarangay(item);
              setShowBarangayModal(false);
            }}
          >
            <Text
              style={[
                styles.barangayText,
                barangay === item && styles.selectedBarangayText,
              ]}
            >
              {item}
            </Text>

            {barangay === item && (
              <Feather name="check-circle" size={18} color="#25A5FE" />
            )}
          </TouchableOpacity>
        ))}
      </ScrollView>
    </View>
  </View>
</Modal>

{/* Birthdate Calendar Picker */}
{Platform.OS === 'ios' ? (
  <Modal
    visible={showDatePicker}
    transparent
    animationType="fade"
    onRequestClose={() => setShowDatePicker(false)}
  >
    <View style={styles.modalOverlay}>
      <View style={styles.datePickerContent}>
        <View style={styles.modalHeader}>
          <Text style={styles.modalTitle}>Select Birthdate</Text>

          <TouchableOpacity
            onPress={() => setShowDatePicker(false)}
          >
            <Feather name="x" size={23} color="#64748B" />
          </TouchableOpacity>
        </View>

<DateTimePicker
  value={tempBirthdate}
  mode="date"
  display="inline"
  themeVariant="light"
  accentColor="#25A5FE"
  textColor="#1E293B"
  maximumDate={new Date()}
  onChange={(_, selectedDate) => {
    if (selectedDate) {
      setTempBirthdate(selectedDate);
    }
  }}
  style={{
    width: '100%',
    height: 350,
  }}
/>

        <TouchableOpacity
          style={styles.saveButton}
          onPress={() => {
            setBirthdate(formatDate(tempBirthdate));
            setShowDatePicker(false);
          }}
        >
          <Text style={styles.saveButtonText}>Confirm Birthdate</Text>
        </TouchableOpacity>
      </View>
    </View>
  </Modal>
) : (
  showDatePicker && (
    <DateTimePicker
      value={tempBirthdate}
      mode="date"
      display="calendar"
      maximumDate={new Date()}
      onChange={(event, selectedDate) => {
        setShowDatePicker(false);

        if (event.type === 'set' && selectedDate) {
          setBirthdate(formatDate(selectedDate));
        }
      }}
    />
  )
)}
            </KeyboardAvoidingView>
        </SafeAreaView>
    );
}

    // Reusable input component
    const ProfileInput = ({
        label,
        value,
        onChangeText,
        placeholder,
        icon,
        keyboardType = 'default',
        multiline = false,
    }: any) => (
        <View style={styles.inputGroup}>
            <Text style={styles.inputLabel}>{label}</Text>

            <View style={[
                styles.inputWrapper,
                multiline && styles.multilineWrapper,
            ]}>
                <Feather
                    name={icon}
                    size={18}
                    color="#64748B"
                    style={styles.inputIcon}
                />

                <TextInput
                    style={[
                        styles.input,
                        multiline && styles.multilineInput,
                    ]}
                    value={value}
                    onChangeText={onChangeText}
                    placeholder={placeholder}
                    placeholderTextColor="#94A3B8"
                    keyboardType={keyboardType}
                    multiline={multiline}
                    textAlignVertical={multiline ? 'top' : 'center'}
                />
            </View>
        </View>
    );


const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: '#F8FAFC',
    },

    loadingContainer: {
        flex: 1,
        backgroundColor: '#F8FAFC',
        justifyContent: 'center',
        alignItems: 'center',
    },

    loadingText: {
        marginTop: 12,
        color: '#64748B',
        fontSize: 13,
    },

    header: {
        height: 60,
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        paddingHorizontal: 20,
        backgroundColor: '#FFFFFF',
        borderBottomWidth: 1,
        borderBottomColor: '#E2E8F0',
    },

    backButton: {
        width: 42,
        height: 42,
        justifyContent: 'center',
        alignItems: 'flex-start',
    },

    headerTitle: {
        fontSize: 18,
        fontWeight: '700',
        color: '#1E293B',
    },

    scrollContent: {
        padding: 20,
    },

    intro: {
        alignItems: 'center',
        marginBottom: 25,
    },

    avatar: {
        width: 75,
        height: 75,
        borderRadius: 40,
        backgroundColor: '#E0F2FE',
        justifyContent: 'center',
        alignItems: 'center',
        marginBottom: 14,
    },

    introTitle: {
        fontSize: 19,
        fontWeight: '700',
        color: '#1E293B',
        marginBottom: 7,
    },

    introSub: {
        fontSize: 13,
        color: '#64748B',
        textAlign: 'center',
        lineHeight: 20,
        paddingHorizontal: 10,
    },

    formCard: {
        backgroundColor: '#FFFFFF',
        borderRadius: 16,
        padding: 18,
        borderWidth: 1,
        borderColor: '#E2E8F0',
    },

    inputGroup: {
        marginBottom: 20,
    },

    inputLabel: {
        fontSize: 13,
        fontWeight: '700',
        color: '#334155',
        marginBottom: 9,
    },

    inputWrapper: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: '#F8FAFC',
        borderWidth: 1,
        borderColor: '#E2E8F0',
        borderRadius: 10,
        paddingHorizontal: 13,
        minHeight: 50,
    },

    multilineWrapper: {
        alignItems: 'flex-start',
        minHeight: 90,
        paddingTop: 13,
    },

    inputIcon: {
        marginRight: 11,
    },

    input: {
        flex: 1,
        fontSize: 14,
        color: '#1E293B',
        paddingVertical: 12,
    },

    multilineInput: {
        minHeight: 65,
    },

    helperText: {
        fontSize: 11,
        color: '#94A3B8',
        marginTop: -10,
        marginBottom: 5,
    },

    saveButton: {
        height: 52,
        backgroundColor: '#25A5FE',
        borderRadius: 12,
        flexDirection: 'row',
        justifyContent: 'center',
        alignItems: 'center',
        marginTop: 25,
        gap: 9,
    },

    disabledButton: {
        opacity: 0.6,
    },

    saveButtonText: {
        color: '#FFFFFF',
        fontSize: 15,
        fontWeight: '700',
    },

    cancelButton: {
        height: 48,
        justifyContent: 'center',
        alignItems: 'center',
        marginTop: 8,
    },

    cancelButtonText: {
        color: '#64748B',
        fontSize: 14,
        fontWeight: '600',
    },

    // Modal styles
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

datePickerContent: {
  backgroundColor: '#FFFFFF',
  width: '100%',
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
  color: '#25A5FE',
  fontWeight: '700',
},
});
