-- One row per import:schools / import:clery invocation. The CLI writes it as
-- 'queued' and the queued job moves it through running to complete or failed.
CREATE TABLE IF NOT EXISTS import_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kind ENUM('schools', 'clery') NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(1024) NOT NULL,
    file_sha256 CHAR(64) NOT NULL,
    data_year SMALLINT UNSIGNED NULL,
    location VARCHAR(30) NULL,
    status ENUM('queued', 'running', 'complete', 'failed') NOT NULL DEFAULT 'queued',
    rows_read INT NOT NULL DEFAULT 0,
    rows_added INT NOT NULL DEFAULT 0,
    rows_updated INT NOT NULL DEFAULT 0,
    rows_unchanged INT NOT NULL DEFAULT 0,
    rows_skipped INT NOT NULL DEFAULT 0,
    error_count INT NOT NULL DEFAULT 0,
    errors MEDIUMTEXT NULL,
    columns_found TEXT NULL,
    message VARCHAR(1000) NULL,
    queued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    INDEX idx_kind_queued (kind, queued_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
