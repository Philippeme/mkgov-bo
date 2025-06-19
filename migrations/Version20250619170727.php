<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250619170727 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE procedures CHANGE published published TINYINT(1) NOT NULL, CHANGE display_order display_order INT NOT NULL, CHANGE is_active is_active TINYINT(1) NOT NULL, CHANGE family_id family_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE procedures ADD CONSTRAINT FK_969AFE42C35E566A FOREIGN KEY (family_id) REFERENCES families (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_969AFE42C35E566A ON procedures (family_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE roles CHANGE is_active is_active TINYINT(1) NOT NULL, CHANGE is_system is_system TINYINT(1) NOT NULL, CHANGE display_order display_order INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE role_permissions RENAME INDEX idx_5256d9b3d60322ac TO IDX_1FBA94E6D60322AC
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE role_permissions RENAME INDEX idx_5256d9b3fed90cca TO IDX_1FBA94E6FED90CCA
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users CHANGE is_active is_active TINYINT(1) NOT NULL, CHANGE is_verified is_verified TINYINT(1) NOT NULL, CHANGE display_order display_order INT NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE procedures DROP FOREIGN KEY FK_969AFE42C35E566A
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_969AFE42C35E566A ON procedures
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE procedures CHANGE family_id family_id INT DEFAULT NULL, CHANGE published published TINYINT(1) DEFAULT 1 NOT NULL, CHANGE display_order display_order INT DEFAULT 0 NOT NULL, CHANGE is_active is_active TINYINT(1) DEFAULT 1 NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE role_permissions RENAME INDEX idx_1fba94e6d60322ac TO IDX_5256D9B3D60322AC
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE role_permissions RENAME INDEX idx_1fba94e6fed90cca TO IDX_5256D9B3FED90CCA
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE roles CHANGE is_active is_active TINYINT(1) DEFAULT 1 NOT NULL, CHANGE is_system is_system TINYINT(1) DEFAULT 0 NOT NULL, CHANGE display_order display_order INT DEFAULT 0 NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users CHANGE is_active is_active TINYINT(1) DEFAULT 1 NOT NULL, CHANGE is_verified is_verified TINYINT(1) DEFAULT 0 NOT NULL, CHANGE display_order display_order INT DEFAULT 0 NOT NULL
        SQL);
    }
}
