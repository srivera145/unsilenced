-- Phase 1.3: school totals as each Clery file reported them.
--
-- clery_campus_stats keeps each campus's newest figure, so a campus that
-- drops out of later files keeps the reports earlier files listed for it. That
-- is right when the campus closed and its reports went nowhere, and wrong when
-- a later file moved them to another campus: they were then counted twice.
-- Austin Community College's Health Science Academy had 1 public-property
-- domestic-violence report for 2022 in the 2020-2022 file; the 2021-2023 and
-- 2022-2024 files drop that campus and list the report under Riverside instead.
-- Every file says the school had 1. The campus sum was 2.
--
-- This table holds, for every file imported, the school's total for each
-- year, location and offense: the sum of its campuses in that file, NULL when
-- every campus cell was blank. vintage is the newest calendar year in the
-- file, as in clery_campus_stats.
--
-- clery_stats is rebuilt as the sum of the school's campuses, but never more
-- than the highest total any single file reported for that school, year,
-- location and offense (CleryStat::rebuildFromCampuses). Until the Clery files
-- are imported again after this migration the table is empty and the rebuild
-- uses the campus sum alone, as before.
CREATE TABLE IF NOT EXISTS clery_file_totals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    year SMALLINT UNSIGNED NOT NULL,
    location ENUM('on_campus', 'on_campus_housing', 'noncampus', 'public_property') NOT NULL,
    vintage SMALLINT UNSIGNED NOT NULL,
    rape INT UNSIGNED NULL,
    fondling INT UNSIGNED NULL,
    incest INT UNSIGNED NULL,
    statutory_rape INT UNSIGNED NULL,
    dating_violence INT UNSIGNED NULL,
    domestic_violence INT UNSIGNED NULL,
    stalking INT UNSIGNED NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_school_year_location_vintage (school_id, year, location, vintage),
    INDEX idx_location_year_school (location, year, school_id),
    CONSTRAINT fk_clery_file_totals_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
