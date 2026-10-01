-- Clery Act counts, one row per school, calendar year and location. NULL means
-- the figure was not in any imported file; 0 means the school reported zero.
-- on_campus_housing is a SUBSET of on_campus, so totals add on_campus,
-- noncampus and public_property only.
CREATE TABLE IF NOT EXISTS clery_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    year SMALLINT UNSIGNED NOT NULL,
    location ENUM('on_campus', 'on_campus_housing', 'noncampus', 'public_property') NOT NULL,
    rape INT UNSIGNED NULL,
    fondling INT UNSIGNED NULL,
    incest INT UNSIGNED NULL,
    statutory_rape INT UNSIGNED NULL,
    dating_violence INT UNSIGNED NULL,
    domestic_violence INT UNSIGNED NULL,
    stalking INT UNSIGNED NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_school_year_location (school_id, year, location),
    INDEX idx_year_location (year, location),
    CONSTRAINT fk_clery_stats_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
