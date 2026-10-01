-- Phase 2: survivor reports and the evidence vault (docs/SURVIVOR-REPORTS.md).
--
-- Nothing a survivor writes is stored in plain text: her account, the admin's
-- published version of it, the admin's note to her, evidence file names,
-- share-link labels and her optional email are XChaCha20-Poly1305 ciphertext
-- (columns ending _encrypted), under keys derived from VAULT_MASTER_KEY, which
-- is in .env and never in a backup. Her case key is never stored: lookup_id is
-- an HMAC of it and key_hash an Argon2id hash. Evidence files are encrypted on
-- disk under VAULT_PATH; the per-file keys here are wrapped by the master key.
--
-- Every timestamp in these tables is UTC, written by PHP (gmdate), never by
-- NOW(), whose time zone is the database server's.
--
-- Withdrawing (CaseDeletionService) deletes the case row; the foreign keys
-- take its report, evidence rows, share links and moderation events with it.
-- The service deletes the evidence files from disk first.

CREATE TABLE IF NOT EXISTS survivor_cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lookup_id CHAR(64) NOT NULL,
    key_hash VARCHAR(255) NOT NULL,
    email_encrypted TEXT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uniq_lookup_id (lookup_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One report per case. Multi-choice answers are comma-separated keys from
-- config/unsilenced.php (survivor_reports.*). status: private (consent c,
-- never moderated or counted), submitted, in_review, changes_requested,
-- approved, rejected. A school with reports cannot be deleted (RESTRICT): the
-- admin panel refuses and says why.
CREATE TABLE IF NOT EXISTS survivor_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    school_id INT NOT NULL,
    status VARCHAR(20) NOT NULL,
    consent VARCHAR(20) NOT NULL,
    incident_year SMALLINT UNSIGNED NOT NULL,
    incident_season VARCHAR(10) NULL,
    setting VARCHAR(30) NULL,
    perpetrator VARCHAR(30) NOT NULL,
    reported_to_school TINYINT(1) NOT NULL,
    school_channels VARCHAR(100) NULL,
    reported_to_police VARCHAR(12) NULL,
    not_reported_reasons VARCHAR(200) NULL,
    school_outcomes VARCHAR(200) NULL,
    response_rating TINYINT UNSIGNED NULL,
    account_encrypted MEDIUMTEXT NULL,
    name_scan_confirmed TINYINT(1) NOT NULL DEFAULT 0,
    published_encrypted MEDIUMTEXT NULL,
    published_by INT NULL,
    published_saved_at DATETIME NULL,
    admin_note_encrypted TEXT NULL,
    evidence_reviewed VARCHAR(10) NULL,
    reviewed_by INT NULL,
    submitted_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    approved_at DATETIME NULL,
    rejected_at DATETIME NULL,
    UNIQUE KEY uniq_case (case_id),
    INDEX idx_status_submitted (status, submitted_at),
    INDEX idx_school_status (school_id, status),
    INDEX idx_rejected (status, rejected_at),
    CONSTRAINT fk_survivor_report_case FOREIGN KEY (case_id) REFERENCES survivor_cases(id) ON DELETE CASCADE,
    CONSTRAINT fk_survivor_report_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE RESTRICT,
    CONSTRAINT fk_survivor_report_published_by FOREIGN KEY (published_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_survivor_report_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- sha256 is of the original bytes as uploaded, with uploaded_at the server's
-- UTC time: the integrity fingerprint shown on share pages. blob_* is the
-- encrypted original (metadata intact, released only through her share
-- links); admin_blob_* the copy admins see, with metadata stripped
-- (admin_copy: stripped, or partial when some may remain, e.g. a compressed
-- PDF). report_id is NULL only for a quarantined file kept after its case was
-- withdrawn (docs/ILLEGAL-CONTENT.md).
CREATE TABLE IF NOT EXISTS evidence_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NULL,
    kind VARCHAR(10) NOT NULL,
    mime_type VARCHAR(60) NOT NULL,
    size_bytes INT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    uploaded_at DATETIME NOT NULL,
    name_encrypted TEXT NOT NULL,
    blob_name CHAR(32) NOT NULL,
    blob_key VARCHAR(255) NOT NULL,
    admin_blob_name CHAR(32) NULL,
    admin_blob_key VARCHAR(255) NULL,
    admin_copy VARCHAR(12) NOT NULL,
    orientation TINYINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    reviewed_by INT NULL,
    quarantined_at DATETIME NULL,
    quarantined_by INT NULL,
    UNIQUE KEY uniq_blob_name (blob_name),
    INDEX idx_report (report_id),
    CONSTRAINT fk_evidence_report FOREIGN KEY (report_id) REFERENCES survivor_reports(id) ON DELETE CASCADE,
    CONSTRAINT fk_evidence_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_evidence_quarantined_by FOREIGN KEY (quarantined_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The token is shown to her once and never stored: token_hash is its
-- SHA-256. It travels in the URL fragment (/share#token), which browsers
-- never send to the server, so no access log can hold it. last_opened_at is
-- a time and nothing else about who opened it.
CREATE TABLE IF NOT EXISTS share_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    label_encrypted TEXT NULL,
    passcode_hash VARCHAR(255) NULL,
    failed_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    last_opened_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uniq_token_hash (token_hash),
    INDEX idx_case (case_id),
    INDEX idx_expires (expires_at),
    CONSTRAINT fk_share_link_case FOREIGN KEY (case_id) REFERENCES survivor_cases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS share_link_files (
    share_link_id INT NOT NULL,
    evidence_file_id INT NOT NULL,
    PRIMARY KEY (share_link_id, evidence_file_id),
    INDEX idx_evidence_file (evidence_file_id),
    CONSTRAINT fk_share_link_files_link FOREIGN KEY (share_link_id) REFERENCES share_links(id) ON DELETE CASCADE,
    CONSTRAINT fk_share_link_files_file FOREIGN KEY (evidence_file_id) REFERENCES evidence_files(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The report's history: status changes, survivor edits, approvals with their
-- checklist. details is JSON with keys and ids only, never text from the
-- report or the note.
CREATE TABLE IF NOT EXISTS moderation_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    actor VARCHAR(10) NOT NULL,
    admin_id INT NULL,
    event VARCHAR(40) NOT NULL,
    from_status VARCHAR(20) NULL,
    to_status VARCHAR(20) NULL,
    details TEXT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_report (report_id, id),
    CONSTRAINT fk_moderation_report FOREIGN KEY (report_id) REFERENCES survivor_reports(id) ON DELETE CASCADE,
    CONSTRAINT fk_moderation_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
