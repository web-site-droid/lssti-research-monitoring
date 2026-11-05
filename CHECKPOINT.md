# Local developer handoff checkpoint

Branch: `checkpoint/developer-handoff`.

This checkpoint preserves the inherited working tree and the local fixes described
in `DEVELOPER_HANDOFF.md`. It is a recovery point, not a verified production release.
The original version remains at commit `aa91937` on `main`.

The inherited deletions of the old README, documentation, SQL schema/migrations,
and placeholder logo are included to preserve the current state accurately. Their
previous contents remain recoverable from the original commit. The existing SQL
dump is unchanged; it is not a newly obtained production backup.

Excluded from the checkpoint: local database credentials, environment secrets,
Vercel local metadata, server logs, `image.png`, manuscript uploads, and `.cursor/`.
The Cursor folder contains production deployment automation and is intentionally
excluded from this checkpoint.

Validation performed before staging:

- 96 regression checks passed using a fixture database, with no external services.
- 97 PHP files passed syntax checks.
- Four changed JavaScript files passed Node syntax checks.
- `vercel.json` parsed successfully; Git's whitespace check passed.
- Reviewed credential-pattern matches in the changed/new source; the matches were
  an existing application default password, spreadsheet headings, and password
  visibility controls. No new live connection secrets were found by that scan.

Still outstanding before release: verify the live database host/access, back up the
live database, test the complete browser/MySQL workflow, and review the deployment
connection. Rotate the previously exposed database password through its host.

No push, deployment, live database connection, or database change is part of this
checkpoint task. Files containing sensitive data may exist in earlier Git history;
this checkpoint does not sanitize that history.
