
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

export default function BasicFirstAidScreen() {
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
                <Text style={styles.headerTitle}>Basic First Aid Guide</Text>
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
                        title="1. Cuts and Wounds"
                        icon="scissors"
                        iconColor="#DC2626"
                        iconBg="#FEE2E2"
                        tips={[
                            "Wash your hands and put on disposable gloves if available.",
                            "Apply firm, direct pressure using clean gauze or cloth to stop bleeding.",
                            "Once bleeding stops, rinse the wound with clean running water and cover it with a sterile dressing.",
                            "Seek medical help for deep wounds, embedded objects, or severe bleeding."
                        ]}
                    />

                    <PhaseCard
                        title="2. Burns and Scalds"
                        icon="alert-circle"
                        iconColor="#EA580C"
                        iconBg="#FFEDD5"
                        tips={[
                            "Cool the burn under cool or lukewarm running water for 20 minutes.",
                            "Remove jewelry or loose clothing near the burn, but do not pull off anything stuck to the skin.",
                            "Cover loosely with a sterile, non-fluffy dressing or clean cling film.",
                            "Never apply ice, toothpaste, butter, or oil to a burn.",
                            "Seek urgent medical attention for large, deep, or serious burns."
                        ]}
                    />

                    <PhaseCard
                        title="3. Sprains and Strains"
                        icon="activity"
                        iconColor="#2563EB"
                        iconBg="#DBEAFE"
                        tips={[
                            "Stop the activity and rest the injured area.",
                            "Apply a cold pack wrapped in cloth for up to 20 minutes at a time.",
                            "Use gentle compression and elevate the injured limb if comfortable.",
                            "Seek medical attention if there is severe pain, deformity, or inability to move or bear weight."
                        ]}
                    />

                    <PhaseCard
                        title="4. Nosebleeds"
                        icon="droplet"
                        iconColor="#DC2626"
                        iconBg="#FEE2E2"
                        tips={[
                            "Sit upright and lean forward slightly.",
                            "Pinch the soft part of the nose just above the nostrils.",
                            "Maintain continuous pressure for 10 to 15 minutes while breathing through the mouth.",
                            "Do not tilt your head backward or lie flat.",
                            "Seek urgent care if bleeding is heavy or does not stop after 20 minutes of proper pressure."
                        ]}
                    />

                    <PhaseCard
                        title="5. Choking"
                        icon="alert-triangle"
                        iconColor="#D97706"
                        iconBg="#FEF3C7"
                        tips={[
                            "If the person can cough or speak, encourage them to keep coughing.",
                            "If they cannot speak, breathe, or cough effectively, call emergency services.",
                            "For adults and children over one year, give up to 5 firm back blows followed by up to 5 abdominal thrusts.",
                            "Repeat until the object comes out or the person becomes unresponsive.",
                            "For infants under one year, use 5 back blows and 5 chest thrusts. Never use abdominal thrusts."
                        ]}
                    />

                    <PhaseCard
                        title="6. Fainting"
                        icon="user"
                        iconColor="#7C3AED"
                        iconBg="#EDE9FE"
                        tips={[
                            "Help the person lie flat on their back.",
                            "Raise their legs slightly if there is no suspected injury.",
                            "Loosen tight clothing and allow fresh air.",
                            "Check their breathing and responsiveness.",
                            "If they do not wake quickly, have difficulty breathing, or have chest pain, call emergency services."
                        ]}
                    />

                    <PhaseCard
                        title="7. Heat Exhaustion"
                        icon="sun"
                        iconColor="#CA8A04"
                        iconBg="#FEF9C3"
                        tips={[
                            "Move the person to a cool or shaded area.",
                            "Have them rest and loosen unnecessary clothing.",
                            "Cool their body with cool water, wet cloths, or a fan.",
                            "If fully alert and able to swallow, give cool water in small sips.",
                            "If they become confused, collapse, or lose consciousness, suspect heatstroke and seek emergency help immediately."
                        ]}
                    />

                    <PhaseCard
                        title="8. Suspected Fractures"
                        icon="activity"
                        iconColor="#475569"
                        iconBg="#E2E8F0"
                        tips={[
                            "Keep the injured person still and avoid unnecessary movement.",
                            "Support the injured area in the position found.",
                            "Apply a wrapped cold pack to help reduce swelling.",
                            "Do not attempt to straighten the bone or push a protruding bone back into place.",
                            "Seek immediate medical attention, especially for open fractures or severe injuries."
                        ]}
                    />

                    <PhaseCard
                        title="9. Electric Shock"
                        icon="zap"
                        iconColor="#CA8A04"
                        iconBg="#FEF9C3"
                        tips={[
                            "Do not touch the person while they are still in contact with an electrical source.",
                            "Switch off the power supply or disconnect it safely if possible.",
                            "Call emergency services and check the person's breathing once the area is safe.",
                            "If they are unresponsive and not breathing normally, begin CPR and use an AED if available.",
                            "All significant electric shocks require medical assessment."
                        ]}
                    />

                    <PhaseCard
                        title="10. Seizures"
                        icon="activity"
                        iconColor="#7C3AED"
                        iconBg="#EDE9FE"
                        tips={[
                            "Stay calm and move nearby dangerous objects away.",
                            "Protect the person's head with something soft.",
                            "Do not restrain them or put anything inside their mouth.",
                            "When the seizure stops, check their breathing and place them on their side if breathing normally.",
                            "Call emergency services if the seizure lasts 5 minutes or longer, repeats, or is their first seizure."
                        ]}
                    />

                    <PhaseCard
                        title="11. Poisoning"
                        icon="alert-octagon"
                        iconColor="#DC2626"
                        iconBg="#FEE2E2"
                        tips={[
                            "Move the person away from the source of exposure if it is safe.",
                            "Call emergency services or seek immediate medical advice.",
                            "Do not induce vomiting or give food, drinks, or home remedies unless instructed by a medical professional.",
                            "Keep the product container or label to help identify the substance.",
                            "For chemical exposure to the skin or eyes, rinse with plenty of clean running water."
                        ]}
                    />

                    <PhaseCard
                        title="12. Cardiac Arrest and CPR"
                        icon="heart"
                        iconColor="#DC2626"
                        iconBg="#FEE2E2"
                        tips={[
                            "Check if the person responds and is breathing normally.",
                            "If unresponsive and not breathing normally or only gasping, call emergency services and ask someone to get an AED.",
                            "Place both hands in the center of the chest and push hard and fast.",
                            "For adults, perform 30 chest compressions followed by 2 rescue breaths if trained and willing.",
                            "Continue CPR until help arrives, the person shows signs of life, or you are unable to continue.",
                            "Follow the AED voice instructions when it becomes available."
                        ]}
                    />

                    {/* Important Reminder */}
                    <View style={styles.card}>
                        <View style={styles.cardHeader}>
                            <View style={[styles.iconBox, { backgroundColor: '#FEE2E2' }]}>
                                <Feather name="alert-triangle" size={22} color="#DC2626" />
                            </View>
                            <Text style={styles.cardTitle}>Important Safety Reminders</Text>
                        </View>

                        <View style={styles.tipRow}>
                            <View style={styles.bulletPoint} />
                            <Text style={styles.tipText}>
                                Always check that the surrounding area is safe before approaching an injured person.
                            </Text>
                        </View>

                        <View style={styles.tipRow}>
                            <View style={styles.bulletPoint} />
                            <Text style={styles.tipText}>
                                Call emergency services for life-threatening injuries or serious medical emergencies.
                            </Text>
                        </View>

                        <View style={styles.tipRow}>
                            <View style={styles.bulletPoint} />
                            <Text style={styles.tipText}>
                                Wear disposable gloves when handling blood or bodily fluids.
                            </Text>
                        </View>

                        <View style={styles.tipRow}>
                            <View style={styles.bulletPoint} />
                            <Text style={styles.tipText}>
                                Never perform procedures beyond your level of training.
                            </Text>
                        </View>

                        <View style={styles.tipRow}>
                            <View style={styles.bulletPoint} />
                            <Text style={styles.tipText}>
                                Attend a certified first aid and CPR training course to learn proper techniques.
                            </Text>
                        </View>
                    </View>

                </ScrollView>
            </Animated.View>
        </SafeAreaView>
    );
}

// Reusable Card Component
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
