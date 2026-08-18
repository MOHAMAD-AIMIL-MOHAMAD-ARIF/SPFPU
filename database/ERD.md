# SPFPU Database Entity-Relationship Diagram

This diagram reflects the MariaDB schema defined by migrations `001_create_spfpu.sql` through `003_add_user_auth_version.sql`.

```mermaid
erDiagram
    USERS {
        bigint id PK
        varchar fullname
        varchar username
        varchar username_norm UK
        varchar email
        varchar email_norm UK
        varchar phone "nullable"
        enum role "Admin or Staff"
        enum status "Active or Inactive"
        varchar password_hash
        int auth_version "credential epoch"
        boolean reset_warning
        datetime archived_at "nullable"
        bigint archived_by "nullable; logical user reference"
        datetime created_at
        datetime updated_at
    }

    CATEGORIES {
        bigint id PK
        varchar name
        varchar name_norm UK
        varchar description "nullable"
        datetime archived_at "nullable"
        bigint archived_by "nullable; logical user reference"
        char archive_batch "nullable UUID"
        bigint created_by FK
        datetime created_at
        datetime updated_at
    }

    FOLDERS {
        bigint id PK
        bigint category_id FK
        varchar reference_code
        varchar reference_code_norm UK
        varchar display_name
        varchar description "nullable"
        boolean is_confidential
        datetime archived_at "nullable"
        bigint archived_by "nullable; logical user reference"
        char archive_batch "nullable UUID"
        bigint created_by FK
        datetime created_at
        datetime updated_at
    }

    FOLDER_ACCESS {
        bigint folder_id PK, FK
        bigint user_id PK, FK
        bigint granted_by FK
        datetime created_at
    }

    VOLUMES {
        bigint id PK
        bigint folder_id FK
        int sequence_no "unique within folder"
        date coverage_start "nullable"
        date coverage_end "nullable"
        varchar description "nullable"
        enum status "Open or Closed"
        datetime archived_at "nullable"
        bigint archived_by "nullable; logical user reference"
        char archive_batch "nullable UUID"
        bigint created_by FK
        datetime created_at
        datetime closed_at "nullable"
    }

    ENTRIES {
        bigint id PK
        bigint volume_id FK
        int entry_no "unique within volume"
        enum type "nullable; Incoming or Outgoing"
        date letter_date "nullable"
        varchar correspondent "nullable"
        date movement_date "nullable"
        varchar matter "nullable"
        varchar remarks "nullable"
        datetime archived_at "nullable"
        bigint archived_by "nullable; logical user reference"
        char archive_batch "nullable UUID"
        bigint created_by FK
        bigint updated_by FK
        datetime created_at
        datetime updated_at
    }

    AUDIT_LOGS {
        bigint id PK
        bigint actor_id FK "nullable"
        varchar action
        varchar target_type
        bigint target_id "nullable; polymorphic logical reference"
        varchar ip_address
        json before_values "nullable"
        json after_values "nullable"
        datetime created_at
    }

    LOGIN_ATTEMPTS {
        bigint id PK
        char identity_hash
        varchar ip_address
        boolean succeeded
        datetime attempted_at
    }

    IMPORT_PREVIEWS {
        char token PK
        bigint user_id FK
        bigint volume_id FK
        varchar temp_path
        int row_count
        json warnings "nullable"
        datetime expires_at
        datetime created_at
    }

    USERS ||--o{ CATEGORIES : "creates"
    USERS ||--o{ FOLDERS : "creates"
    CATEGORIES ||--o{ FOLDERS : "contains"
    FOLDERS ||--o{ FOLDER_ACCESS : "has grants"
    USERS ||--o{ FOLDER_ACCESS : "receives access"
    USERS ||--o{ FOLDER_ACCESS : "grants access"
    FOLDERS ||--o{ VOLUMES : "contains"
    USERS ||--o{ VOLUMES : "creates"
    VOLUMES ||--o{ ENTRIES : "contains"
    USERS ||--o{ ENTRIES : "creates"
    USERS ||--o{ ENTRIES : "updates"
    USERS o|--o{ AUDIT_LOGS : "acts in"
    USERS ||--o{ IMPORT_PREVIEWS : "creates"
    VOLUMES ||--o{ IMPORT_PREVIEWS : "is previewed for"
```

## Notes

- `folder_access` resolves the many-to-many access relationship between users and confidential folders. Its composite primary key is (`folder_id`, `user_id`).
- `volumes` has a composite unique constraint on (`folder_id`, `sequence_no`), while `entries` has one on (`volume_id`, `entry_no`).
- `archived_by` appears on soft-archivable records as a logical reference to `users.id`, but the migration does not define a foreign-key constraint for it.
- `audit_logs.target_type` and `target_id` form a polymorphic logical reference, so `target_id` has no database-level foreign key.
- `login_attempts.identity_hash` deliberately does not reference `users`; it supports throttling without retaining the submitted login identity.
- `users.auth_version` is copied into each authenticated session and incremented whenever the account password changes or is reset; a mismatch invalidates the session.
- Deleting a folder cascades only to its `folder_access` rows. Other core records use soft archival fields rather than cascade deletion.
