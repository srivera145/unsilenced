-- Phase 1.2: real Clery files.
--
-- Each published Campus Safety file has one row per campus (UNITID_P, the
-- 6-digit UNITID plus a 3-digit campus number) and covers three calendar
-- years: Oncampuscrime222324.csv has 2022, 2023 and 2024. So every year is in
-- up to three files, schools correct earlier years in later files, and a
-- campus that closes drops out of later files, taking the reports earlier
-- files carried for it with it.
--
-- clery_campus_stats keeps one row per campus, year and location.
-- {offense}_vintage is the newest calendar year in the file the figure came
-- from (2024 for the 2022-2024 file). An import replaces a figure only with
-- one from the same or a newer file, and never with a blank. So for each
-- campus the newest file that has a figure wins, a closed campus keeps the
-- reports earlier files carried, and the files can be imported in any order,
-- any number of times. NULL vintage: no figure yet.
--
-- clery_stats (one row per school, year and location, which the site reads)
-- is rebuilt from this table after every import: the sum of the school's
-- campuses. After this migration, re-import every Clery file.
CREATE TABLE IF NOT EXISTS clery_campus_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    campus_id VARCHAR(20) NOT NULL,
    year SMALLINT UNSIGNED NOT NULL,
    location ENUM('on_campus', 'on_campus_housing', 'noncampus', 'public_property') NOT NULL,
    rape INT UNSIGNED NULL,
    fondling INT UNSIGNED NULL,
    incest INT UNSIGNED NULL,
    statutory_rape INT UNSIGNED NULL,
    dating_violence INT UNSIGNED NULL,
    domestic_violence INT UNSIGNED NULL,
    stalking INT UNSIGNED NULL,
    rape_vintage SMALLINT UNSIGNED NULL,
    fondling_vintage SMALLINT UNSIGNED NULL,
    incest_vintage SMALLINT UNSIGNED NULL,
    statutory_rape_vintage SMALLINT UNSIGNED NULL,
    dating_violence_vintage SMALLINT UNSIGNED NULL,
    domestic_violence_vintage SMALLINT UNSIGNED NULL,
    stalking_vintage SMALLINT UNSIGNED NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_campus_year_location (campus_id, year, location),
    INDEX idx_location_year_school (location, year, school_id),
    INDEX idx_school (school_id),
    CONSTRAINT fk_clery_campus_stats_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Public pages list only schools that report Clery figures (Title IV
-- institutions). A school from the IPEDS directory with no Clery figure keeps
-- its row and its URL but is left out of search, state lists and the sitemap.
-- Set by Imports\DerivedData::rebuild() after every import.
ALTER TABLE schools
    ADD COLUMN has_clery_data TINYINT(1) NOT NULL DEFAULT 0 AFTER enrollment_year,
    ADD INDEX idx_public_state_name (has_clery_data, state, name);

UPDATE schools s
SET has_clery_data = EXISTS (
    SELECT 1 FROM clery_stats c
    WHERE c.school_id = s.id
      AND (c.rape IS NOT NULL OR c.fondling IS NOT NULL OR c.incest IS NOT NULL OR c.statutory_rape IS NOT NULL
           OR c.dating_violence IS NOT NULL OR c.domestic_violence IS NOT NULL OR c.stalking IS NOT NULL)
);
