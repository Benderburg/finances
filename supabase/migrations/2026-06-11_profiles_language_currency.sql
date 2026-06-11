alter table public.profiles
add column if not exists language text;

alter table public.profiles
add column if not exists currency text;

update public.profiles
set language = coalesce(language, 'ro')
where language is null;

update public.profiles
set currency = coalesce(currency, 'MDL')
where currency is null;

alter table public.profiles
alter column language set default 'ro';

alter table public.profiles
alter column currency set default 'MDL';

alter table public.profiles
alter column language set not null;

alter table public.profiles
alter column currency set not null;

alter table public.profiles
drop constraint if exists profiles_language_check;

alter table public.profiles
add constraint profiles_language_check
check (language in ('ro', 'ru', 'en'));

alter table public.profiles
drop constraint if exists profiles_currency_check;

alter table public.profiles
add constraint profiles_currency_check
check (currency in ('MDL', 'EUR', 'USD'));
