# Admin for Librarian accounts

The separate `account_admin` role is displayed as **Admin**. The existing final-publisher role (`school_administrator`) remains displayed as **Librarian**. They have different permissions.

## First account

1. Deploy this version and confirm its production deployment is Ready.
2. Sign in with the existing final-publisher Librarian account.
3. Open **Admin setup** in the menu.
4. Enter the first Admin's ID no., name, and a temporary password of at least eight characters.
5. Create the account, sign out, and sign in using the new Admin credentials.
6. Change the temporary password when prompted.

Setup inserts the role only when needed and closes after the first Admin account is created. It is protected by the existing final-publisher role and CSRF validation. No default Admin credentials are committed or automatically provisioned, and existing accounts are not converted.

## Admin pages

- **Librarian accounts:** lists final-publisher Librarians and creates new ones with an initial temporary password. Posted role or department values cannot change the type of account created.
- **Activity logs:** latest 200 recorded audit/login events, optionally filtered to a Librarian. Includes recorded successful/failed sign-ins and sign-outs where associated with that user, and existing recorded actions. It is not a record of every page view.
- **Profile/password:** the Admin manages their own profile and password through the existing account screens.

The Admin cannot review/publish studies, open full manuscripts, access school-wide research reports, or use general People management. Existing account editing, disabling, or password resets are not added by this feature. Generic People/import routes cannot create or modify the special Admin role. Librarian account activity is scoped server-side to the final-publisher role; manually requesting another user's ID is rejected.

The legacy `librarian` role is distinct from the final-publisher `school_administrator` role. This new interface manages the latter, which is the Librarian account used in the current workflow.

For Admin credential recovery after setup, use a separately authorized database maintenance procedure. Setup does not reopen merely because the first Admin is inactive.
