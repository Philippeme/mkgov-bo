<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250621110734 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE documents ADD request_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents ADD CONSTRAINT FK_A2B07288427EB8A5 FOREIGN KEY (request_id) REFERENCES requests (id) ON DELETE SET NULL
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_A2B07288427EB8A5 ON documents (request_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE documents DROP FOREIGN KEY FK_A2B07288427EB8A5
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_A2B07288427EB8A5 ON documents
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents DROP request_id
        SQL);
    }
}
