
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

export default function EvacuationPlanScreen() {
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
                <Text style={styles.headerTitle}>Evacuation Plan Guide</Text>
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
                        title="1. Know Your Evacuation Routes"
                        icon="map"
                        iconColor="#2563EB"
                        iconBg="#DBEAFE"
                        tips={[
                            "Identify the safest evacuation routes from your home, school, or workplace.",
                            "Know alternative routes in case roads become flooded, blocked, or damaged.",
                            "Familiarize yourself with evacuation signs and designated evacuation centers."
                        ]}
                    />

                    <PhaseCard
                        title="2. Identify the Nearest Evacuation Center"
                        icon="map-pin"
                        iconColor="#059669"
                        iconBg="#D1FAE5"
                        tips={[
                            "Find the nearest officially designated evacuation center in your barangay.",
                            "Check whether the facility is suitable for your household's needs.",
                            "Use the Evacuation section in TaliResQ to access available evacuation information."
                        ]}
                    />

                    <PhaseCard
                        title="3. Prepare an Emergency Go Bag"
                        icon="briefcase"
                        iconColor="#D97706"
                        iconBg="#FEF3C7"
                        tips={[
                            "Prepare essential supplies such as drinking water, ready-to-eat food, medicines, clothing, and hygiene items.",
                            "Include flashlights, power banks, important documents, and emergency contact information.",
                            "Keep your go bag in an accessible location near an exit."
                        ]}
                    />

                    <PhaseCard
                        title="4. Establish a Family Communication Plan"
                        icon="phone"
                        iconColor="#0284C7"
                        iconBg="#E0F2FE"
                        tips={[
                            "Agree on how family members will communicate during an emergency.",
                            "Choose a meeting point in case household members become separated.",
                            "Identify an out-of-area contact person whom everyone can contact."
                        ]}
                    />

                    <PhaseCard
                        title="5. Monitor Official Emergency Advisories"
                        icon="radio"
                        iconColor="#7C3AED"
                        iconBg="#EDE9FE"
                        tips={[
                            "Monitor announcements from PAGASA, PHIVOLCS, NDRRMC, and your local government.",
                            "Pay attention to evacuation orders, weather warnings, and hazard updates.",
                            "Avoid relying on unverified information from social media."
                        ]}
                    />

                    <PhaseCard
                        title="6. Secure Your Home Before Leaving"
                        icon="home"
                        iconColor="#475569"
                        iconBg="#E2E8F0"
                        tips={[
                            "Switch off electricity, gas, and water supplies if it is safe to do so.",
                            "Unplug electrical appliances and secure doors and windows if time permits.",
                            "Do not delay evacuation just to secure belongings."
                        ]}
                    />

                    <PhaseCard
                        title="7. Follow Official Evacuation Orders"
                        icon="alert-triangle"
                        iconColor="#DC2626"
                        iconBg="#FEE2E2"
                        tips={[
                            "Evacuate immediately when instructed by local authorities.",
                            "Follow designated evacuation routes and instructions from emergency responders.",
                            "Do not wait until conditions become dangerous before leaving."
                        ]}
                    />

                    <PhaseCard
                        title="8. Assist Children, Older Adults, and PWDs"
                        icon="users"
                        iconColor="#DB2777"
                        iconBg="#FCE7F3"
                        tips={[
                            "Assign a family member to assist children, older adults, and persons with disabilities.",
                            "Prepare necessary medicines, mobility aids, and assistive devices.",
                            "Arrange accessible transportation and additional assistance when needed."
                        ]}
                    />

                    <PhaseCard
                        title="9. Evacuate Safely 
                        and Avoid Hazardous Areas"
                        icon="navigation"
                        iconColor="#EA580C"
                        iconBg="#FFEDD5"
                        tips={[
                            "Walk calmly and avoid pushing or overcrowding during evacuation.",
                            "Never walk or drive through floodwaters.",
                            "Stay away from damaged structures, fallen power lines, landslide areas, and restricted zones.",
                            "Follow the safest route recommended by local authorities."
                        ]}
                    />

                    <PhaseCard
                        title="10. Bring and Account for Family Members"
                        icon="users"
                        iconColor="#0891B2"
                        iconBg="#CFFAFE"
                        tips={[
                            "Make sure all household members are accounted for before leaving, if safe to do so.",
                            "Keep children close and establish a buddy system.",
                            "Bring pets when possible and follow designated pet evacuation arrangements."
                        ]}
                    />

                    <PhaseCard
                        title="11. Register at the Evacuation Center"
                        icon="clipboard"
                        iconColor="#4F46E5"
                        iconBg="#E0E7FF"
                        tips={[
                            "Proceed to the designated registration or information desk upon arrival.",
                            "Provide the necessary household information to evacuation center personnel.",
                            "Inform responders about missing family members, medical conditions, or special assistance needs."
                        ]}
                    />

                    <PhaseCard
                        title="12. Wait for the Official All-Clear"
                        icon="check-circle"
                        iconColor="#15803D"
                        iconBg="#DCFCE7"
                        tips={[
                            "Remain at the evacuation center until authorities declare it safe to return.",
                            "Do not enter damaged buildings or hazardous areas.",
                            "Check for structural damage, electrical hazards, and contaminated water before returning home.",
                            "Continue monitoring official announcements and follow local safety instructions."
                        ]}
                    />

                    {/* Important Reminder */}
                    <View style={styles.card}>
                        <View style={styles.cardHeader}>
                            <View style={[styles.iconBox, { backgroundColor: '#FEE2E2' }]}>
                                <Feather name="alert-triangle" size={22} color="#DC2626" />
                            </View>
                            <Text style={styles.cardTitle}>Important Evacuation Reminders</Text>
                        </View>

                        <View style={styles.tipRow}>
                            <View style={styles.bulletPoint} />
                            <Text style={styles.tipText}>
                                Never ignore an official evacuation order, even if the danger is not yet visible.
                            </Text>
                        </View>

                        <View style={styles.tipRow}>
                            <View style={styles.bulletPoint} />
                            <Text style={styles.tipText}>
                                Keep your mobile phone charged and emergency contacts readily accessible.
                            </Text>
                        </View>

                        <View style={styles.tipRow}>
                            <View style={styles.bulletPoint} />
                            <Text style={styles.tipText}>
                                Never return to an evacuation zone until authorities declare it safe.
                            </Text>
                        </View>

                        <View style={styles.tipRow}>
                            <View style={styles.bulletPoint} />
                            <Text style={styles.tipText}>
                                Prioritize lives and safety over personal belongings.
                            </Text>
                        </View>

                        <View style={styles.tipRow}>
                            <View style={styles.bulletPoint} />
                            <Text style={styles.tipText}>
                                Follow instructions from barangay officials, MDRRMO personnel, and emergency responders.
                            </Text>
                        </View>
                    </View>

                </ScrollView>
            </Animated.View>
        </SafeAreaView>
    );
}

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

            <Text style={[styles.cardTitle, { flex: 1, flexWrap: 'wrap' }]}>
                {title}
            </Text>
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
