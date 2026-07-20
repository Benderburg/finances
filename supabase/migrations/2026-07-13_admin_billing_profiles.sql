alter table public.profiles
add column if not exists billing text not null default 'regular' check (billing in ('regular', 'premium'));

alter table public.profiles
add column if not exists is_admin boolean not null default false;

alter table public.profiles
add column if not exists last_seen_at timestamptz;

create or replace function public.is_current_user_admin()
returns boolean
language sql
stable
security definer
set search_path = public
as $$
  select coalesce((
    select p.is_admin
    from public.profiles p
    where p.id = auth.uid()
  ), false);
$$;

create or replace function public.protect_profile_admin_fields()
returns trigger
language plpgsql
as $$
begin
  if auth.uid() = old.id
    and not public.is_current_user_admin()
    and (new.is_admin is distinct from old.is_admin or new.billing is distinct from old.billing)
  then
    raise exception 'Only admins can change billing or admin rights';
  end if;

  return new;
end;
$$;

drop trigger if exists profiles_protect_admin_fields on public.profiles;
create trigger profiles_protect_admin_fields
before update on public.profiles
for each row execute function public.protect_profile_admin_fields();

drop policy if exists "profiles_select_own" on public.profiles;
create policy "profiles_select_own" on public.profiles
for select using (auth.uid() = id or public.is_current_user_admin());

drop policy if exists "profiles_update_own" on public.profiles;
create policy "profiles_update_own" on public.profiles
for update using (auth.uid() = id or public.is_current_user_admin()) with check (auth.uid() = id or public.is_current_user_admin());
