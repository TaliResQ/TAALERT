
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

export default function TaalAlertLevelsScreen() {
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
            {/* Header Area */}
            <View style={styles.header}>
                <TouchableOpacity
                    style={styles.backBtn}
                    onPress={() => router.back()}
                >
                    <Feather name="arrow-left" size={20} color="#25A5FE" />
                </TouchableOpacity>
                <Text style={styles.headerTitle}>
                    Taal Alert Levels
                </Text>
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

                    {/* Introduction */}
                    <View style={styles.card}>
                        <Text style={styles.cardTitle}>
                            Understanding Taal's 5 Alert Levels
                        </Text>

                        <Text style={styles.tipText}>
                            PHIVOLCS uses five alert levels to communicate
                            the condition of Taal Volcano and the possible
                            danger it poses to nearby communities.
                        </Text>

                        <View style={styles.tipRow}>
                            <View style={styles.bulletPoint} />
                            <Text style={styles.tipText}>
                                Alert Level 1 indicates low-level volcanic unrest.
                            </Text>
                        </View>

                        <View style={styles.tipRow}>
                            <View style={styles.bulletPoint} />
                            <Text style={styles.tipText}>
                                Alert Level 5 indicates a highly hazardous
                                eruption is in progress.
                            </Text>
                        </View>

                        <Text style={[styles.tipText, { marginTop: 10 }]}>
                            As the alert level increases, the potential danger
                            becomes greater and stricter safety measures
                            may be required.
                        </Text>
                    </View>

                    {/* Alert Level 1 */}
                    <PhaseCard
                        title="Alert Level 1 – Low-Level Unrest"
                        icon="activity"
                        iconColor="#CA8A04"
                        iconBg="#FEF9C3"
                        tips={[
                            "Taal Volcano is showing signs of abnormal activity, but no major eruption is imminent.",
                            "Possible signs include volcanic earthquakes, changes in gas emissions, and unusual volcanic activity.",
                            "Sudden steam-driven or gas-driven explosions may still occur.",
                            "Avoid entering Taal Volcano Island, which is a Permanent Danger Zone (PDZ).",
                            "Stay informed and monitor official PHIVOLCS announcements."
                        ]}
                    />

                    {/* Alert Level 2 */}
                    <PhaseCard
                        title="Alert Level 2 – Increasing Unrest"
                        icon="trending-up"
                        iconColor="#EA580C"
                        iconBg="#FFEDD5"
                        tips={[
                            "Volcanic activity is increasing, suggesting possible movement of magma beneath the volcano.",
                            "More volcanic earthquakes, increased gas emissions, and ground deformation may be observed.",
                            "The possibility of an eruption becomes higher, although an eruption is not necessarily imminent.",
                            "Entry into Taal Volcano Island remains prohibited.",
                            "Communities in identified high-risk areas should prepare for possible evacuation."
                        ]}
                    />

                    {/* Alert Level 3 */}
                    <PhaseCard
                        title="Alert Level 3 – Intensified or Magmatic Unrest"
                        icon="alert-triangle"
                        iconColor="#DC2626"
                        iconBg="#FEE2E2"
                        tips={[
                            "Magmatic activity is intensifying, and an explosive eruption may be imminent.",
                            "Volcanic earthquakes, ground swelling, increased gas emissions, and initial eruptive activity may occur.",
                            "Ashfall, ballistic projectiles, and other volcanic hazards may affect nearby communities.",
                            "Evacuation of Taal Volcano Island, Taal Lake, and identified lakeshore communities may be required.",
                            "Follow evacuation instructions immediately and avoid returning to restricted areas."
                        ]}
                    />

                    {/* Alert Level 4 */}
                    <PhaseCard
                        title="Alert Level 4 – Hazardous Eruption Imminent"
                        icon="alert-octagon"
                        iconColor="#B91C1C"
                        iconBg="#FEE2E2"
                        tips={[
                            "A strong explosive eruption is taking place or may occur at any time.",
                            "Intense volcanic earthquakes, continuous tremors, ash explosions, and volcanic lightning may occur.",
                            "Dangerous volcanic hazards may include widespread ashfall, ballistic fragments, lava flows, and pyroclastic density currents.",
                            "Communities within identified worst-case volcanic hazard zones must evacuate.",
                            "Follow local government evacuation orders and proceed to designated evacuation centers."
                        ]}
                    />

                    {/* Alert Level 5 */}
                    <PhaseCard
                        title="Alert Level 5 – Highly Hazardous Eruption in Progress"
                        icon="alert-circle"
                        iconColor="#FFFFFF"
                        iconBg="#991B1B"
                        tips={[
                            "A highly hazardous volcanic eruption is already in progress.",
                            "Violent explosions may produce tall ash columns, widespread ashfall, and dangerous pyroclastic density currents or base surges.",
                            "Volcanic tsunamis, ground fissures, lahars, and other life-threatening hazards may affect surrounding communities.",
                            "Additional communities may be ordered to evacuate depending on the eruption and prevailing conditions.",
                            "Stay inside designated safe areas or evacuation centers and strictly follow government instructions.",
                            "Never attempt to return to affected or restricted areas until authorities declare it safe."
                        ]}
                    />

                    {/* Important Reminder */}
                    <View style={styles.card}>
                        <View style={styles.cardHeader}>
                            <View style={[styles.iconBox, {
                                backgroundColor: '#DBEAFE'
                            }]}>
                                <Feather
                                    name="info"
                                    size={22}
                                    color="#2563EB"
                                />
                            </View>
                            <Text style={styles.cardTitle}>
                                Important Safety Reminder
                            </Text>
                        </View>

                        <View>
                            {[
                                "The entire Taal Volcano Island is a Permanent Danger Zone (PDZ). Entry is prohibited regardless of the alert level.",
                                "Alert levels may change depending on PHIVOLCS monitoring results.",
                                "Evacuation zones and restrictions depend on official hazard assessments and local government announcements.",
                                "Always follow verified updates from PHIVOLCS, NDRRMC, and your local Disaster Risk Reduction and Management Office."
                            ].map((tip, index) => (
                                <View key={index} style={styles.tipRow}>
                                    <View style={styles.bulletPoint} />
                                    <Text style={styles.tipText}>{tip}</Text>
                                </View>
                            ))}
                        </View>
                    </View>

                    {/* Official Source */}
                    <View style={styles.card}>
                        <View style={styles.cardHeader}>
                            <View style={[styles.iconBox, {
                                backgroundColor: '#D1FAE5'
                            }]}>
                                <Feather
                                    name="book-open"
                                    size={22}
                                    color="#059669"
                                />
                            </View>
                            <Text style={styles.cardTitle}>
                                Official Reference
                            </Text>
                        </View>

                        <Text style={styles.tipText}>
                            Information adapted from the Philippine Institute
                            of Volcanology and Seismology (PHIVOLCS).
                        </Text>

                        <Text style={[styles.tipText, {
                            color: '#2563EB',
                            marginTop: 10
                        }]}>
                            www.phivolcs.dost.gov.ph
                        </Text>
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
