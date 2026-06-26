-- Create user_progress table for AgriTech Certificate Module
CREATE TABLE IF NOT EXISTS user_progress (
    id BIGINT AUTO_INCREMENT NOT NULL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    quiz_score INT DEFAULT NULL,
    passed TINYINT(1) DEFAULT NULL,
    certificate_number VARCHAR(50) DEFAULT NULL,
    certificate_path VARCHAR(500) DEFAULT NULL,
    certificate_generated_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY UNIQ_certificate_number (certificate_number),
    INDEX idx_user_id (user_id),
    INDEX idx_passed (passed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
