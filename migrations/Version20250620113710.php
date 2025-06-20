<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250620113710 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE documents CHANGE updated_at updated_at DATETIME NOT NULL, CHANGE status status VARCHAR(50) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE persons ADD postal_code VARCHAR(10) DEFAULT NULL, ADD verified_at DATETIME DEFAULT NULL, CHANGE gender gender VARCHAR(1) NOT NULL, CHANGE nationality nationality VARCHAR(100) DEFAULT NULL, CHANGE place_of_birth place_of_birth VARCHAR(100) DEFAULT NULL, CHANGE city city VARCHAR(100) DEFAULT NULL, CHANGE region region VARCHAR(100) DEFAULT NULL, CHANGE profession profession VARCHAR(100) DEFAULT NULL, CHANGE marital_status marital_status VARCHAR(100) DEFAULT NULL, CHANGE updated_at updated_at DATETIME NOT NULL, CHANGE photo image VARCHAR(255) DEFAULT NULL, CHANGE is_active is_deleted TINYINT(1) NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE documents CHANGE status status VARCHAR(50) DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE persons DROP postal_code, DROP verified_at, CHANGE gender gender VARCHAR(20) NOT NULL, CHANGE nationality nationality VARCHAR(100) NOT NULL, CHANGE place_of_birth place_of_birth VARCHAR(255) NOT NULL, CHANGE city city VARCHAR(100) NOT NULL, CHANGE region region VARCHAR(100) NOT NULL, CHANGE profession profession VARCHAR(255) NOT NULL, CHANGE marital_status marital_status VARCHAR(50) NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE image photo VARCHAR(255) DEFAULT NULL, CHANGE is_deleted is_active TINYINT(1) NOT NULL
        SQL);
    }
}
