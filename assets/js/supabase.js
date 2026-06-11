import { SUPABASE_ANON_KEY, SUPABASE_URL } from "./supabase-config.js";

export function isSupabaseConfigured() {
  return !SUPABASE_URL.includes("YOUR_PROJECT_ID") && !SUPABASE_ANON_KEY.includes("YOUR_SUPABASE_ANON_KEY");
}

export function createSupabaseClient() {
  if (!window.supabase?.createClient) {
    throw new Error("Supabase SDK не загрузился");
  }

  return window.supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY, {
    auth: {
      persistSession: true,
      autoRefreshToken: true
    }
  });
}
