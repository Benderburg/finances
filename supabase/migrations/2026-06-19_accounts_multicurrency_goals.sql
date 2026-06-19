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

alter table public.accounts enable row level security;

drop trigger if exists accounts_set_updated_at on public.accounts;
create trigger accounts_set_updated_at
before update on public.accounts
for each row execute function public.set_updated_at();

drop policy if exists "accounts_all_own" on public.accounts;
create policy "accounts_all_own" on public.accounts
for all using (auth.uid() = user_id) with check (auth.uid() = user_id);

alter table public.transactions
  add column if not exists account_id uuid references public.accounts(id) on delete set null,
  add column if not exists from_account_id uuid references public.accounts(id) on delete set null,
  add column if not exists to_account_id uuid references public.accounts(id) on delete set null,
  add column if not exists currency_code text,
  add column if not exists converted_amount numeric(12, 2),
  add column if not exists converted_currency_code text,
  add column if not exists exchange_rate numeric(18, 8),
  add column if not exists goal_id uuid references public.goals(id) on delete set null;

alter table public.transactions
  alter column category drop not null;

update public.transactions
set description = null
where description = '';

alter table public.transactions
  drop constraint if exists transactions_type_check;

alter table public.transactions
  add constraint transactions_type_check
  check (type in ('income', 'expense', 'transfer', 'exchange'));

alter table public.transactions
  drop constraint if exists transactions_currency_code_check;

alter table public.transactions
  add constraint transactions_currency_code_check
  check (currency_code is null or currency_code in ('MDL', 'EUR', 'USD'));

alter table public.transactions
  drop constraint if exists transactions_converted_currency_code_check;

alter table public.transactions
  add constraint transactions_converted_currency_code_check
  check (converted_currency_code is null or converted_currency_code in ('MDL', 'EUR', 'USD'));

alter table public.transactions
  drop constraint if exists transactions_converted_amount_positive_check;

alter table public.transactions
  add constraint transactions_converted_amount_positive_check
  check (converted_amount is null or converted_amount > 0);

alter table public.transactions
  drop constraint if exists transactions_exchange_rate_positive_check;

alter table public.transactions
  add constraint transactions_exchange_rate_positive_check
  check (exchange_rate is null or exchange_rate > 0);

alter table public.goals
  add column if not exists currency_code text,
  add column if not exists savings_account_id uuid references public.accounts(id) on delete set null,
  add column if not exists status text,
  add column if not exists completed_at timestamptz;

update public.goals g
set currency_code = coalesce(g.currency_code, p.currency, 'MDL')
from public.profiles p
where p.id = g.user_id
  and g.currency_code is null;

update public.goals
set status = 'active'
where status is null;

alter table public.goals
  alter column currency_code set default 'MDL';

alter table public.goals
  alter column status set default 'active';

alter table public.goals
  alter column currency_code set not null;

alter table public.goals
  alter column status set not null;

alter table public.goals
  drop constraint if exists goals_currency_code_check;

alter table public.goals
  add constraint goals_currency_code_check
  check (currency_code in ('MDL', 'EUR', 'USD'));

alter table public.goals
  drop constraint if exists goals_status_check;

alter table public.goals
  add constraint goals_status_check
  check (status in ('active', 'reached', 'spent', 'cancelled'));

alter table public.goals
  drop constraint if exists goals_saved_amount_nonnegative_check;

alter table public.goals
  add constraint goals_saved_amount_nonnegative_check
  check (saved_amount >= 0);

create unique index if not exists goals_savings_account_unique_idx
on public.goals (savings_account_id)
where savings_account_id is not null;

insert into public.accounts (user_id, name, type, currency_code, opening_balance, include_in_total)
select
  p.id,
  'Main account',
  'regular',
  p.currency,
  0,
  true
from public.profiles p
where not exists (
  select 1
  from public.accounts a
  where a.user_id = p.id
    and a.type = 'regular'
);

update public.transactions t
set
  account_id = a.id,
  currency_code = p.currency
from public.profiles p
join lateral (
  select id
  from public.accounts
  where user_id = p.id
    and type = 'regular'
  order by created_at asc
  limit 1
) a on true
where t.user_id = p.id
  and t.account_id is null
  and t.from_account_id is null
  and t.to_account_id is null;

alter table public.transactions
  alter column currency_code set not null;

alter table public.transactions
  drop constraint if exists transactions_shape_check;

alter table public.transactions
  add constraint transactions_shape_check
  check (
    (
      type in ('income', 'expense')
      and account_id is not null
      and from_account_id is null
      and to_account_id is null
      and currency_code is not null
    )
    or
    (
      type in ('transfer', 'exchange')
      and account_id is null
      and from_account_id is not null
      and to_account_id is not null
      and currency_code is not null
      and converted_amount is not null
      and converted_currency_code is not null
    )
  );

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

drop trigger if exists profiles_create_primary_account on public.profiles;
create trigger profiles_create_primary_account
after insert on public.profiles
for each row execute function public.ensure_primary_account();

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

drop trigger if exists goals_validate_link on public.goals;
create trigger goals_validate_link
before insert or update on public.goals
for each row execute function public.validate_goal_link();

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
    if account_row.id is null then
      raise exception 'Account not found';
    end if;
    if account_row.user_id <> new.user_id then
      raise exception 'Account must belong to the same user';
    end if;
    if account_row.currency_code <> new.currency_code then
      raise exception 'Transaction currency must match account currency';
    end if;
  end if;

  if new.from_account_id is not null then
    select * into from_row from public.accounts where id = new.from_account_id;
    if from_row.id is null then
      raise exception 'Source account not found';
    end if;
    if from_row.user_id <> new.user_id then
      raise exception 'Source account must belong to the same user';
    end if;
    if from_row.currency_code <> new.currency_code then
      raise exception 'Debit currency must match source account currency';
    end if;
  end if;

  if new.to_account_id is not null then
    select * into to_row from public.accounts where id = new.to_account_id;
    if to_row.id is null then
      raise exception 'Target account not found';
    end if;
    if to_row.user_id <> new.user_id then
      raise exception 'Target account must belong to the same user';
    end if;
    if to_row.currency_code <> new.converted_currency_code then
      raise exception 'Credit currency must match target account currency';
    end if;
  end if;

  if new.goal_id is not null then
    select * into goal_row from public.goals where id = new.goal_id;
    if goal_row.id is null then
      raise exception 'Goal not found';
    end if;
    if goal_row.user_id <> new.user_id then
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
      raise exception 'Transfers in the same currency must have identical debit and credit amounts';
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

drop trigger if exists transactions_validate_links on public.transactions;
create trigger transactions_validate_links
before insert or update on public.transactions
for each row execute function public.validate_transaction_links();
