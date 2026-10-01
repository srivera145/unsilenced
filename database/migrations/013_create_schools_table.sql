-- One row per IPEDS institution, keyed on UNITID. The slug is assigned once,
-- at insert, and never rewritten by an import, so /schools/{state}/{slug}
-- stays stable when a school renames.
CREATE TABLE IF NOT EXISTS schools (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unitid INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    city VARCHAR(120) NULL,
    state CHAR(2) NOT NULL,
    control VARCHAR(30) NULL,
    enrollment INT UNSIGNED NULL,
    enrollment_year SMALLINT UNSIGNED NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_unitid (unitid),
    UNIQUE KEY uniq_state_slug (state, slug),
    INDEX idx_name (name),
    INDEX idx_state_enrollment (state, enrollment),
    INDEX idx_enrollment (enrollment)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
