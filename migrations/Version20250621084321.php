<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250621084321 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE requests (id INT AUTO_INCREMENT NOT NULL, procedure_id INT NOT NULL, person_id INT NOT NULL, reference VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL, priority VARCHAR(20) NOT NULL, comments LONGTEXT DEFAULT NULL, admin_notes LONGTEXT DEFAULT NULL, total_cost NUMERIC(10, 2) DEFAULT NULL, paid_amount NUMERIC(10, 2) DEFAULT NULL, payment_status VARCHAR(30) DEFAULT NULL, submitted_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, expected_completion_at DATETIME DEFAULT NULL, display_order INT NOT NULL, is_active TINYINT(1) NOT NULL, is_deleted TINYINT(1) NOT NULL, UNIQUE INDEX UNIQ_7B85D651AEA34913 (reference), INDEX IDX_7B85D6511624BCD2 (procedure_id), INDEX IDX_7B85D651217BBB47 (person_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE requests ADD CONSTRAINT FK_7B85D6511624BCD2 FOREIGN KEY (procedure_id) REFERENCES procedures (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE requests ADD CONSTRAINT FK_7B85D651217BBB47 FOREIGN KEY (person_id) REFERENCES persons (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE requests DROP FOREIGN KEY FK_7B85D6511624BCD2
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE requests DROP FOREIGN KEY FK_7B85D651217BBB47
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE requests
        SQL);
    }
}
