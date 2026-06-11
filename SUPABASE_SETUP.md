# Supabase Setup

1. Creează un proiect în Supabase.
2. În `SQL Editor`, rulează conținutul din [supabase/schema.sql](/Users/fritz/code/noros/finances/supabase/schema.sql:1).
3. În [assets/js/supabase-config.js](/Users/fritz/code/noros/finances/assets/js/supabase-config.js:1) completează:
   - `SUPABASE_URL`
   - `SUPABASE_ANON_KEY`
4. În `Authentication -> Providers`, activează Email.
5. Profilul utilizatorului păstrează și preferințele pentru:
   - `language`: `ro`, `ru`, `en`
   - `currency`: `MDL`, `EUR`, `USD`

După asta, deschide aplicația și autentifică-te în Norocel.
