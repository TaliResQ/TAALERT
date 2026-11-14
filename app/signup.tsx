
import { Feather } from '@expo/vector-icons';
import * as ImagePicker from 'expo-image-picker';
import { useRouter } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import {
  Alert,
  Animated,
  Image,
  KeyboardAvoidingView,
  Platform,
  SafeAreaView,
  ScrollView,
  StatusBar,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
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

const ID_BUCKET = 'resident-valid-ids';

type SelectedID = {
  uri: string;
  ext: string;
  mimeType: string;
};

export default function SignUpScreen() {
  const router = useRouter();

  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);

  const [validId, setValidId] = useState<SelectedID | null>(null);
  const [loading, setLoading] = useState(false);

  const fadeAnim = useRef(new Animated.Value(0)).current;
  const slideUpAnim = useRef(new Animated.Value(30)).current;

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

  // SELECT VALID ID
  const pickValidID = async () => {
    try {
      const permission =
        await ImagePicker.requestMediaLibraryPermissionsAsync();

      if (permission.status !== 'granted') {
        Alert.alert(
          'Permission Required',
          'Please allow gallery access to upload your valid ID.'
        );
        return;
      }

      const result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ['images'],
        allowsEditing: false,
        quality: 0.8,
      });

      if (result.canceled || !result.assets?.length) {
        return;
      }

      const selectedAsset = result.assets[0];

      const extension =
        selectedAsset.fileName?.split('.').pop()?.toLowerCase() ||
        selectedAsset.uri.split('.').pop()?.split('?')[0]?.toLowerCase() ||
        'jpg';

      const allowedExtensions = ['jpg', 'jpeg', 'png'];

      if (!allowedExtensions.includes(extension)) {
        Alert.alert(
          'Invalid File',
          'Please select a JPG, JPEG, or PNG image.'
        );
        return;
      }

      setValidId({
        uri: selectedAsset.uri,
        ext: extension === 'jpeg' ? 'jpg' : extension,
        mimeType:
          selectedAsset.mimeType ||
          (extension === 'png' ? 'image/png' : 'image/jpeg'),
      });
    } catch (error: any) {
      Alert.alert(
        'Upload Error',
        error.message || 'Unable to select your valid ID.'
      );
    }
  };

  // UPLOAD VALID ID TO PRIVATE SUPABASE BUCKET
  const uploadValidID = async (userId: string) => {
    if (!validId) {
      throw new Error('Please select a valid ID image.');
    }

    const fileName = `valid-id-${Date.now()}.${validId.ext}`;

    // Each resident's file is stored inside their own folder.
    const filePath = `${userId}/${fileName}`;

    const response = await fetch(validId.uri);

    if (!response.ok) {
      throw new Error('Unable to read the selected ID image.');
    }

    const fileData = await response.arrayBuffer();

    const { error: uploadError } = await supabase.storage
      .from(ID_BUCKET)
      .upload(filePath, fileData, {
        contentType: validId.mimeType,
        upsert: false,
      });

    if (uploadError) {
      throw new Error(`ID upload failed: ${uploadError.message}`);
    }

    return filePath;
  };

  // REGISTER RESIDENT
  const handleSignUp = async () => {
    if (!name.trim() || !email.trim() || !password.trim()) {
      Alert.alert(
        'Hold on!',
        'Please enter your full name, email, and password.'
      );
      return;
    }

    if (!validId) {
      Alert.alert(
        'Valid ID Required',
        'Please upload a valid ID before creating your account.'
      );
      return;
    }

    if (password.length < 6) {
      Alert.alert(
        'Weak Password',
        'Your password must contain at least 6 characters.'
      );
      return;
    }

    setLoading(true);

    let uploadedFilePath: string | null = null;

    try {
      // STEP 1: CREATE AUTH ACCOUNT
      const { data: authData, error: authError } =
        await supabase.auth.signUp({
          email: email.trim().toLowerCase(),
          password,
          options: {
            data: {
              full_name: name.trim(),
            },
          },
        });

      if (authError) {
        throw new Error(authError.message);
      }

      if (!authData.user) {
        throw new Error('Unable to create your account. Please try again.');
      }

      // STEP 2: CHECK AUTHENTICATED SESSION
      // Private Storage requires an authenticated user with a valid session.
      if (!authData.session) {
        Alert.alert(
          'Email Confirmation Required',
          'Your account was created, but your email must be confirmed before your ID can be uploaded. Please confirm your email, log in, and complete the ID upload after signing in.'
        );
        return;
      }

      // STEP 3: UPLOAD ID TO resident-valid-ids
      uploadedFilePath = await uploadValidID(authData.user.id);

      // STEP 4: SAVE STORAGE PATH IN public.users.valid_id
      const { data: updatedUser, error: updateError } = await supabase
        .from('users')
        .update({
          valid_id: uploadedFilePath,
        })
        .eq('id', authData.user.id)
        .select('id')
        .maybeSingle();

      if (updateError) {
        throw new Error(
          `The ID was uploaded, but the resident record could not be updated: ${updateError.message}`
        );
      }

      if (!updatedUser) {
        throw new Error(
          'The ID was uploaded, but no matching resident record was found. Please check your users table registration trigger and Row Level Security policies.'
        );
      }

      // STEP 5: SUCCESS
      Alert.alert(
        'Registration Successful!',
        'Your account and valid ID have been submitted. Your residency application is now awaiting administrator verification.',
        [
          {
            text: 'Continue',
            onPress: () => router.replace('/home'),
          },
        ]
      );
    } catch (error: any) {
      console.error('Registration Error:', error);

      // Remove the uploaded file if saving its database reference failed.
      if (uploadedFilePath) {
        const { error: removeError } = await supabase.storage
          .from(ID_BUCKET)
          .remove([uploadedFilePath]);

        if (removeError) {
          console.error('ID cleanup error:', removeError.message);
        }
      }

      Alert.alert(
        'Registration Error',
        error.message || 'An unexpected error occurred.'
      );
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
          {/* BACK BUTTON */}
          <View style={styles.topBar}>
            <TouchableOpacity
              style={styles.backBtn}
              onPress={() => router.back()}
            >
              <Feather
                name="arrow-left"
                size={24}
                color={COLORS.textDark}
              />
            </TouchableOpacity>
          </View>

          <ScrollView
            contentContainerStyle={styles.scrollContent}
            showsVerticalScrollIndicator={false}
            keyboardShouldPersistTaps="handled"
          >
            {/* HEADER */}
            <View style={styles.headerContainer}>
              <Text style={styles.titleText}>Create Account</Text>
              <Text style={styles.subText}>
                Sign up to get real-time disaster alerts and stay protected.
              </Text>
            </View>

            {/* FORM */}
            <View style={styles.formContainer}>
              {/* FULL NAME */}
              <View style={styles.inputWrapper}>
                <Feather
                  name="user"
                  size={20}
                  color={COLORS.textMuted}
                  style={styles.inputIcon}
                />

                <TextInput
                  style={styles.input}
                  placeholder="Full Name"
                  placeholderTextColor={COLORS.textMuted}
                  autoCapitalize="words"
                  value={name}
                  onChangeText={setName}
                />
              </View>

              {/* EMAIL */}
              <View style={styles.inputWrapper}>
                <Feather
                  name="mail"
                  size={20}
                  color={COLORS.textMuted}
                  style={styles.inputIcon}
                />

                <TextInput
                  style={styles.input}
                  placeholder="Email address"
                  placeholderTextColor={COLORS.textMuted}
                  keyboardType="email-address"
                  autoCapitalize="none"
                  autoCorrect={false}
                  value={email}
                  onChangeText={setEmail}
                />
              </View>

              {/* PASSWORD */}
              <View style={styles.inputWrapper}>
                <Feather
                  name="lock"
                  size={20}
                  color={COLORS.textMuted}
                  style={styles.inputIcon}
                />

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
                    name={showPassword ? 'eye' : 'eye-off'}
                    size={20}
                    color={COLORS.textMuted}
                  />
                </TouchableOpacity>
              </View>

              {/* VALID ID UPLOAD */}
              <Text style={styles.uploadLabel}>RESIDENCY VERIFICATION</Text>

              <Text style={styles.idDescription}>
                Upload a clear photo of a valid government-issued ID for
                resident verification.
              </Text>

              <TouchableOpacity
                style={[
                  styles.photoBox,
                  validId && styles.photoBoxSelected,
                ]}
                onPress={pickValidID}
                activeOpacity={0.8}
                disabled={loading}
              >
                {validId ? (
                  <>
                    <Image
                      source={{ uri: validId.uri }}
                      style={styles.idPreview}
                      resizeMode="contain"
                    />

                    <View style={styles.changePhotoButton}>
                      <Feather name="edit-2" size={13} color="#FFFFFF" />
                      <Text style={styles.changePhotoText}>Change ID</Text>
                    </View>
                  </>
                ) : (
                  <>
                    <View style={styles.uploadIcon}>
                      <Feather
                        name="upload-cloud"
                        size={25}
                        color={COLORS.primaryBlue}
                      />
                    </View>

                    <Text style={styles.photoText}>
                      Tap to upload your valid ID
                    </Text>

                    <Text style={styles.photoSubText}>
                      JPG, JPEG, or PNG
                    </Text>
                  </>
                )}
              </TouchableOpacity>

              {validId && (
                <View style={styles.selectedFile}>
                  <Feather
                    name="check-circle"
                    size={16}
                    color="#287747"
                  />
                  <Text style={styles.selectedFileText}>
                    Valid ID selected successfully
                  </Text>

                  <TouchableOpacity
                    onPress={() => setValidId(null)}
                    disabled={loading}
                  >
                    <Feather name="trash-2" size={17} color="#B33C32" />
                  </TouchableOpacity>
                </View>
              )}

              {/* PRIVACY NOTICE */}
              <View style={styles.privacyNotice}>
                <Feather
                  name="shield"
                  size={17}
                  color="#287747"
                />

                <Text style={styles.privacyNoticeText}>
                  Your submitted valid ID will be stored securely in a private
                  storage bucket and will only be used for resident
                  verification by the TaliResQ administration.
                </Text>
              </View>

              {/* SIGN UP BUTTON */}
              <TouchableOpacity
                style={[
                  styles.signUpBtn,
                  loading && { opacity: 0.7 },
                ]}
                onPress={handleSignUp}
                activeOpacity={0.8}
                disabled={loading}
              >
                <Text style={styles.signUpBtnText}>
                  {loading ? 'Creating Account...' : 'Sign Up'}
                </Text>
              </TouchableOpacity>
            </View>

            {/* FOOTER */}
            <View style={styles.footerContainer}>
              <Text style={styles.footerText}>
                Already have an account?{' '}
              </Text>

              <TouchableOpacity
                onPress={() => router.back()}
                disabled={loading}
              >
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
    marginBottom: 35,
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
    borderWidth: 1,
    borderColor: COLORS.inputBorder,
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
    fontWeight: '700',
    color: COLORS.textMuted,
    marginBottom: 8,
    marginTop: 4,
    marginLeft: 4,
    textTransform: 'uppercase',
  },

  idDescription: {
    color: COLORS.textMuted,
    fontSize: 13,
    lineHeight: 19,
    marginBottom: 12,
    marginHorizontal: 4,
  },

  photoBox: {
    width: '100%',
    minHeight: 155,
    borderWidth: 1.5,
    borderColor: '#CBD5E1',
    borderStyle: 'dashed',
    borderRadius: 16,
    backgroundColor: '#F8FAFC',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 12,
    overflow: 'hidden',
    padding: 15,
  },

  photoBoxSelected: {
    borderStyle: 'solid',
    borderColor: COLORS.primaryBlue,
    backgroundColor: '#FFFFFF',
  },

  uploadIcon: {
    width: 48,
    height: 48,
    borderRadius: 24,
    backgroundColor: '#EAF4FF',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 10,
  },

  photoText: {
    color: '#475569',
    fontSize: 14,
    fontWeight: '600',
    marginTop: 4,
  },

  photoSubText: {
    color: '#94A3B8',
    fontSize: 11,
    marginTop: 5,
  },

  idPreview: {
    width: '100%',
    height: 190,
    borderRadius: 10,
  },

  changePhotoButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: COLORS.darkNavy,
    paddingHorizontal: 12,
    paddingVertical: 7,
    borderRadius: 20,
    marginTop: 10,
    gap: 6,
  },

  changePhotoText: {
    color: '#FFFFFF',
    fontSize: 11,
    fontWeight: '600',
  },

  selectedFile: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 14,
    paddingHorizontal: 4,
  },

  selectedFileText: {
    flex: 1,
    color: '#287747',
    fontSize: 12,
    fontWeight: '600',
  },

  privacyNotice: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    backgroundColor: '#EAF5EE',
    borderRadius: 12,
    padding: 12,
    marginTop: 5,
    marginBottom: 18,
    gap: 9,
  },

  privacyNoticeText: {
    flex: 1,
    color: '#287747',
    fontSize: 12,
    lineHeight: 18,
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
