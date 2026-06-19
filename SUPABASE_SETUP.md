# Supabase Setup

1. Creează un proiect în Supabase.
2. Pentru instalare nouă, în `SQL Editor`, rulează conținutul din [supabase/schema.sql](/Users/fritz/code/noros/finances/supabase/schema.sql:1).
3. Pentru un proiect existent, aplică și migrarea [supabase/migrations/2026-06-19_accounts_multicurrency_goals.sql](/Users/fritz/code/noros/finances/supabase/migrations/2026-06-19_accounts_multicurrency_goals.sql:1).
4. În [assets/js/supabase-config.js](/Users/fritz/code/noros/finances/assets/js/supabase-config.js:1) completează:
   - `SUPABASE_URL`
   - `SUPABASE_ANON_KEY`
5. În `Authentication -> Providers`, activează Email.
6. Profilul utilizatorului păstrează și preferințele pentru:
   - `language`: `ro`, `ru`, `en`
   - `currency`: `MDL`, `EUR`, `USD`

Schema actualizată adaugă:
- `accounts` pentru conturi obișnuite și de economii;
- tranzacții multi-cont și multi-valută (`income`, `expense`, `transfer`, `exchange`);
- obiective cu `currency_code`, `savings_account_id`, `status`, `completed_at`;
- validări server-side pentru proprietate, monedă și sold disponibil.

După asta, deschide aplicația și autentifică-te în Norocel.
