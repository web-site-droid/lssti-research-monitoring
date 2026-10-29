-- Browser catalog (anon key): read approved rows only.
-- Apply in Supabase SQL editor or via MCP migrate.

alter table public.research_study_index enable row level security;

drop policy if exists research_study_index_public_read on public.research_study_index;

create policy research_study_index_public_read
  on public.research_study_index
  for select
  to anon, authenticated
  using (status = 'approved');

grant select on public.research_study_index to anon, authenticated;
