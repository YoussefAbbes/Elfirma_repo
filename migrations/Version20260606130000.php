<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260606130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user_progress table for the AgriTech training/quiz/certificate module';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE user_progress (
                id BIGINT AUTO_INCREMENT NOT NULL,
                user_id BIGINT NOT NULL,
                current_lesson INT DEFAULT 1,
                completed_lessons JSON DEFAULT NULL,
                passed TINYINT(1) DEFAULT NULL,
                quiz_score INT DEFAULT NULL,
                certificate_number VARCHAR(50) DEFAULT NULL,
                certificate_path VARCHAR(500) DEFAULT NULL,
                certificate_generated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                completed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX UNIQ_user_progress_certificate_number (certificate_number),
                INDEX idx_user_progress_user_id (user_id),
                INDEX idx_user_progress_passed (passed),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_progress');
    }
}
