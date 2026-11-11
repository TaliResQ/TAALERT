import AsyncStorage from '@react-native-async-storage/async-storage';
import { createClient } from '@supabase/supabase-js';
import { Platform } from 'react-native';
import 'react-native-url-polyfill/auto';

// const supabaseUrl = "https://okwnknzodfvbsbgetvzh.supabase.co"
// const supabasePublishableKey = "sb_publishable_isCj681TK4luzYBQWSNJUg_qhCavuBs"

const supabaseUrl = "https://llfhttyqxogagirvdxzs.supabase.co"
const supabasePublishableKey = "sb_publishable_UfWzOl032r_4cEy344WRiQ_XZG51o4j"

const isWeb = Platform.OS === 'web';

export const supabase = createClient(
  supabaseUrl,
  supabasePublishableKey,
  {
    auth: {
      storage: isWeb ? undefined : AsyncStorage,
      autoRefreshToken: true,
      persistSession: true,
      detectSessionInUrl: false,
    },
  }
);