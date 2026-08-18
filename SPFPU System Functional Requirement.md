**SPFPU System Functional Requirements:**

|ID|Module|Actor|Functional Requirement|
|-|-|-|-|
|FR-001|Authentication|User|The system shall allow registered active users to log in using a username and password.|
|FR-002|Authentication|System|The system shall reject login attempts for deactivated accounts.|
|FR-003|Authentication|System|The system shall throttle repeated failed login attempts.|
|FR-004|Authentication|System|The system shall rotate the session ID after successful authentication.|
|FR-005|Authentication|System|The system shall terminate a session after eight hours of inactivity.|
|FR-006|Authentication|User|The system shall allow users to log out of the application.|
|FR-007|Authentication|System|The system shall record successful and failed authentication attempts in the audit log.|
|FR-008|Navigation|User|The system shall open the category workspace immediately after a successful login.|
|FR-009|Category Management|Admin|The system shall allow an Admin to create, view, edit the name and description of, and archive categories.|
|FR-010|Category Management|System|The system shall enforce case-insensitive uniqueness for category names when categories are created or edited.|
|FR-011|Category Management|System|The system shall allow a category description to be optional when the category is created or edited.|
|FR-012|Category Management|System|The system shall atomically archive all folders, volumes, and entries belonging to an archived category.|
|FR-013|Folder Management|Admin|The system shall allow an Admin to create, view, edit the reference code, display name, and description of, and archive folders under a category.|
|FR-014|Folder Management|System|The system shall require each folder to have a globally unique, case-insensitive file reference code when the folder is created or edited.|
|FR-015|Folder Management|System|The system shall allow different folders to use the same display name, including after a folder is edited.|
|FR-016|Folder Management|System|The system shall allow a folder description to be optional when the folder is created or edited.|
|FR-017|Folder Management|Admin|The system shall require the Admin to specify whether a folder is confidential when creating it.|
|FR-018|Folder Management|System|The system shall prevent a folder’s confidentiality setting from being changed after creation.|
|FR-019|Folder Management|System|The system shall atomically create `Jilid 1` when a new folder is created.|
|FR-020|Folder Management|System|The system shall atomically archive all volumes and entries belonging to an archived folder.|
|FR-021|Folder Access|Staff|The system shall allow Staff to browse the metadata of all folders.|
|FR-022|Folder Access|System|The system shall prevent Staff from opening a confidential folder unless they have an individual access grant.|
|FR-023|Folder Access|Admin|The system shall allow an Admin to grant or revoke a Staff member’s access to a confidential folder.|
|FR-024|Folder Access|System|The system shall apply a confidential-folder grant to all existing and future volumes in that folder.|
|FR-025|Volume Management|User|The system shall display each volume using the format `Jilid N`.|
|FR-026|Volume Management|System|The system shall treat each volume sequence number as immutable after any volume in its folder has ever contained an entry, including an archived entry.|
|FR-027|Volume Management|Admin|The system shall allow an Admin to specify optional coverage start and end dates and an optional description for a volume.|
|FR-028|Volume Management|Admin|The system shall allow an Admin to close the current volume and create `Jilid N+1` in a single transaction, provided the new volume number does not exceed 200.|
|FR-029|Volume Management|System|The system shall permit Staff to create entries only in the latest current volume and shall permit an Admin to create entries in either an open or closed volume.|
|FR-030|Entry Management|Admin, Authorized Staff|The system shall allow an authorized user to read and update entries in an accessible volume and to create and archive entries in an accessible current volume, subject to the Admin exception for creating entries in closed volumes.|
|FR-031|Entry Management|System|The system shall support Incoming and Outgoing entry types, displayed as `Masuk` and `Keluar`.|
|FR-032|Entry Management|System|The system shall require each entry to contain an entry number, type, letter date, From/To, received/sent date, and matter.|
|FR-033|Entry Management|System|The system shall allow remarks to be omitted.|
|FR-034|Entry Management|System|The system shall assign entry numbers transactionally, beginning at number 1 and unique within each volume.|
|FR-035|Entry Management|System|The system shall prevent an entry number from being changed after assignment.|
|FR-036|Entry Management|System|The system shall never reuse an entry number after its entry has been archived.|
|FR-037|Entry Management|System|The system shall record the author and creation and modification timestamps for every entry.|
|FR-038|Entry Management|System|The system shall validate entered dates as real calendar dates.|
|FR-039|Entry Management|System|The system shall display dates in `DD.MM.YYYY` format.|
|FR-040|Entry Management|System|The system shall warn the user when the received/sent date precedes the letter date and require confirmation before saving.|
|FR-041|Entry Management|Admin|The system shall provide an Admin correction mode for editing existing entries in historical volumes.|
|FR-042|Entry Management|System|The system shall prevent correction mode from archiving entries in historical volumes; creation of a new historical entry shall be available only to an Admin through the normal entry-creation action.|
|FR-043|Archive Management|System|The system shall soft-delete archived records and record who archived them, when they were archived, and their archive-batch metadata.|
|FR-044|Archive Management|System|The system shall exclude archived records from normal application browsing and operations.|
|FR-045|Profile Management|User|The system shall allow every user to edit their own non-role profile fields.|
|FR-046|Password Management|User|The system shall allow every user to change their own password.|
|FR-047|Password Management|System|The system shall require passwords to contain 8–19 characters, including uppercase letters, lowercase letters, and digits.|
|FR-048|User Management|Admin|The system shall allow an Admin to create and manage Staff accounts, profiles, roles, and account statuses.|
|FR-049|User Management|System|The system shall enforce case-insensitive uniqueness for usernames and email addresses.|
|FR-050|User Management|Admin|The system shall allow an Admin to reset another user’s password to `Passw123`.|
|FR-051|Password Management|System|The system shall display a persistent warning after a password reset until the user changes the reset password.|
|FR-052|Password Management|System|The system shall allow a user with a reset-warning state to continue accessing the application.|
|FR-053|User Management|Admin|The system shall allow an Admin to demote, deactivate, or reactivate another Admin.|
|FR-054|User Management|System|The system shall prevent an Admin from modifying another Admin’s personal profile fields.|
|FR-055|User Management|System|The system shall prevent an Admin from demoting or deactivating their own account.|
|FR-056|User Management|System|The system shall ensure that at least one active Admin account always remains.|
|FR-057|User Management|System|The system shall preserve an inactive user’s authorship records, confidential-folder grants, and audit history.|
|FR-058|User Management|System Administrator|The system shall provide an idempotent deployment command for creating the first Admin using environment-provided credentials.|
|FR-059|User Management|System|The system shall refuse to execute the first-Admin creation command after any user account exists.|
|FR-060|Search|User|The system shall provide global search across entry text, type, date range, category, folder reference code, folder name, and volume.|
|FR-061|Search|System|The system shall filter all search results according to the requesting user’s permissions and confidential-folder access.|
|FR-062|Entry Listing|User|The system shall display volume entries using server-side pagination of 100 entries per page.|
|FR-063|CSV Export|User|The system shall allow users to export permitted search results as UTF-8 CSV.|
|FR-064|CSV Export|System|The system shall export all matching authorized rows rather than only the currently displayed page.|
|FR-065|CSV Export|System|The system shall use Malay column headings and values and format dates as `DD.MM.YYYY`.|
|FR-066|CSV Import|Admin|The system shall restrict CSV import operations to Admin users.|
|FR-067|CSV Import|System|The system shall accept documented Malay and English column aliases for imported entry data.|
|FR-068|CSV Import|System|The system shall accept `Masuk` or `Incoming` for incoming entries and `Keluar` or `Outgoing` for outgoing entries.|
|FR-069|CSV Import|System|The system shall allow an Admin to import CSV data into an open or closed volume only when that volume has never contained any entry.|
|FR-070|CSV Import|System|The system shall require imported entry numbers to be unique positive integers and include entry number 1.|
|FR-071|CSV Import|System|The system shall permit gaps between imported entry numbers.|
|FR-072|CSV Import|System|The system shall validate the complete uploaded CSV before importing any row.|
|FR-073|CSV Import|Admin|The system shall display an import preview containing all detected row errors.|
|FR-074|CSV Import|System|The system shall import all CSV rows in one transaction only after the file is fully valid and the Admin confirms the import.|
|FR-075|CSV Import|System|The system shall roll back the complete CSV import if any part of the transaction fails.|
|FR-076|CSV Import|System|The system shall limit each CSV import to 10,000 rows and the configured maximum upload size.|
|FR-077|CSV Import|System|The system shall remove temporary import files after confirmation or expiry.|
|FR-078|Audit History|Admin|The system shall allow Admins to view audit history.|
|FR-079|Audit History|System|The system shall record the actor, action, target, timestamp, IP address, and sanitized before-and-after values for audited events.|
|FR-080|Audit History|System|The system shall audit data changes, access changes, user changes, password resets, authentication events, and database-backup downloads.|
|FR-081|Audit History|System|The system shall not record ordinary searches or CSV exports in the audit log.|
|FR-082|Audit History|System|The system shall never include passwords or password hashes in the audit log.|
|FR-083|Database Backup|Admin|The system shall allow an Admin to request a database backup.|
|FR-084|Database Backup|System|The system shall require the Admin to re-authenticate using their current password before downloading a backup.|
|FR-085|Database Backup|System|The system shall stream the backup as a compressed SQL dump without retaining it on the server.|
|FR-086|Database Backup|System|The system shall disable response caching for database-backup downloads.|
|FR-087|Authorization|System|The system shall enforce role and folder-access permissions for every operation, including direct URL access, search, and CSV operations.|
|FR-088|Authorization|System|The system shall prevent password hashes from appearing in the user interface, user directory, audit log, or CSV files.|
|FR-089|Interface|User|The system shall provide a Bahasa Melayu user interface for category, folder, volume, entry, user, search, audit, CSV, and backup operations.|
|FR-090|Interface|User|The system shall provide breadcrumbs, searchable folder lists, paginated entry tables, contextual actions, and a mobile filter drawer.|
|FR-091|Entry Listing|User|The system shall allow users to search entries within the selected volume across the correspondent, matter, and remarks fields while retaining server-side pagination.|
|FR-092|Entry Listing|User|The system shall display the last entry number used in the selected volume beside the entry search bar, or below it on small screens.|
|FR-093|Volume Management|Admin|While every volume in a folder has never contained an entry, the system shall allow an Admin to increment or decrement the first volume number using up and down buttons.|
|FR-094|Volume Management|System|The system shall atomically renumber every subsequent volume in the folder to preserve its order and shall require every resulting volume number to remain within 1 through 200.|
|FR-095|Volume Management|System|When an Admin adds the folder's first entry or confirms its first CSV import, the system shall warn that completing the action will permanently fix the folder's volume numbering and shall require confirmation.|
|FR-096|Password Management|System|The system shall invalidate all authenticated sessions for an account after its password is changed or reset.|
|FR-097|Password Management|System|After changing or resetting their own password, the current user shall be signed out and required to log in again.|



