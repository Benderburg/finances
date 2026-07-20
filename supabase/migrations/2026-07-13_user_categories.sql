create table if not exists public.categories (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references auth.users(id) on delete cascade,
  key text not null,
  name text not null,
  type text not null check (type in ('income', 'expense')),
  created_at timestamptz not null default timezone('utc', now()),
  updated_at timestamptz not null default timezone('utc', now()),
  unique (user_id, type, key)
);

create unique index if not exists categories_user_type_name_unique
on public.categories (user_id, type, lower(name));

drop trigger if exists categories_set_updated_at on public.categories;
create trigger categories_set_updated_at
before update on public.categories
for each row execute function public.set_updated_at();

alter table public.categories enable row level security;

drop policy if exists "categories_all_own" on public.categories;
create policy "categories_all_own" on public.categories
for all using (auth.uid() = user_id) with check (auth.uid() = user_id);
