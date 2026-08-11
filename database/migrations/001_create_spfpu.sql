SET NAMES utf8mb4;
SET time_zone = '+08:00';

CREATE TABLE IF NOT EXISTS users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 fullname VARCHAR(150) NOT NULL, username VARCHAR(50) NOT NULL, username_norm VARCHAR(50) NOT NULL,
 email VARCHAR(254) NOT NULL, email_norm VARCHAR(254) NOT NULL, phone VARCHAR(30) NULL,
 role ENUM('Admin','Staff') NOT NULL DEFAULT 'Staff', status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
 password_hash VARCHAR(255) NOT NULL, reset_warning TINYINT(1) NOT NULL DEFAULT 0,
 archived_at DATETIME NULL, archived_by BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_users_username_norm (username_norm), UNIQUE KEY uq_users_email_norm (email_norm), KEY idx_users_status_role (status,role)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL, name_norm VARCHAR(150) NOT NULL,
 description VARCHAR(500) NULL, archived_at DATETIME NULL, archived_by BIGINT UNSIGNED NULL, archive_batch CHAR(36) NULL,
 created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_categories_name_norm (name_norm), KEY idx_categories_archive (archived_at), CONSTRAINT fk_categories_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS folders (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, category_id BIGINT UNSIGNED NOT NULL, reference_code VARCHAR(100) NOT NULL, reference_code_norm VARCHAR(100) NOT NULL,
 display_name VARCHAR(150) NOT NULL, description VARCHAR(500) NULL, is_confidential TINYINT(1) NOT NULL DEFAULT 0,
 archived_at DATETIME NULL, archived_by BIGINT UNSIGNED NULL, archive_batch CHAR(36) NULL, created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_folders_ref_norm (reference_code_norm), KEY idx_folders_category_archive (category_id,archived_at),
 CONSTRAINT fk_folders_category FOREIGN KEY (category_id) REFERENCES categories(id), CONSTRAINT fk_folders_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS folder_access (
 folder_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, granted_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(folder_id,user_id), KEY idx_access_user (user_id,folder_id),
 CONSTRAINT fk_access_folder FOREIGN KEY(folder_id) REFERENCES folders(id) ON DELETE CASCADE, CONSTRAINT fk_access_user FOREIGN KEY(user_id) REFERENCES users(id), CONSTRAINT fk_access_granter FOREIGN KEY(granted_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS volumes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, folder_id BIGINT UNSIGNED NOT NULL, sequence_no INT UNSIGNED NOT NULL,
 coverage_start DATE NULL, coverage_end DATE NULL, description VARCHAR(500) NULL, status ENUM('Open','Closed') NOT NULL DEFAULT 'Open',
 archived_at DATETIME NULL, archived_by BIGINT UNSIGNED NULL, archive_batch CHAR(36) NULL, created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, closed_at DATETIME NULL,
 UNIQUE KEY uq_volume_sequence (folder_id,sequence_no), KEY idx_volumes_folder_archive (folder_id,archived_at,status),
 CONSTRAINT fk_volumes_folder FOREIGN KEY(folder_id) REFERENCES folders(id), CONSTRAINT fk_volumes_creator FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS entries (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, volume_id BIGINT UNSIGNED NOT NULL, entry_no INT UNSIGNED NOT NULL,
 type ENUM('Incoming','Outgoing') NOT NULL, letter_date DATE NOT NULL, correspondent VARCHAR(150) NOT NULL, movement_date DATE NOT NULL,
 matter VARCHAR(500) NOT NULL, remarks VARCHAR(500) NULL, archived_at DATETIME NULL, archived_by BIGINT UNSIGNED NULL, archive_batch CHAR(36) NULL,
 created_by BIGINT UNSIGNED NOT NULL, updated_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_entry_no (volume_id,entry_no), KEY idx_entries_volume_archive (volume_id,archived_at), KEY idx_entries_dates (letter_date,movement_date), KEY idx_entries_type (type), FULLTEXT KEY ft_entries_text (correspondent,matter,remarks),
 CONSTRAINT fk_entries_volume FOREIGN KEY(volume_id) REFERENCES volumes(id), CONSTRAINT fk_entries_creator FOREIGN KEY(created_by) REFERENCES users(id), CONSTRAINT fk_entries_updater FOREIGN KEY(updated_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, actor_id BIGINT UNSIGNED NULL, action VARCHAR(80) NOT NULL, target_type VARCHAR(50) NOT NULL,
 target_id BIGINT UNSIGNED NULL, ip_address VARCHAR(45) NOT NULL, before_values JSON NULL, after_values JSON NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_audit_created (created_at), KEY idx_audit_actor (actor_id,created_at), CONSTRAINT fk_audit_actor FOREIGN KEY(actor_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_attempts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, identity_hash CHAR(64) NOT NULL, ip_address VARCHAR(45) NOT NULL, succeeded TINYINT(1) NOT NULL, attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_login_throttle (identity_hash,ip_address,attempted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS import_previews (
 token CHAR(64) PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, volume_id BIGINT UNSIGNED NOT NULL, temp_path VARCHAR(500) NOT NULL,
 row_count INT UNSIGNED NOT NULL, warnings JSON NULL, expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_preview_expiry(expires_at), CONSTRAINT fk_preview_user FOREIGN KEY(user_id) REFERENCES users(id), CONSTRAINT fk_preview_volume FOREIGN KEY(volume_id) REFERENCES volumes(id)
) ENGINE=InnoDB;
