
import { Feather } from '@expo/vector-icons';
import * as ImagePicker from 'expo-image-picker';
import { useRouter } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import {
    ActivityIndicator,
    Alert,
    Animated,
    Image,
    Modal,
    SafeAreaView,
    ScrollView,
    Text,
    TextInput,
    TouchableOpacity,
    View,
} from 'react-native';
import 'react-native-url-polyfill/auto';

import BottomNavbar from '../components/BottomNavbar';
import { ReportStyles as styles } from '../constants/theme';
import { supabase } from '../src/services/supabase';

const REPORT_TYPES = [
    'Flood',
    'Fire',
    'Landslide',
    'Earthquake',
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

export default function ReportScreen() {
    const router = useRouter();

    const [selectedType, setSelectedType] = useState('Flood');
    const [location, setLocation] = useState('');
    const [description, setDescription] = useState('');
    const [loading, setLoading] = useState(false);
    const [successVisible, setSuccessVisible] = useState(false);
    const [showBarangayModal, setShowBarangayModal] = useState(false);

    const [image, setImage] = useState<{
        uri: string;
        ext: string;
    } | null>(null);

    const scaleAnim = useRef(new Animated.Value(0)).current;
    const fadeAnim = useRef(new Animated.Value(0)).current;
    const slideUpAnim = useRef(new Animated.Value(30)).current;

    const showSuccessModal = () => {
        setSuccessVisible(true);
        scaleAnim.setValue(0);

        Animated.sequence([
            Animated.spring(scaleAnim, {
                toValue: 1.15,
                friction: 4,
                useNativeDriver: true,
            }),
            Animated.spring(scaleAnim, {
                toValue: 1,
                friction: 6,
                useNativeDriver: true,
            }),
        ]).start();
    };

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
    }, []);

    // Pick an image from gallery
    const pickImage = async () => {
        const { status } =
            await ImagePicker.requestMediaLibraryPermissionsAsync();

        if (status !== 'granted') {
            Alert.alert(
                'Permission Denied',
                'We need permissions to open your gallery.'
            );
            return;
        }

        const result = await ImagePicker.launchImageLibraryAsync({
            mediaTypes: ImagePicker.MediaTypeOptions.Images,
            allowsEditing: true,
            aspect: [4, 3],
            quality: 0.2,
        });

        if (!result.canceled) {
            const uri = result.assets[0].uri;
            const ext = uri.split('.').pop()?.toLowerCase() || 'jpeg';

            setImage({
                uri,
                ext: ext === 'jpg' ? 'jpeg' : ext,
            });
        }
    };

    // Submit report
    const handleSubmit = async () => {
        const {
            data: { user },
        } = await supabase.auth.getUser();

        if (!user) {
            Alert.alert('Error', 'You must be logged in.');
            return;
        }

        if (!location.trim() || !description.trim()) {
            Alert.alert(
                'Missing Information',
                'Please select a barangay and enter a description.'
            );
            return;
        }

        setLoading(true);

        try {
            let photoUrl = null;

            if (image) {
                const fileName = `${Date.now()}.${image.ext}`;

                const response = await fetch(image.uri);
                const blob = await response.blob();

                const { error: uploadError } = await supabase.storage
                    .from('report-images')
                    .upload(fileName, blob, {
                        contentType: `image/${image.ext}`,
                        upsert: true,
                    });

                if (uploadError) {
                    Alert.alert('Storage Error!', uploadError.message);
                    return;
                }

                const { data: publicUrlData } = supabase.storage
                    .from('report-images')
                    .getPublicUrl(fileName);

                photoUrl = publicUrlData.publicUrl;
            }

            const { error: dbError } = await supabase
                .from('incidents')
                .insert([
                    {
                        user_id: user.id,
                        category: selectedType,
                        location: location.trim(),
                        description: description.trim(),
                        photo_url: photoUrl,
                        status: 'pending',
                        resident_status: 'Submitted',
                        latest_update:
                            'Your report has been submitted and is awaiting review.',
                        priority: 'medium',
                    },
                ]);

            if (dbError) {
                Alert.alert('Database Error!', dbError.message);
                console.error('DB Error:', dbError);
                return;
            }

            setLocation('');
            setDescription('');
            setImage(null);
            setSelectedType('Flood');

            showSuccessModal();

        } catch (error: any) {
            Alert.alert(
                'System Error',
                error.message || 'An unexpected error occurred.'
            );
        } finally {
            setLoading(false);
        }
    };

    return (
        <SafeAreaView style={styles.container}>

            {/* Header */}
            <View style={styles.header}>
                <TouchableOpacity
                    style={styles.backBtn}
                    onPress={() => router.replace('/home')}
                >
                    <Feather
                        name="arrow-left"
                        size={22}
                        color="#1E293B"
                    />
                </TouchableOpacity>

                <Text style={styles.headerTitle}>
                    Submit a Report
                </Text>
            </View>

            <Animated.View
                style={{
                    flex: 1,
                    opacity: fadeAnim,
                    transform: [{ translateY: slideUpAnim }],
                }}
            >
                <ScrollView
                    contentContainerStyle={styles.scrollContent}
                    showsVerticalScrollIndicator={false}
                    keyboardShouldPersistTaps="handled"
                >

                    {/* Report Type */}
                    <Text style={styles.label}>TYPE</Text>

                    <View style={styles.typeGrid}>
                        {REPORT_TYPES.map((type) => (
                            <TouchableOpacity
                                key={type}
                                style={[
                                    styles.typeChip,
                                    selectedType === type &&
                                        styles.typeChipActive,
                                ]}
                                onPress={() => setSelectedType(type)}
                            >
                                <Text
                                    style={[
                                        styles.typeChipText,
                                        selectedType === type &&
                                            styles.typeChipTextActive,
                                    ]}
                                >
                                    {type}
                                </Text>
                            </TouchableOpacity>
                        ))}
                    </View>

                    {/* Location Dropdown */}
                    <Text style={styles.label}>LOCATION</Text>

                    <TouchableOpacity
                        style={styles.locationDropdown}
                        onPress={() => setShowBarangayModal(true)}
                        activeOpacity={0.7}
                    >
                        <Feather
                            name="map-pin"
                            size={18}
                            color="#25A5FE"
                            style={{ marginRight: 11 }}
                        />

                        <Text
                            style={{
                                flex: 1,
                                fontSize: 14,
                                color: location ? '#1E293B' : '#94A3B8',
                            }}
                        >
                            {location || 'Select barangay in Talisay'}
                        </Text>

                        <Feather
                            name="chevron-down"
                            size={18}
                            color="#64748B"
                        />
                    </TouchableOpacity>

                    {/* Description */}
                    <Text style={styles.label}>DESCRIPTION</Text>

                    <View style={styles.textAreaContainer}>
                        <TextInput
                            style={styles.textArea}
                            placeholder="Describe what you observed..."
                            placeholderTextColor="#94A3B8"
                            multiline
                            numberOfLines={4}
                            textAlignVertical="top"
                            value={description}
                            onChangeText={setDescription}
                        />
                    </View>

                    {/* Photo */}
                    <Text style={styles.label}>PHOTO</Text>

                    <TouchableOpacity
                        style={[
                            styles.photoBox,
                            image && {
                                padding: 0,
                                overflow: 'hidden',
                            },
                        ]}
                        onPress={pickImage}
                    >
                        {image ? (
                            <>
                                <Image
                                    source={{ uri: image.uri }}
                                    style={{
                                        width: '100%',
                                        height: 150,
                                    }}
                                />

                                <View
                                    style={{
                                        position: 'absolute',
                                        backgroundColor: 'rgba(0,0,0,0.5)',
                                        padding: 8,
                                        borderRadius: 20,
                                    }}
                                >
                                    <Feather
                                        name="edit-2"
                                        size={20}
                                        color="#fff"
                                    />
                                </View>
                            </>
                        ) : (
                            <>
                                <Feather
                                    name="camera"
                                    size={32}
                                    color="#25A5FE"
                                />

                                <Text style={styles.photoText}>
                                    Take photo or upload
                                </Text>
                            </>
                        )}
                    </TouchableOpacity>

                    <View style={{ height: 120 }} />
                </ScrollView>
            </Animated.View>

            {/* Submit Button */}
            <View style={styles.footer}>
                <TouchableOpacity
                    style={styles.submitBtn}
                    onPress={handleSubmit}
                    disabled={loading}
                >
                    {loading ? (
                        <ActivityIndicator color="#fff" />
                    ) : (
                        <Text style={styles.submitBtnText}>
                            Submit Report
                        </Text>
                    )}
                </TouchableOpacity>
            </View>

            {/* Barangay Dropdown Modal */}
            <Modal
                visible={showBarangayModal}
                transparent
                animationType="slide"
                onRequestClose={() => setShowBarangayModal(false)}
            >
                <View style={styles.locationModalOverlay}>
                    <View style={styles.locationModalContent}>

                        <View style={styles.locationModalHeader}>
                            <View>
                                <Text style={styles.locationModalTitle}>
                                    Select Barangay
                                </Text>

                                <Text style={styles.locationModalSubtitle}>
                                    Choose the affected barangay in Talisay
                                </Text>
                            </View>

                            <TouchableOpacity
                                onPress={() => setShowBarangayModal(false)}
                            >
                                <Feather
                                    name="x"
                                    size={23}
                                    color="#64748B"
                                />
                            </TouchableOpacity>
                        </View>

                        <ScrollView
                            showsVerticalScrollIndicator={false}
                            nestedScrollEnabled
                        >
                            {BARANGAYS.map((item) => (
                                <TouchableOpacity
                                    key={item}
                                    style={[
                                        styles.locationOption,
                                        location === item &&
                                            styles.selectedLocation,
                                    ]}
                                    onPress={() => {
                                        setLocation(item);
                                        setShowBarangayModal(false);
                                    }}
                                >
                                    <Text
                                        style={[
                                            styles.locationOptionText,
                                            location === item &&
                                                styles.selectedLocationText,
                                        ]}
                                    >
                                        {item}
                                    </Text>

                                    {location === item && (
                                        <Feather
                                            name="check-circle"
                                            size={18}
                                            color="#25A5FE"
                                        />
                                    )}
                                </TouchableOpacity>
                            ))}
                        </ScrollView>
                    </View>
                </View>
            </Modal>

            {/* Success Modal */}
            <Modal
                visible={successVisible}
                transparent
                animationType="fade"
                onRequestClose={() => setSuccessVisible(false)}
            >
                <View
                    style={{
                        flex: 1,
                        backgroundColor: 'rgba(0,0,0,0.45)',
                        justifyContent: 'center',
                        alignItems: 'center',
                    }}
                >
                    <Animated.View
                        style={{
                            width: 300,
                            backgroundColor: '#fff',
                            borderRadius: 24,
                            padding: 30,
                            alignItems: 'center',
                            transform: [{ scale: scaleAnim }],
                        }}
                    >
                        <View
                            style={{
                                width: 90,
                                height: 90,
                                borderRadius: 45,
                                backgroundColor: '#22C55E',
                                justifyContent: 'center',
                                alignItems: 'center',
                                marginBottom: 20,
                            }}
                        >
                            <Feather
                                name="check"
                                size={50}
                                color="#fff"
                            />
                        </View>

                        <Text
                            style={{
                                fontSize: 24,
                                fontWeight: '700',
                                color: '#0F172A',
                                marginBottom: 8,
                            }}
                        >
                            Report Submitted
                        </Text>

                        <Text
                            style={{
                                textAlign: 'center',
                                color: '#64748B',
                                fontSize: 15,
                                marginBottom: 25,
                                lineHeight: 22,
                            }}
                        >
                            Thank you! Your report has been successfully
                            submitted and will help keep everyone informed.
                        </Text>

                        <TouchableOpacity
                            style={{
                                backgroundColor: '#25A5FE',
                                width: '100%',
                                paddingVertical: 14,
                                borderRadius: 14,
                                alignItems: 'center',
                            }}
                            onPress={() => {
                                setSuccessVisible(false);
                                router.replace('/home');
                            }}
                        >
                            <Text
                                style={{
                                    color: '#fff',
                                    fontWeight: '700',
                                    fontSize: 16,
                                }}
                            >
                                OK
                            </Text>
                        </TouchableOpacity>
                    </Animated.View>
                </View>
            </Modal>

            <BottomNavbar activeTab="plus" />
        </SafeAreaView>
    );
}
