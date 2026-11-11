import { Feather } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { useEffect, useRef } from 'react';
import {
    Animated,
    SafeAreaView,
    ScrollView,
    Text,
    TouchableOpacity,
    View
} from 'react-native';
import { GuideStyles as styles } from '../constants/theme';

export default function EmergencyGoBagScreen() {
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

    return (
        <SafeAreaView style={styles.container}>
            {/* Header Area with Back Button */}
            <View style={styles.header}>
                <TouchableOpacity
                    style={styles.backBtn}
                    onPress={() => router.back()}
                >
                    <Feather name="arrow-left" size={20} color="#25A5FE" />
                </TouchableOpacity>
                <Text style={styles.headerTitle}>Emergency Go Bag</Text>
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

                    <PhaseCard
                        title="1. Drinking Water"
                        icon="droplet"
                        iconColor="#2563EB"
                        iconBg="#DBEAFE"
                        tips={[
                            "Prepare at least 3 liters of drinking water per person per day for at least three days.",
                            "Use sealed and clean water containers."
                        ]}
                    />

                    <PhaseCard
                        title="2. Ready-to-Eat Food"
                        icon="package"
                        iconColor="#D97706"
                        iconBg="#FEF3C7"
                        tips={[
                            "Pack at least three days' worth of non-perishable food such as canned goods, biscuits, energy bars, and ready-to-eat meals.",
                            "Include a manual can opener."
                        ]}
                    />

                    <PhaseCard
                        title="3. First Aid Kit"
                        icon="briefcase"
                        iconColor="#DC2626"
                        iconBg="#FEE2E2"
                        tips={[
                            "Include bandages, gauze, antiseptic, medical tape, alcohol or disinfectant, and basic first aid supplies.",
                            "Check supplies regularly and replace expired items."
                        ]}
                    />

                    <PhaseCard
                        title="4. Medicines and Prescriptions"
                        icon="activity"
                        iconColor="#059669"
                        iconBg="#D1FAE5"
                        tips={[
                            "Bring maintenance medicines, prescribed medications, and basic over-the-counter medicines.",
                            "Keep medicines in their original packaging and include copies of prescriptions."
                        ]}
                    />

                    <PhaseCard
                        title="5. Flashlight and Extra Batteries"
                        icon="zap"
                        iconColor="#CA8A04"
                        iconBg="#FEF9C3"
                        tips={[
                            "Pack a working flashlight or rechargeable emergency light.",
                            "Bring extra batteries or a fully charged backup light for power interruptions."
                        ]}
                    />

                    <PhaseCard
                        title="6. Battery-Powered or Hand-Crank Radio"
                        icon="radio"
                        iconColor="#7C3AED"
                        iconBg="#EDE9FE"
                        tips={[
                            "Use a battery-powered or hand-crank radio to receive official weather alerts and emergency announcements.",
                            "Keep extra batteries available."
                        ]}
                    />

                    <PhaseCard
                        title="7. Mobile Phone and Power Bank"
                        icon="smartphone"
                        iconColor="#0284C7"
                        iconBg="#E0F2FE"
                        tips={[
                            "Keep your mobile phone fully charged and bring a fully charged power bank.",
                            "Pack charging cables and save important emergency contact numbers."
                        ]}
                    />

                    <PhaseCard
                        title="8. Important Documents"
                        icon="file-text"
                        iconColor="#4F46E5"
                        iconBg="#E0E7FF"
                        tips={[
                            "Keep photocopies of IDs, birth certificates, medical records, insurance documents, and other important papers.",
                            "Place them in a waterproof and sealed plastic envelope."
                        ]}
                    />

                    <PhaseCard
                        title="9. Clothes and Extra Blankets"
                        icon="shirt"
                        iconColor="#DB2777"
                        iconBg="#FCE7F3"
                        tips={[
                            "Pack extra clothes, underwear, socks, a raincoat, and a lightweight blanket.",
                            "Choose comfortable, weather-appropriate clothing and place everything in waterproof bags."
                        ]}
                    />

                    <PhaseCard
                        title="10. Hygiene and Sanitation Supplies"
                        icon="droplet"
                        iconColor="#0891B2"
                        iconBg="#CFFAFE"
                        tips={[
                            "Include soap, toothbrushes, toothpaste, alcohol or hand sanitizer, tissue, wet wipes, and sanitary supplies.",
                            "Bring extra face masks and personal hygiene items."
                        ]}
                    />

                    <PhaseCard
                        title="11. Emergency Tools and Whistle"
                        icon="tool"
                        iconColor="#475569"
                        iconBg="#E2E8F0"
                        tips={[
                            "Pack a whistle, multipurpose tool, sturdy gloves, duct tape, and a small emergency knife.",
                            "These can help you signal for assistance and handle basic emergency tasks."
                        ]}
                    />

                    <PhaseCard
                        title="12. Cash and Emergency Contacts"
                        icon="credit-card"
                        iconColor="#15803D"
                        iconBg="#DCFCE7"
                        tips={[
                            "Bring small denominations of cash in case ATMs and electronic payment services are unavailable.",
                            "Keep a written list of family members, emergency hotlines, and important contact numbers."
                        ]}
                    />

                    {/* Additional Reminder */}
                    <View style={styles.card}>
                        <View style={styles.cardHeader}>
                            <View style={[styles.iconBox, { backgroundColor: '#FEF3C7' }]}>
                                <Feather name="alert-circle" size={22} color="#D97706" />
                            </View>
                            <Text style={styles.cardTitle}>Important Reminder</Text>
                        </View>

                        <View style={styles.tipRow}>
                            <View style={styles.bulletPoint} />
                            <Text style={styles.tipText}>
                                Prepare one go bag for each household member whenever possible.
                            </Text>
                        </View>

                        <View style={styles.tipRow}>
                            <View style={styles.bulletPoint} />
                            <Text style={styles.tipText}>
                                Store your go bag in an accessible location near an exit.
                            </Text>
                        </View>

                        <View style={styles.tipRow}>
                            <View style={styles.bulletPoint} />
                            <Text style={styles.tipText}>
                                Check your supplies every six months and replace expired food, water, and medicines.
                            </Text>
                        </View>

                        <View style={styles.tipRow}>
                            <View style={styles.bulletPoint} />
                            <Text style={styles.tipText}>
                                Customize your bag for infants, older adults, persons with disabilities, and pets.
                            </Text>
                        </View>
                    </View>

                </ScrollView>
            </Animated.View>
        </SafeAreaView>
    );
}

// Reusable Card Component for Emergency Go Bag Items
const PhaseCard = ({
    title,
    icon,
    iconColor,
    iconBg,
    tips
}: any) => (
    <View style={styles.card}>
        <View style={styles.cardHeader}>
            <View style={[styles.iconBox, { backgroundColor: iconBg }]}>
                <Feather name={icon} size={22} color={iconColor} />
            </View>
            <Text style={styles.cardTitle}>{title}</Text>
        </View>

        <View>
            {tips.map((tip: string, index: number) => (
                <View key={index} style={styles.tipRow}>
                    <View style={styles.bulletPoint} />
                    <Text style={styles.tipText}>{tip}</Text>
                </View>
            ))}
        </View>
    </View>
);