create table if not exists public.liabilities (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references auth.users(id) on delete cascade,
  counterparty_name text not null,
  amount numeric(12, 2) not null check (amount > 0),
  currency_code text not null check (currency_code in ('MDL', 'EUR', 'USD')),
  liability_type text not null check (liability_type in ('receivable', 'payable', 'credit')),
  due_date date,
  comment text,
  status text not null default 'open' check (status in ('open', 'settled')),
  settlement_account_id uuid references public.accounts(id) on delete set null,
  settlement_transaction_id uuid references public.transactions(id) on delete set null,
  settled_at timestamptz,
  created_at timestamptz not null default timezone('utc', now()),
  updated_at timestamptz not null default timezone('utc', now())
);

create or replace function public.validate_liability_links()
returns trigger
language plpgsql
as $$
declare
  account_row public.accounts%rowtype;
  transaction_row public.transactions%rowtype;
begin
  if new.settlement_account_id is not null then
    select * into account_row from public.accounts where id = new.settlement_account_id;
    if account_row.id is null or account_row.user_id <> new.user_id then
      raise exception 'Settlement account must belong to the same user';
    end if;
    if account_row.currency_code <> new.currency_code then
      raise exception 'Liability currency must match settlement account currency';
    end if;
  end if;

  if new.settlement_transaction_id is not null then
    select * into transaction_row from public.transactions where id = new.settlement_transaction_id;
    if transaction_row.id is null or transaction_row.user_id <> new.user_id then
      raise exception 'Settlement transaction must belong to the same user';
    end if;
    if transaction_row.currency_code <> new.currency_code then
      raise exception 'Liability currency must match settlement transaction currency';
    end if;
  end if;

  if new.status = 'settled' and new.settled_at is null then
    new.settled_at = timezone('utc', now());
  end if;

  if new.status = 'open' then
    new.settled_at = null;
    new.settlement_account_id = null;
    new.settlement_transaction_id = null;
  end if;

  return new;
end;
$$;

drop trigger if exists liabilities_set_updated_at on public.liabilities;
create trigger liabilities_set_updated_at
before update on public.liabilities
for each row execute function public.set_updated_at();

drop trigger if exists liabilities_validate_links on public.liabilities;
create trigger liabilities_validate_links
before insert or update on public.liabilities
for each row execute function public.validate_liability_links();

alter table public.liabilities enable row level security;

drop policy if exists "liabilities_all_own" on public.liabilities;
create policy "liabilities_all_own" on public.liabilities
for all using (auth.uid() = user_id) with check (auth.uid() = user_id);
