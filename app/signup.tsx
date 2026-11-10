import { Feather } from '@expo/vector-icons';
import * as ImagePicker from 'expo-image-picker';
import { useRouter } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import {
  Alert,
  Animated,
  KeyboardAvoidingView,
  Platform,
  SafeAreaView,
  ScrollView,
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
  inputBorder: '#E2E8F0',
};

export default function SignUpScreen() {
  const router = useRouter();

  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [image, setImage] = useState<{ uri: string; ext: string } | null>(null);
  const [loading, setLoading] = useState(false); 

  const fadeAnim = useRef(new Animated.Value(0)).current;
  const slideUpAnim = useRef(new Animated.Value(30)).current;

  useEffect(() => {
    Animated.parallel([
      Animated.timing(fadeAnim, { toValue: 1, duration: 600, useNativeDriver: true }),
      Animated.spring(slideUpAnim, { toValue: 0, friction: 6, useNativeDriver: true }),
    ]).start();
  }, []);

  const pickImage = async () => {
    const { status } = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (status !== 'granted') {
      Alert.alert('Permission Denied', 'We need permissions to open your gallery.');
      return;
    }

    let result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      allowsEditing: true,
      aspect: [1, 1], 
      quality: 0.2,
    });

    if (!result.canceled) {
      const uri = result.assets[0].uri;
      const ext = uri.split('.').pop()?.toLowerCase() || 'jpeg';

      setImage({
        uri: uri,
        ext: ext === 'jpg' ? 'jpeg' : ext,
      });
    }
  };

  const handleSignUp = async () => {
    if (!name || !email || !password) {
      Alert.alert('Hold on!', 'Please enter your name, email, and password.');
      return;
    }

    setLoading(true);

    try {
      const { data: authData, error: authError } = await supabase.auth.signUp({
        email: email,
        password: password,
        options: {
          data: {
            full_name: name, 
          }
        }
      });

      if (authError) {
        Alert.alert('Error', authError.message);
        setLoading(false);
        return;
      }

      if (image && authData.user) {
        const fileName = `avatar-${authData.user.id}-${Date.now()}.${image.ext}`;
        const response = await fetch(image.uri);
        const blob = await response.blob();
        
        const { error: uploadError } = await supabase
          .storage
          .from('avatars') 
          .upload(fileName, blob, {
            contentType: `image/${image.ext}`,
            upsert: true,
          });

        if (!uploadError) {
          const { data: publicUrlData } = supabase
            .storage
            .from('avatars')
            .getPublicUrl(fileName);

          await supabase.auth.updateUser({
            data: { avatar_url: publicUrlData.publicUrl }
          });
        } else {
          console.log("Image Upload Error:", uploadError.message);
        }
      }

      Alert.alert('Success!', 'Your account has been created.');
      router.replace('/home'); 

    } catch (error: any) {
      Alert.alert("System Error", error.message || "An unexpected error occurred.");
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
            { opacity: fadeAnim, transform: [{ translateY: slideUpAnim }] }
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

          {/* Form Content inside ScrollView to handle smaller screens + keyboard */}
          <ScrollView 
            contentContainerStyle={styles.scrollContent} 
            showsVerticalScrollIndicator={false}
          >
            {/* Header Texts */}
            <View style={styles.headerContainer}>
              <Text style={styles.titleText}>Create Account</Text>
              <Text style={styles.subText}>Sign up to get real-time disaster alerts and stay protected.</Text>
            </View>

            {/* Form Inputs */}
            <View style={styles.formContainer}>
              
              {/* Full Name Input */}
              <View style={styles.inputWrapper}>
                <Feather name="user" size={20} color={COLORS.textMuted} style={styles.inputIcon} />
                <TextInput
                  style={styles.input}
                  placeholder="Full Name"
                  placeholderTextColor={COLORS.textMuted}
                  autoCapitalize="words"
                  value={name}
                  onChangeText={setName}
                />
              </View>

              {/* Email Input */}
              <View style={styles.inputWrapper}>
                <Feather name="mail" size={20} color={COLORS.textMuted} style={styles.inputIcon} />
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

              {/* Password Input */}
              <View style={styles.inputWrapper}>
                <Feather name="lock" size={20} color={COLORS.textMuted} style={styles.inputIcon} />
                <TextInput
                  style={styles.input}
                  placeholder="Create Password"
                  placeholderTextColor={COLORS.textMuted}
                  secureTextEntry={!showPassword}
                  value={password}
                  onChangeText={setPassword}
                />
                <TouchableOpacity 
                  style={styles.eyeIcon} 
                  onPress={() => setShowPassword(!showPassword)}
                >
                  <Feather 
                    name={showPassword ? "eye" : "eye-off"} 
                    size={20} 
                    color={COLORS.textMuted} 
                  />
                </TouchableOpacity>
              </View>



              {/* Sign Up Button */}
              <TouchableOpacity 
                style={[styles.signUpBtn, loading && { opacity: 0.7 }]} 
                onPress={handleSignUp}
                activeOpacity={0.8}
                disabled={loading} 
              >
                <Text style={styles.signUpBtnText}>
                  {loading ? 'Signing Up...' : 'Sign Up'}
                </Text>
              </TouchableOpacity>
            </View>

            {/* Footer Log In */}
            <View style={styles.footerContainer}>
              <Text style={styles.footerText}>Already have an account? </Text>
              <TouchableOpacity onPress={() => router.back()}>
                <Text style={styles.logInText}>Log In</Text>
              </TouchableOpacity>
            </View>
          </ScrollView>
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
  innerContainer: {
    flex: 1,
  },
  scrollContent: {
    paddingHorizontal: 24,
    paddingTop: Platform.OS === 'android' ? 60 : 40,
    paddingBottom: 40,
    flexGrow: 1,
    justifyContent: 'center',
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
  headerContainer: {
    marginBottom: 40,
    marginTop: 20, 
  },
  titleText: {
    fontSize: 28,
    fontWeight: '800',
    color: COLORS.textDark,
    marginBottom: 8,
  },
  subText: {
    fontSize: 15,
    color: COLORS.textMuted,
    lineHeight: 22,
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
  },
  inputIcon: {
    marginRight: 12,
  },
  input: {
    flex: 1,
    fontSize: 16,
    color: COLORS.textDark,
    height: '100%',
  },
  eyeIcon: {
    padding: 8,
  },
  uploadLabel: {
    fontSize: 13,
    fontWeight: '600',
    color: COLORS.textMuted,
    marginBottom: 8,
    marginTop: 4,
    marginLeft: 4,
    textTransform: 'uppercase',
  },
  photoBox: {
    width: '100%',
    height: 100,
    borderWidth: 1.5,
    borderColor: '#CBD5E1',
    borderStyle: 'dashed',
    borderRadius: 16,
    backgroundColor: '#F8FAFC',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 24,
  },
  photoText: {
    color: '#64748B',
    fontSize: 14,
    marginTop: 8,
    fontWeight: '500',
  },
  editIconContainer: {
    position: 'absolute',
    backgroundColor: 'rgba(0,0,0,0.5)',
    padding: 8,
    borderRadius: 20,
  },
  signUpBtn: {
    backgroundColor: COLORS.primaryBlue,
    borderRadius: 16,
    height: 60,
    justifyContent: 'center',
    alignItems: 'center',
    marginTop: 8, 
    shadowColor: COLORS.primaryBlue,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 8,
    elevation: 6,
  },
  signUpBtnText: {
    color: COLORS.white,
    fontSize: 16,
    fontWeight: 'bold',
  },
  footerContainer: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    marginTop: 10,
  },
  footerText: {
    color: COLORS.textMuted,
    fontSize: 14,
  },
  logInText: {
    color: COLORS.primaryBlue,
    fontSize: 14,
    fontWeight: 'bold',
  },
});