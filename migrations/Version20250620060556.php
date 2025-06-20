<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250620060556 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE documents DROP FOREIGN KEY FK_A2B07288217BBB47
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_A2B07288217BBB47 ON documents
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents DROP person_id, DROP expiration_date, DROP document_status
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE persons ADD verified_at DATETIME DEFAULT NULL, DROP emergency_contacts, DROP is_active, CHANGE gender gender VARCHAR(1) NOT NULL, CHANGE nationality nationality VARCHAR(100) DEFAULT NULL, CHANGE place_of_birth place_of_birth VARCHAR(100) DEFAULT NULL, CHANGE city city VARCHAR(100) DEFAULT NULL, CHANGE region region VARCHAR(100) DEFAULT NULL, CHANGE profession profession VARCHAR(100) DEFAULT NULL, CHANGE employer employer VARCHAR(255) DEFAULT NULL, CHANGE marital_status marital_status VARCHAR(100) DEFAULT NULL, CHANGE photo image VARCHAR(255) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE documents ADD person_id INT DEFAULT NULL, ADD expiration_date DATE DEFAULT NULL, ADD document_status VARCHAR(30) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents ADD CONSTRAINT FK_A2B07288217BBB47 FOREIGN KEY (person_id) REFERENCES persons (id) ON UPDATE NO ACTION ON DELETE NO ACTION
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_A2B07288217BBB47 ON documents (person_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE persons ADD emergency_contacts JSON DEFAULT NULL, ADD is_active TINYINT(1) NOT NULL, DROP verified_at, CHANGE gender gender VARCHAR(10) NOT NULL, CHANGE nationality nationality VARCHAR(100) NOT NULL, CHANGE place_of_birth place_of_birth VARCHAR(255) NOT NULL, CHANGE city city VARCHAR(100) NOT NULL, CHANGE region region VARCHAR(100) NOT NULL, CHANGE profession profession VARCHAR(150) DEFAULT NULL, CHANGE employer employer VARCHAR(200) DEFAULT NULL, CHANGE marital_status marital_status VARCHAR(20) DEFAULT NULL, CHANGE image photo VARCHAR(255) DEFAULT NULL
        SQL);
    }
}
