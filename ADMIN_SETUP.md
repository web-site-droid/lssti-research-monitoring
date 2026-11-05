# Admin for Librarian accounts

The separate `account_admin` role is displayed as **Admin**. The existing final-publisher role (`school_administrator`) remains displayed as **Librarian**. They have different permissions.

## First account

1. Deploy this version and confirm its production deployment is Ready.
2. Sign in with the existing final-publisher Librarian account.
3. Open **Admin setup** in the menu.
4. Enter the first Admin's username (up to 32 characters), name, and a temporary password of at least eight characters.
5. Create the account, sign out, choose **Admin sign in** on the login page, and enter the username and password.
6. Change the temporary password when prompted.

Setup inserts the role only when needed and closes after the first Admin account is created. It is protected by the existing final-publisher role and CSRF validation. No default Admin credentials are committed or automatically provisioned, and existing accounts are not converted.

Admin login is `index.php?route=admin/login`. Existing Admin accounts can use their previous ID value as their username; the change does not reset credentials. Usernames reuse the existing unique login-identifier storage and normalization (case-insensitive). No database migration is needed. Librarians and other roles keep the ordinary ID no. sign-in page.

## Admin pages

- **Librarian accounts:** create Librarians; edit their ID no., names, and active status; delete eligible accounts with confirmation. Posted role/department values cannot change their role or department. At least one Librarian must remain active. Accounts with academic records must be deactivated rather than deleted.
- **Activity logs:** latest 200 recorded audit/login events, optionally filtered to a Librarian. Includes recorded successful/failed sign-ins and sign-outs where associated with that user, and existing recorded actions. It is not a record of every page view.
- **Profile/password:** the Admin manages their own profile and password through the existing account screens.

The Admin cannot review/publish studies, open full manuscripts, access school-wide research reports, or use general People management. Password resets are not included in Librarian editing. Generic People/import routes cannot create or modify the special Admin role. Librarian account activity is scoped server-side to the final-publisher role; manually requesting another user's ID is rejected.

The legacy `librarian` role is distinct from the final-publisher `school_administrator` role. This new interface manages the latter, which is the Librarian account used in the current workflow.

For Admin credential recovery after setup, use a separately authorized database maintenance procedure. Setup does not reopen merely because the first Admin is inactive.
