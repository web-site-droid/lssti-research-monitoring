# SDLC — LSSTI Digital Research Repository

## Requirements
Institutional need: manage undergraduate research from submission through ethics review, approval, controlled reading on LAN, and reporting—without internet dependency.

## Design
Level 0 DFD modules (users, submit/revise, review/approve, search/read, logs/reports); MySQL schema with roles, studies, versions, ethics, logs; PHP front controller with role checks and private file serving.

## Implementation
Iterative build: skeleton and database, auth and users, studies and uploads, review and approval rules, search and reader (mammoth/PDF.js offline), audit and reports.

## Testing
Manual checklists in `TESTING.md`; verify on XAMPP from LAN clients with allowlist optional.

## Deployment
XAMPP on school PC, Apache document root `public/`, fixed LAN IP, Windows Firewall, optional IP allowlist in settings; change default director password on first login.
