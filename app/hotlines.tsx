
import { Feather } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { useEffect, useRef } from 'react';
import {
    Alert,
    Animated,
    Image,
    Linking,
    SafeAreaView,
    ScrollView,
    Text,
    TouchableOpacity,
    View,
} from 'react-native';
import { HotlineStyles as styles } from '../constants/theme';


const hotlines = [
    { title: 'MDRRMC Talisay', number: '0939 921 6233', logo: require('../assets/images/hotlines/MDRRMO_Talisay.jpeg'), color: '#DC2626', bg: '#FEE2E2' },
    { title: 'Red Cross', number: '0968 240 3829', logo: require('../assets/images/hotlines/Red Cross.jpeg'), color: '#2563EB', bg: '#DBEAFE' },
    { title: 'PNP Talisay', number: '0917 353 4074', logo: require('../assets/images/hotlines/PNP Talisay.jpeg'), color: '#EA580C', bg: '#FFEDD5' },
    { title: 'PHIVOLCS', number: '0960 312 7912', logo: require('../assets/images/hotlines/PHIVOLCS.jpeg'), color: '#DC2626', bg: '#FEE2E2' },
    { title: 'BFP Talisay', number: '0976 178 2744', logo: require('../assets/images/hotlines/BFP Talisay.jpeg'), color: '#059669', bg: '#D1FAE5' },
    { title: 'Coast Guard Talisay', number: '0948 300 5507', logo: require('../assets/images/hotlines/Coast Guard Talisay.jpeg'), color: '#0891B2', bg: '#CFFAFE' },
];

export default function EruptionGuideScreen() {
    const router = useRouter();
    const fadeAnim = useRef(new Animated.Value(0)).current;
    const slideUpAnim = useRef(new Animated.Value(30)).current;

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
    }, []);

    const callHotline = async (number: string) => {
        const phoneUrl = `tel:${number}`;

        try {
            const supported = await Linking.canOpenURL(phoneUrl);

            if (supported) {
                await Linking.openURL(phoneUrl);
            } else {
                Alert.alert('Unable to Call', 'Your device does not support phone calls.');
            }
        } catch {
            Alert.alert('Error', 'Unable to open the phone dialer.');
        }
    };

    return (
        <SafeAreaView style={styles.container}>
            <View style={styles.header}>
                <TouchableOpacity
                    style={styles.backBtn}
                    onPress={() => router.back()}
                >
                    <Feather name="arrow-left" size={20} color="#25A5FE" />
                </TouchableOpacity>

                <Text style={styles.headerTitle}>Emergency Hotlines</Text>
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
                >
                    <Text style={styles.sectionDescription}>
                        Tap a hotline to contact the corresponding emergency service.
                    </Text>

                    <View style={styles.hotlineGrid}>
                        {hotlines.map((hotline, index) => (
                            <TouchableOpacity
                                key={index}
                                style={styles.hotlineCard}
                                activeOpacity={0.7}
                                onPress={() => callHotline(hotline.number)}
                            >
                                <View style={[
                                    styles.hotlineIcon,
                                    { backgroundColor: hotline.bg }
                                ]}>
                                    <Image
                                        source={hotline.logo}
                                        style={styles.hotlineLogo}
                                        resizeMode="contain"
                                    />
                                </View>

                                <Text style={styles.hotlineTitle}>
                                    {hotline.title}
                                </Text>

                                <Text style={styles.hotlineNumber}>
                                    {hotline.number}
                                </Text>

                                <View style={styles.callIndicator}>
                                    <Feather name="phone" size={13} color="#FFFFFF" />
                                    <Text style={styles.callText}>Call</Text>
                                </View>
                            </TouchableOpacity>
                        ))}
                    </View>
                </ScrollView>
            </Animated.View>
        </SafeAreaView>
    );
}
