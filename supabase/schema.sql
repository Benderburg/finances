create extension if not exists pgcrypto;

create table if not exists public.profiles (
  id uuid primary key references auth.users(id) on delete cascade,
  email text unique,
  full_name text,
  avatar_url text,
  language text not null default 'ro' check (language in ('ro', 'ru', 'en')),
  currency text not null default 'MDL' check (currency in ('MDL', 'EUR', 'USD')),
  created_at timestamptz not null default timezone('utc', now()),
  updated_at timestamptz not null default timezone('utc', now())
);

create table if not exists public.accounts (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references auth.users(id) on delete cascade,
  name text not null,
  type text not null check (type in ('regular', 'savings')),
  currency_code text not null check (currency_code in ('MDL', 'EUR', 'USD')),
  opening_balance numeric(12, 2) not null default 0 check (opening_balance >= 0),
  include_in_total boolean not null default true,
  created_at timestamptz not null default timezone('utc', now()),
  updated_at timestamptz not null default timezone('utc', now())
);

create table if not exists public.goals (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references auth.users(id) on delete cascade,
  name text not null,
  target_amount numeric(12, 2) not null check (target_amount > 0),
  saved_amount numeric(12, 2) not null default 0 check (saved_amount >= 0),
  currency_code text not null default 'MDL' check (currency_code in ('MDL', 'EUR', 'USD')),
  savings_account_id uuid unique references public.accounts(id) on delete set null,
  status text not null default 'active' check (status in ('active', 'reached', 'spent', 'cancelled')),
  icon text,
  deadline date,
  completed_at timestamptz,
  created_at timestamptz not null default timezone('utc', now()),
  updated_at timestamptz not null default timezone('utc', now())
);

create table if not exists public.transactions (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references auth.users(id) on delete cascade,
  account_id uuid references public.accounts(id) on delete set null,
  from_account_id uuid references public.accounts(id) on delete set null,
  to_account_id uuid references public.accounts(id) on delete set null,
  goal_id uuid references public.goals(id) on delete set null,
  amount numeric(12, 2) not null check (amount > 0),
  currency_code text not null check (currency_code in ('MDL', 'EUR', 'USD')),
  converted_amount numeric(12, 2) check (converted_amount is null or converted_amount > 0),
  converted_currency_code text check (converted_currency_code is null or converted_currency_code in ('MDL', 'EUR', 'USD')),
  exchange_rate numeric(18, 8) check (exchange_rate is null or exchange_rate > 0),
  description text,
  transaction_date date not null,
  category text,
  type text not null check (type in ('income', 'expense', 'transfer', 'exchange')),
  created_at timestamptz not null default timezone('utc', now()),
  updated_at timestamptz not null default timezone('utc', now()),
  constraint transactions_shape_check check (
    (
      type in ('income', 'expense')
      and account_id is not null
      and from_account_id is null
      and to_account_id is null
    )
    or
    (
      type in ('transfer', 'exchange')
      and account_id is null
      and from_account_id is not null
      and to_account_id is not null
      and converted_amount is not null
      and converted_currency_code is not null
    )
  )
);

create table if not exists public.budgets (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references auth.users(id) on delete cascade,
  category text not null,
  monthly_limit numeric(12, 2) not null check (monthly_limit > 0),
  created_at timestamptz not null default timezone('utc', now()),
  updated_at timestamptz not null default timezone('utc', now()),
  unique (user_id, category)
);

create or replace function public.set_updated_at()
returns trigger
language plpgsql
as $$
begin
  new.updated_at = timezone('utc', now());
  return new;
end;
$$;

create or replace function public.get_account_balance(p_account_id uuid, p_exclude_transaction_id uuid default null)
returns numeric
language sql
stable
as $$
  select coalesce(a.opening_balance, 0)
    + coalesce((
      select sum(case when t.type = 'income' then t.amount else 0 end)
      from public.transactions t
      where t.account_id = a.id
        and (p_exclude_transaction_id is null or t.id <> p_exclude_transaction_id)
    ), 0)
    - coalesce((
      select sum(case when t.type = 'expense' then t.amount else 0 end)
      from public.transactions t
      where t.account_id = a.id
        and (p_exclude_transaction_id is null or t.id <> p_exclude_transaction_id)
    ), 0)
    - coalesce((
      select sum(t.amount)
      from public.transactions t
      where t.from_account_id = a.id
        and (p_exclude_transaction_id is null or t.id <> p_exclude_transaction_id)
    ), 0)
    + coalesce((
      select sum(t.converted_amount)
      from public.transactions t
      where t.to_account_id = a.id
        and (p_exclude_transaction_id is null or t.id <> p_exclude_transaction_id)
    ), 0)
  from public.accounts a
  where a.id = p_account_id;
$$;

create or replace function public.ensure_primary_account()
returns trigger
language plpgsql
security definer
set search_path = public
as $$
begin
  insert into public.accounts (user_id, name, type, currency_code, opening_balance, include_in_total)
  values (new.id, 'Main account', 'regular', new.currency, 0, true)
  on conflict do nothing;
  return new;
end;
$$;

create or replace function public.validate_goal_link()
returns trigger
language plpgsql
as $$
declare
  linked_account public.accounts%rowtype;
begin
  if new.savings_account_id is null then
    return new;
  end if;

  select *
  into linked_account
  from public.accounts
  where id = new.savings_account_id;

  if linked_account.id is null then
    raise exception 'Savings account not found';
  end if;
  if linked_account.user_id <> new.user_id then
    raise exception 'Savings account must belong to the same user';
  end if;
  if linked_account.type <> 'savings' then
    raise exception 'Only savings accounts can be linked to goals';
  end if;
  if linked_account.currency_code <> new.currency_code then
    raise exception 'Goal currency must match linked savings account currency';
  end if;

  return new;
end;
$$;

create or replace function public.validate_transaction_links()
returns trigger
language plpgsql
as $$
declare
  account_row public.accounts%rowtype;
  from_row public.accounts%rowtype;
  to_row public.accounts%rowtype;
  goal_row public.goals%rowtype;
  available_balance numeric;
begin
  if new.account_id is not null then
    select * into account_row from public.accounts where id = new.account_id;
    if account_row.id is null or account_row.user_id <> new.user_id then
      raise exception 'Account must belong to the same user';
    end if;
    if account_row.currency_code <> new.currency_code then
      raise exception 'Transaction currency must match account currency';
    end if;
  end if;

  if new.from_account_id is not null then
    select * into from_row from public.accounts where id = new.from_account_id;
    if from_row.id is null or from_row.user_id <> new.user_id then
      raise exception 'Source account must belong to the same user';
    end if;
    if from_row.currency_code <> new.currency_code then
      raise exception 'Debit currency must match source account currency';
    end if;
  end if;

  if new.to_account_id is not null then
    select * into to_row from public.accounts where id = new.to_account_id;
    if to_row.id is null or to_row.user_id <> new.user_id then
      raise exception 'Target account must belong to the same user';
    end if;
    if to_row.currency_code <> new.converted_currency_code then
      raise exception 'Credit currency must match target account currency';
    end if;
  end if;

  if new.goal_id is not null then
    select * into goal_row from public.goals where id = new.goal_id;
    if goal_row.id is null or goal_row.user_id <> new.user_id then
      raise exception 'Goal must belong to the same user';
    end if;
    if new.type <> 'expense' then
      raise exception 'Only expense transactions can be linked to goals';
    end if;
    if goal_row.currency_code <> new.currency_code then
      raise exception 'Goal currency must match transaction currency';
    end if;
    if goal_row.savings_account_id is not null and goal_row.savings_account_id <> new.account_id then
      raise exception 'Goal spending must use the linked savings account';
    end if;
  end if;

  if new.type in ('transfer', 'exchange') then
    if new.from_account_id = new.to_account_id then
      raise exception 'Source and target accounts must be different';
    end if;
    if from_row.currency_code = to_row.currency_code and new.amount <> new.converted_amount then
      raise exception 'Same-currency transfers must have identical amounts';
    end if;
    if new.exchange_rate is null then
      new.exchange_rate = round((new.amount / new.converted_amount)::numeric, 8);
    end if;
    available_balance = coalesce(public.get_account_balance(new.from_account_id, case when tg_op = 'UPDATE' then old.id else null end), 0);
    if available_balance < new.amount then
      raise exception 'Insufficient funds on source account';
    end if;
  elsif new.type = 'expense' then
    available_balance = coalesce(public.get_account_balance(new.account_id, case when tg_op = 'UPDATE' then old.id else null end), 0);
    if available_balance < new.amount then
      raise exception 'Insufficient funds on account';
    end if;
  end if;

  return new;
end;
$$;

drop trigger if exists profiles_set_updated_at on public.profiles;
create trigger profiles_set_updated_at
before update on public.profiles
for each row execute function public.set_updated_at();

drop trigger if exists accounts_set_updated_at on public.accounts;
create trigger accounts_set_updated_at
before update on public.accounts
for each row execute function public.set_updated_at();

drop trigger if exists transactions_set_updated_at on public.transactions;
create trigger transactions_set_updated_at
before update on public.transactions
for each row execute function public.set_updated_at();

drop trigger if exists budgets_set_updated_at on public.budgets;
create trigger budgets_set_updated_at
before update on public.budgets
for each row execute function public.set_updated_at();

drop trigger if exists goals_set_updated_at on public.goals;
create trigger goals_set_updated_at
before update on public.goals
for each row execute function public.set_updated_at();

drop trigger if exists profiles_create_primary_account on public.profiles;
create trigger profiles_create_primary_account
after insert on public.profiles
for each row execute function public.ensure_primary_account();

drop trigger if exists goals_validate_link on public.goals;
create trigger goals_validate_link
before insert or update on public.goals
for each row execute function public.validate_goal_link();

drop trigger if exists transactions_validate_links on public.transactions;
create trigger transactions_validate_links
before insert or update on public.transactions
for each row execute function public.validate_transaction_links();

alter table public.profiles enable row level security;
alter table public.accounts enable row level security;
alter table public.transactions enable row level security;
alter table public.budgets enable row level security;
alter table public.goals enable row level security;

drop policy if exists "profiles_select_own" on public.profiles;
create policy "profiles_select_own" on public.profiles
for select using (auth.uid() = id);

drop policy if exists "profiles_insert_own" on public.profiles;
create policy "profiles_insert_own" on public.profiles
for insert with check (auth.uid() = id);

drop policy if exists "profiles_update_own" on public.profiles;
create policy "profiles_update_own" on public.profiles
for update using (auth.uid() = id) with check (auth.uid() = id);

drop policy if exists "accounts_all_own" on public.accounts;
create policy "accounts_all_own" on public.accounts
for all using (auth.uid() = user_id) with check (auth.uid() = user_id);

drop policy if exists "transactions_all_own" on public.transactions;
create policy "transactions_all_own" on public.transactions
for all using (auth.uid() = user_id) with check (auth.uid() = user_id);

drop policy if exists "budgets_all_own" on public.budgets;
create policy "budgets_all_own" on public.budgets
for all using (auth.uid() = user_id) with check (auth.uid() = user_id);

drop policy if exists "goals_all_own" on public.goals;
create policy "goals_all_own" on public.goals
for all using (auth.uid() = user_id) with check (auth.uid() = user_id);
