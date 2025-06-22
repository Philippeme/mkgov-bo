<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250622080550 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE workflows (id INT AUTO_INCREMENT NOT NULL, procedure_id INT NOT NULL, name VARCHAR(255) NOT NULL, short_description LONGTEXT NOT NULL, inputs JSON NOT NULL, output VARCHAR(255) NOT NULL, step_order INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, display_order INT NOT NULL, is_active TINYINT(1) NOT NULL, is_required TINYINT(1) NOT NULL, INDEX IDX_EFBFBFC21624BCD2 (procedure_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE workflows ADD CONSTRAINT FK_EFBFBFC21624BCD2 FOREIGN KEY (procedure_id) REFERENCES procedures (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE workflows DROP FOREIGN KEY FK_EFBFBFC21624BCD2
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE workflows
        SQL);
    }
}
