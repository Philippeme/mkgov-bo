<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250728155648 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE departments (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, code VARCHAR(100) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, display_order INT NOT NULL, is_active TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE documents (id INT AUTO_INCREMENT NOT NULL, procedure_id INT DEFAULT NULL, person_id INT DEFAULT NULL, request_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(20) NOT NULL, description LONGTEXT DEFAULT NULL, file_path VARCHAR(255) DEFAULT NULL, file_size VARCHAR(100) DEFAULT NULL, mime_type VARCHAR(50) DEFAULT NULL, expiration_date DATE DEFAULT NULL, status VARCHAR(50) NOT NULL, is_required TINYINT(1) NOT NULL, is_active TINYINT(1) NOT NULL, is_deleted TINYINT(1) NOT NULL, display_order INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_A2B072881624BCD2 (procedure_id), INDEX IDX_A2B07288217BBB47 (person_id), INDEX IDX_A2B07288427EB8A5 (request_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE families (id INT AUTO_INCREMENT NOT NULL, fname VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, icon VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, display_order INT NOT NULL, is_active TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE permissions (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, display_order INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE persons (id INT AUTO_INCREMENT NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, middle_name VARCHAR(100) DEFAULT NULL, email VARCHAR(255) NOT NULL, phone_number VARCHAR(20) NOT NULL, date_of_birth DATE NOT NULL, gender VARCHAR(1) NOT NULL, national_id VARCHAR(50) NOT NULL, nationality VARCHAR(100) DEFAULT NULL, place_of_birth VARCHAR(100) DEFAULT NULL, address LONGTEXT NOT NULL, city VARCHAR(100) DEFAULT NULL, region VARCHAR(100) DEFAULT NULL, postal_code VARCHAR(10) DEFAULT NULL, profession VARCHAR(100) DEFAULT NULL, employer VARCHAR(255) DEFAULT NULL, marital_status VARCHAR(100) DEFAULT NULL, image VARCHAR(255) DEFAULT NULL, emergency_contact JSON DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, verified_at DATETIME DEFAULT NULL, display_order INT NOT NULL, is_deleted TINYINT(1) NOT NULL, UNIQUE INDEX UNIQ_A25CC7D3E7927C74 (email), UNIQUE INDEX UNIQ_A25CC7D336491297 (national_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE procedures (id INT AUTO_INCREMENT NOT NULL, family_id INT NOT NULL, providing_administration_id INT DEFAULT NULL, pname VARCHAR(255) NOT NULL, shortdesc LONGTEXT NOT NULL, longdesc LONGTEXT NOT NULL, processtime VARCHAR(100) NOT NULL, servicecost NUMERIC(10, 2) NOT NULL, image VARCHAR(255) DEFAULT NULL, legaltext VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, published TINYINT(1) NOT NULL, display_order INT NOT NULL, is_active TINYINT(1) NOT NULL, INDEX IDX_969AFE42C35E566A (family_id), INDEX IDX_969AFE428E8B1838 (providing_administration_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE projects (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, category VARCHAR(100) NOT NULL, excerpt LONGTEXT NOT NULL, description LONGTEXT NOT NULL, image VARCHAR(255) DEFAULT NULL, featured TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, published TINYINT(1) NOT NULL, display_order INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE public_entities (id INT AUTO_INCREMENT NOT NULL, department_id INT NOT NULL, institution_name VARCHAR(255) NOT NULL, headquarters_address LONGTEXT NOT NULL, phone_number VARCHAR(20) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, contact_person_name VARCHAR(255) DEFAULT NULL, contact_person_email VARCHAR(255) DEFAULT NULL, contact_person_phone VARCHAR(20) DEFAULT NULL, website VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, code VARCHAR(100) DEFAULT NULL, status VARCHAR(50) DEFAULT NULL, logo VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, display_order INT NOT NULL, is_active TINYINT(1) NOT NULL, INDEX IDX_75D8681AAE80F5DF (department_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE requests (id INT AUTO_INCREMENT NOT NULL, procedure_id INT NOT NULL, person_id INT NOT NULL, reference VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL, priority VARCHAR(20) NOT NULL, comments LONGTEXT DEFAULT NULL, admin_notes LONGTEXT DEFAULT NULL, total_cost NUMERIC(10, 2) DEFAULT NULL, paid_amount NUMERIC(10, 2) DEFAULT NULL, payment_status VARCHAR(30) DEFAULT NULL, submitted_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, expected_completion_at DATETIME DEFAULT NULL, display_order INT NOT NULL, is_active TINYINT(1) NOT NULL, is_deleted TINYINT(1) NOT NULL, UNIQUE INDEX UNIQ_7B85D651AEA34913 (reference), INDEX IDX_7B85D6511624BCD2 (procedure_id), INDEX IDX_7B85D651217BBB47 (person_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE roles (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, display_name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, is_active TINYINT(1) NOT NULL, is_system TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, display_order INT NOT NULL, UNIQUE INDEX UNIQ_B63E2EC75E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE role_permissions (role_id INT NOT NULL, permission_id INT NOT NULL, INDEX IDX_1FBA94E6D60322AC (role_id), INDEX IDX_1FBA94E6FED90CCA (permission_id), PRIMARY KEY(role_id, permission_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(100) NOT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, phone_number VARCHAR(20) DEFAULT NULL, avatar VARCHAR(255) DEFAULT NULL, is_active TINYINT(1) NOT NULL, is_verified TINYINT(1) NOT NULL, last_login_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, display_order INT NOT NULL, UNIQUE INDEX UNIQ_1483A5E9F85E0677 (username), UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_roles (user_id INT NOT NULL, role_id INT NOT NULL, INDEX IDX_54FCD59FA76ED395 (user_id), INDEX IDX_54FCD59FD60322AC (role_id), PRIMARY KEY(user_id, role_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE workflows (id INT AUTO_INCREMENT NOT NULL, procedure_id INT NOT NULL, name VARCHAR(255) NOT NULL, short_description LONGTEXT NOT NULL, inputs JSON NOT NULL, output VARCHAR(255) NOT NULL, step_order INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, display_order INT NOT NULL, is_active TINYINT(1) NOT NULL, is_required TINYINT(1) NOT NULL, INDEX IDX_EFBFBFC21624BCD2 (procedure_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', available_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', delivered_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents ADD CONSTRAINT FK_A2B072881624BCD2 FOREIGN KEY (procedure_id) REFERENCES procedures (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents ADD CONSTRAINT FK_A2B07288217BBB47 FOREIGN KEY (person_id) REFERENCES persons (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents ADD CONSTRAINT FK_A2B07288427EB8A5 FOREIGN KEY (request_id) REFERENCES requests (id) ON DELETE SET NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE procedures ADD CONSTRAINT FK_969AFE42C35E566A FOREIGN KEY (family_id) REFERENCES families (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE procedures ADD CONSTRAINT FK_969AFE428E8B1838 FOREIGN KEY (providing_administration_id) REFERENCES public_entities (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE public_entities ADD CONSTRAINT FK_75D8681AAE80F5DF FOREIGN KEY (department_id) REFERENCES departments (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE requests ADD CONSTRAINT FK_7B85D6511624BCD2 FOREIGN KEY (procedure_id) REFERENCES procedures (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE requests ADD CONSTRAINT FK_7B85D651217BBB47 FOREIGN KEY (person_id) REFERENCES persons (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE role_permissions ADD CONSTRAINT FK_1FBA94E6D60322AC FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE role_permissions ADD CONSTRAINT FK_1FBA94E6FED90CCA FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_roles ADD CONSTRAINT FK_54FCD59FA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_roles ADD CONSTRAINT FK_54FCD59FD60322AC FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE workflows ADD CONSTRAINT FK_EFBFBFC21624BCD2 FOREIGN KEY (procedure_id) REFERENCES procedures (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE documents DROP FOREIGN KEY FK_A2B072881624BCD2
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents DROP FOREIGN KEY FK_A2B07288217BBB47
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents DROP FOREIGN KEY FK_A2B07288427EB8A5
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE procedures DROP FOREIGN KEY FK_969AFE42C35E566A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE procedures DROP FOREIGN KEY FK_969AFE428E8B1838
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE public_entities DROP FOREIGN KEY FK_75D8681AAE80F5DF
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE requests DROP FOREIGN KEY FK_7B85D6511624BCD2
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE requests DROP FOREIGN KEY FK_7B85D651217BBB47
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE role_permissions DROP FOREIGN KEY FK_1FBA94E6D60322AC
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE role_permissions DROP FOREIGN KEY FK_1FBA94E6FED90CCA
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_roles DROP FOREIGN KEY FK_54FCD59FA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_roles DROP FOREIGN KEY FK_54FCD59FD60322AC
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE workflows DROP FOREIGN KEY FK_EFBFBFC21624BCD2
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE departments
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE documents
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE families
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE permissions
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE persons
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE procedures
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE projects
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE public_entities
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE requests
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE roles
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE role_permissions
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE users
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_roles
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE workflows
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE messenger_messages
        SQL);
    }
}
