-- LSSTI Node.js app: school-ID login (server-only via service role; not exposed to anon).

create table if not exists public.research_app_users (
  id uuid primary key default gen_random_uuid(),
  school_id text not null,
  password_hash text not null,
  first_name text not null,
  middle_name text,
  last_name text not null,
  role_slug text not null check (
    role_slug in ('research_director', 'adviser', 'student', 'librarian')
  ),
  is_active boolean not null default true,
  must_change_password boolean not null default false,
  created_at timestamptz not null default now(),
  constraint research_app_users_school_id_key unique (school_id)
);

alter table public.research_app_users enable row level security;

-- Seed: password ChangeMe@LSSTI2026 (same bcrypt as MySQL seed)
insert into public.research_app_users (school_id, password_hash, first_name, middle_name, last_name, role_slug, must_change_password)
values
  ('RD-0001', '$2y$10$mAgfgL2o7cO.7YUN.y9tWu8Ta3Akx5m.JJvAgg7TLsGXwac9w6Iw2', 'Maria', 'L.', 'Director', 'research_director', true),
  ('ADV-0001', '$2y$10$mAgfgL2o7cO.7YUN.y9tWu8Ta3Akx5m.JJvAgg7TLsGXwac9w6Iw2', 'Juan', 'D.', 'Adviser', 'adviser', true),
  ('STU-0001', '$2y$10$mAgfgL2o7cO.7YUN.y9tWu8Ta3Akx5m.JJvAgg7TLsGXwac9w6Iw2', 'Ana', 'S.', 'Researcher', 'student', true),
  ('LIB-0001', '$2y$10$mAgfgL2o7cO.7YUN.y9tWu8Ta3Akx5m.JJvAgg7TLsGXwac9w6Iw2', 'Lina', 'M.', 'Librarian', 'librarian', true)
on conflict (school_id) do nothing;
