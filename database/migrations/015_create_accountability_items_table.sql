-- Admin-entered public records about a school. The summary is written in our
-- own words and never names an individual; name_check_confirmed_* records an
-- admin overriding the name warning.
CREATE TABLE IF NOT EXISTS accountability_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    type VARCHAR(40) NOT NULL,
    item_date DATE NOT NULL,
    summary TEXT NOT NULL,
    status VARCHAR(30) NOT NULL,
    source_name VARCHAR(255) NOT NULL,
    source_url VARCHAR(2048) NOT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    name_check_confirmed_by INT NULL,
    name_check_confirmed_at DATETIME NULL,
    created_by INT NULL,
    updated_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_school_date (school_id, item_date),
    CONSTRAINT fk_accountability_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_accountability_confirmed_by FOREIGN KEY (name_check_confirmed_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_accountability_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_accountability_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
