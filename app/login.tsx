import { Feather } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import {
    Animated,
    KeyboardAvoidingView,
    Platform,
    SafeAreaView,
    StatusBar,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    View
} from 'react-native';

import { supabase } from '../src/services/supabase';

const COLORS = {
    background: '#F5F7FA',
    primaryBlue: '#319EFE',
    darkNavy: '#223354',
    textDark: '#1A202C',
    textMuted: '#718096',
    white: '#FFFFFF',
    inputBg: '#FFFFFF',
};

export default function LoginScreen() {
    const router = useRouter();

    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [showPassword, setShowPassword] = useState(false);
    const [loading, setLoading] = useState(false);

    const fadeAnim = useRef(new Animated.Value(0)).current;
    const slideUpAnim = useRef(new Animated.Value(30)).current;
    const [error, setError] = useState('');

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

    const handleLogin = async () => {
        try {
            if (!email.trim() || !password.trim()) {
                setError('Please enter your email and password');
                return;
            }

            setLoading(true);
            setError('');

            const { error } = await supabase.auth.signInWithPassword({
                email: email.trim(),
                password,
            });

            if (error) {
                setError(error.message);
                return;
            }
            // BASTA PAG SUCCESSFUL PUNTA HOME
            router.replace('/home');

        } catch (err) {
            console.log('Catch Error:', err);
            setError('Something went wrong');
        } finally {
            setLoading(false);
        }
    };

    return (
        <SafeAreaView style={styles.container}>
            <StatusBar barStyle="dark-content" />

            <KeyboardAvoidingView
                behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
                style={{ flex: 1 }}
            >
                <Animated.View
                    style={[
                        styles.innerContainer,
                        {
                            opacity: fadeAnim,
                            transform: [{ translateY: slideUpAnim }],
                        },
                    ]}
                >
                    {/* Top Bar / Back Button */}
                    <View style={styles.topBar}>
                        <TouchableOpacity
                            style={styles.backBtn}
                            onPress={() => router.back()}
                        >
                            <Feather name="arrow-left" size={24} color={COLORS.textDark} />
                        </TouchableOpacity>
                    </View>
                    {/* Icon */}
                    <View style={styles.logoContainer}>
                        <View style={styles.iconCircle}>
                            <Feather name="shield" size={40} color={COLORS.primaryBlue} />
                        </View>
                    </View>

                    {/* Header */}
                    <View style={styles.headerContainer}>
                        <Text style={styles.welcomeText}>Welcome Back 👋</Text>
                        <Text style={styles.subText}>
                            Log in to receive real-time disaster alerts and stay safe.
                        </Text>
                    </View>

                    {/* Form */}
                    <View style={styles.formContainer}>
                        {/* Email */}
                        <View style={styles.inputWrapper}>
                            <Feather name="mail" size={20} color={COLORS.textMuted} />
                            <TextInput
                                style={styles.input}
                                placeholder="Email address"
                                placeholderTextColor={COLORS.textMuted}
                                keyboardType="email-address"
                                autoCapitalize="none"
                                value={email}
                                onChangeText={setEmail}
                            />
                        </View>

                        {/* Password */}
                        <View style={styles.inputWrapper}>
                            <Feather name="lock" size={20} color={COLORS.textMuted} />
                            <TextInput
                                style={styles.input}
                                placeholder="Password"
                                placeholderTextColor={COLORS.textMuted}
                                secureTextEntry={!showPassword}
                                value={password}
                                onChangeText={setPassword}
                                autoCapitalize="none"
                            />

                            <TouchableOpacity onPress={() => setShowPassword(!showPassword)}>
                                <Feather
                                    name={showPassword ? 'eye' : 'eye-off'}
                                    size={20}
                                    color={COLORS.textMuted}
                                />
                            </TouchableOpacity>
                        </View>

                        {/* Forgot */}
                        <TouchableOpacity style={styles.forgotPasswordBtn}>
                            <Text style={styles.forgotPasswordText}>Forgot Password?</Text>
                        </TouchableOpacity>

                        {error ? (
                            <Text style={styles.errorText}>{error}</Text>
                        ) : null}

                        {/* Login */}
                        <TouchableOpacity
                            style={styles.loginBtn}
                            onPress={handleLogin}
                            activeOpacity={0.8}
                            disabled={loading}
                        >
                            <Text style={styles.loginBtnText}>
                                {loading ? 'Logging in...' : 'Log In'}
                            </Text>
                        </TouchableOpacity>
                    </View>

                    {/* Signup */}
                    <View style={styles.footerContainer}>
                        <Text style={styles.footerText}>Don't have an account? </Text>

                        <TouchableOpacity onPress={() => router.push('/signup')}>
                            <Text style={styles.signUpText}>Sign up</Text>
                        </TouchableOpacity>
                    </View>
                </Animated.View>
            </KeyboardAvoidingView>
        </SafeAreaView>
    );
}

const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: COLORS.background,
    },
    topBar: {
        position: 'absolute',
        top: Platform.OS === 'android' ? 20 : 0,
        left: 24,
        zIndex: 10,
    },
    backBtn: {
        padding: 8,
        marginLeft: -8,
    },
    errorText: {
        color: 'red',
        textAlign: 'center',
        marginBottom: 16,
        fontSize: 14,
    },
    innerContainer: {
        flex: 1,
        paddingHorizontal: 24,
        justifyContent: 'center',
    },
    logoContainer: {
        alignItems: 'center',
        marginBottom: 32,
    },
    iconCircle: {
        width: 80,
        height: 80,
        borderRadius: 40,
        backgroundColor: COLORS.white,
        justifyContent: 'center',
        alignItems: 'center',
        elevation: 5,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.1,
        shadowRadius: 4,
    },
    headerContainer: {
        marginBottom: 40,
    },
    welcomeText: {
        fontSize: 28,
        fontWeight: '800',
        color: COLORS.textDark,
    },
    subText: {
        fontSize: 15,
        color: COLORS.textMuted,
        marginTop: 8,
    },
    formContainer: {
        marginBottom: 24,
    },
    inputWrapper: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: COLORS.inputBg,
        borderRadius: 16,
        marginBottom: 16,
        paddingHorizontal: 16,
        height: 60,
        gap: 10,
    },
    input: {
        flex: 1,
        fontSize: 16,
        color: COLORS.textDark,
    },
    forgotPasswordBtn: {
        alignSelf: 'flex-end',
        marginBottom: 32,
    },
    forgotPasswordText: {
        color: COLORS.primaryBlue,
        fontWeight: '600',
    },
    loginBtn: {
        backgroundColor: COLORS.primaryBlue,
        borderRadius: 16,
        height: 60,
        justifyContent: 'center',
        alignItems: 'center',
    },
    loginBtnText: {
        color: COLORS.white,
        fontSize: 16,
        fontWeight: 'bold',
    },
    footerContainer: {
        flexDirection: 'row',
        justifyContent: 'center',
    },
    footerText: {
        color: COLORS.textMuted,
    },
    signUpText: {
        color: COLORS.primaryBlue,
        fontWeight: 'bold',
    },
});