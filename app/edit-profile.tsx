
import { supabase } from '@/src/services/supabase';
import { Feather } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import {
    ActivityIndicator,
    Alert,
    KeyboardAvoidingView,
    Platform,
    SafeAreaView,
    ScrollView,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    View,
} from 'react-native';

export default function EditProfileScreen() {
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);

    const [fullname, setFullname] = useState('');
    const [contactNumber, setContactNumber] = useState('');
    const [barangay, setBarangay] = useState('');
    const [address, setAddress] = useState('');
    const [birthdate, setBirthdate] = useState('');

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

                        <ProfileInput
                            label="Barangay"
                            value={barangay}
                            onChangeText={setBarangay}
                            placeholder="Enter your barangay"
                            icon="map-pin"
                        />

                        <ProfileInput
                            label="Complete Address"
                            value={address}
                            onChangeText={setAddress}
                            placeholder="House number, street, subdivision..."
                            icon="home"
                            multiline
                        />

                        <ProfileInput
                            label="Birthdate"
                            value={birthdate}
                            onChangeText={setBirthdate}
                            placeholder="YYYY-MM-DD (e.g. 2004-06-07)"
                            icon="calendar"
                        />

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
});
