<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250622065342 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE procedures ADD providing_administration_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE procedures ADD CONSTRAINT FK_969AFE428E8B1838 FOREIGN KEY (providing_administration_id) REFERENCES public_entities (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_969AFE428E8B1838 ON procedures (providing_administration_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE procedures DROP FOREIGN KEY FK_969AFE428E8B1838
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_969AFE428E8B1838 ON procedures
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE procedures DROP providing_administration_id
        SQL);
    }
}
