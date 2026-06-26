-- AgriTech Certificate Module - MySQL Database Setup
-- Run these SQL commands in PHPMyAdmin

-- Create Database
CREATE DATABASE IF NOT EXISTS agritech_training CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE agritech_training;

-- Create user_progress table
CREATE TABLE IF NOT EXISTS user_progress (
    id BIGINT AUTO_INCREMENT NOT NULL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    quiz_score INT DEFAULT NULL,
    passed TINYINT(1) DEFAULT NULL,
    certificate_number VARCHAR(50) DEFAULT NULL,
    certificate_path VARCHAR(500) DEFAULT NULL,
    certificate_generated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    completed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    UNIQUE KEY UNIQ_certificate_number (certificate_number),
    INDEX idx_user_id (user_id),
    INDEX idx_passed (passed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create doctrine_migration_versions table (for tracking migrations)
CREATE TABLE IF NOT EXISTS doctrine_migration_versions (
    version VARCHAR(191) NOT NULL,
    executed_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    execution_time INT DEFAULT NULL,
    PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert migration record
INSERT INTO doctrine_migration_versions (version, executed_at)
VALUES ('DoctrineMigrations\\Version20260606000001CreateUserProgressTable', NOW())
ON DUPLICATE KEY UPDATE executed_at = NOW();
