# SPFPU Production Web Application

## Summary

Build a greenfield, production-ready internal records system for PPUU UTHM. SPFPU will record metadata for incoming and outgoing correspondence; it will never store the actual letter files.

* Bahasa Melayu interface, responsive across current desktop and mobile browsers.
* In the UI;

&#x09;- Category will be "Kategori",

&#x09;- Folder will be "Fail",

&#x09;- Volume will be "Jilid",

&#x09;- Entry will be "Entri",

&#x09;- Fullname will be "Name Penuh",

&#x09;- Username will be "Username",

&#x09;- Email will be "E-mel",

&#x09;- Phone number will be "No. Phone",

&#x09;- Role will be "Role",

&#x09;- Password will be "Kata Laluan",

&#x09;- In the entries table display;

&#x09;	- No. will be "Bil.",

&#x09;	- Type will be "Jenis",

&#x09;	- Date on letter (DOL) will be "Surat Bertarikh",

&#x09;	- From/To will be "Daripada/Kepada",

&#x09;	- Received/Sent will be "Dimasukkan/Dihantar",

&#x09;	- Matter will be "Perkara",

&#x09;	- Remarks will be "Catatan"

* Linux Apache virtual host with HTTPS, PHP 8.3, MariaDB 10.11, and Asia/Kuala\_Lumpur timezone.
* Framework-free HTML/CSS/JavaScript with server-rendered PHP MVC, Composer autoloading, PDO, and locally bundled utilities only.
* Expected scale: up to 50 users and 10,000 entries.

## Implementation Changes

### Application structure and interface

* Use `public/` as the only Apache document root; keep source, configuration, migrations, temporary imports, tests, and backups outside it.
* Organize backend code into controllers, domain/services, repositories, authorization policies, validation, and PHP templates.
* Provide authentication, category/folder/volume browsing, global search, user management, audit history, CSV operations, and database backup as server-rendered route groups. No public API is included.
* Open the category workspace immediately after login. Use breadcrumbs, searchable folder lists, paginated entry tables, contextual actions, and a mobile filter drawer.
* Apply official UTHM branding using assets and rules from the [official logo download](https://www.uthm.edu.my/en/downloads/uthm-official-logo) and [corporate manual](https://korporat.uthm.edu.my/en/branding/corporate-manual). Use a calm institutional layout, UTHM blue as the main accent, red sparingly, system UI fonts, minimal cards, and restrained transitions with reduced-motion support.
* Apply practical accessibility measures: keyboard operation, visible focus, proper labels, readable contrast, associated form errors, and responsive tables. Formal WCAG certification is not required.

### Data model and records workflow

* `users`: fullname, case-insensitive unique username and email, optional phone, role, status, password hash, reset-warning state, and timestamps.
* `categories`: case-insensitive unique name and optional description.
* `folders`: globally unique case-insensitive file reference code, repeatable display name, optional description, immutable confidentiality setting, and parent category.
* `folder\_access`: individual Staff grants for confidential folders; grants apply to all present and future volumes.
* `volumes`: immutable sequence displayed as `Jilid N`, optional coverage start/end dates, optional description, and open/closed state.
* `entries`: immutable number unique within a volume, Incoming/Outgoing type, letter date, From/To, received/sent date, matter, optional remarks, authorship, and timestamps.
* `audit\_logs`: actor, action, target, timestamp, IP, and sanitized before/after values. Never record passwords or hashes.
* Use `archived\_at`, `archived\_by`, and archive-batch metadata for soft deletion. Archiving a category or folder atomically archives its whole branch. Archived data has no application restoration or archive-browsing UI; recovery is a documented database-administrator operation.
* Creating a folder atomically creates `Jilid 1`. Closing the current volume and creating `Jilid N+1` is one Admin transaction.
* Only the latest volume accepts normal new entries. Admin correction mode may temporarily edit existing entries in any historical volume, but cannot insert new entries or archive there.
* Entry numbers are assigned transactionally, begin at 1, are immutable, and are never reused after archival.
* Validate dates as real calendar dates, store ISO dates, and display/export `DD.MM.YYYY`. If received/sent precedes the letter date, warn and require confirmation but allow saving.
* Category names and folder reference codes are unique; folder display names may repeat. Folder confidentiality cannot be changed after creation.

### Security and RBAC

* Centralize authorization policies and enforce them in every controller/query, including search, CSV, and direct URL access.
* Admins manage categories, folders, grants, volumes, entries, Staff profiles, roles, password resets, account status, audit history, and backups.
* Staff may browse all folder metadata, but cannot open a confidential folder without a grant. They may create/read/update/archive entries only in accessible current volumes.
* Every user may edit their own non-role profile fields and change their own password. Password hashes are never exposed through the UI, user directory, audit log, or CSV.
* Admins cannot edit another Admin’s personal fields, but may reset, demote, deactivate, or reactivate another Admin. Prevent self-demotion/deactivation and preserve at least one active Admin.
* Deactivation blocks login but preserves authorship, grants, and history.
* Passwords require 8–19 characters with uppercase, lowercase, and digits and are hashed with Argon2id. A reset sets `Passw123` and displays a persistent warning, but—per the selected policy—does not block application access until it is changed.
* Seed the first Admin using an idempotent deployment command reading credentials from environment variables; refuse to run once users exist and never commit initial credentials.
* Use HTTPS-only secure/HttpOnly/SameSite cookies, CSRF tokens, session-ID rotation, output escaping, prepared statements, security headers, and login throttling. End sessions after eight hours of inactivity.
* Audit all data, access, user, and password-reset changes; also log successful/failed authentication and database-backup downloads. Ordinary searches and CSV exports are not audited. No change-reason field is required.

## Interfaces and Data Exchange

* Export permitted search results as UTF-8 CSV using Malay headers and values, with dates in `DD.MM.YYYY`.
* Accept documented Malay and English import aliases, including `Masuk`/`Incoming` and `Keluar`/`Outgoing`.
* Malay and English import aliases:

&#x09;- `No.` or `Bil.`

&#x09;- `Type` or `Jenis` or `Masuk/Keluar`

&#x09;- `DOL` or `Surat Bertarikh`

&#x09;- `From/To` or `Daripada/Kepada`

&#x09;- `Received/Sent` or `Dimasukkan/Dihantar`

&#x09;- `Matter` or `Perkara`

&#x09;- `Remarks` or `Catatan`

* CSV import is Admin-only and targets an empty current volume. Require unique positive numbers including number 1, but allow gaps.
* Validate the complete upload, show a preview with all row errors, and import all rows in one transaction only after the file is fully valid and confirmed.
* Limit imports to 10,000 rows and a suitable fixed upload size; keep temporary files outside the web root and remove them after confirmation or expiry.
* Global search is permission-filtered and covers entry text, type, date range, category, folder code/name, and volume. Export all matching authorized rows, not only the displayed page.
* Provide 100-row server-side pagination for volume pages and indexes for hierarchy keys, entry numbers, dates, types, archive state, normalized usernames/emails, folder codes, and access grants.
* Provide an Admin database-backup action that requires current-password re-authentication, streams a compressed SQL dump without retaining it on the server, disables response caching, and records the download. Restoration remains an external Linux/MariaDB administrator procedure.

## Test Plan

* PHPUnit unit and MariaDB integration tests for validation, numbering concurrency, hierarchy transactions, archive cascades, volume closure/correction, grants, and every RBAC boundary.
* Authentication tests for inactive accounts, throttling, eight-hour inactivity, session rotation, CSRF, password rules, reset warnings, last-Admin protection, and login audit events.
* CSV tests for both languages, UTF-8 content, numbering gaps, duplicate/missing numbers, invalid dates, chronology warnings, permission filtering, blank-volume enforcement, preview, and transaction rollback.
* Playwright journeys for Admin and Staff login, responsive category browsing, confidential-folder denial/granting, entry CRUD, search/export, user management, backup re-authentication, and keyboard operation.
* Performance checks using approximately 10,000 entries to verify indexed search and pagination.
* Deployment acceptance on a dedicated HTTPS Linux Apache virtual host with migrations, environment configuration, first-Admin seeding, backup download, and documented external restoration.

## Assumptions and Exclusions

* Username default: 3–50 ASCII letters, digits, `.`, `\_`, or `-`, compared case-insensitively.
* Full names and display names allow 150 characters; file reference codes allow 100; descriptions, Matter, and Remarks allow 500; phone numbers allow common Malaysian/international formatting.
* Remarks and hierarchy descriptions are optional; the other specified entry fields are required.
* No email delivery, self-service password recovery, notifications, multilingual UI, document attachments, public registration, external API, approval workflow, or in-application archive restoration is included.
* Composer and Node-based test tooling are development dependencies; production serves locally bundled assets without CDN access.
