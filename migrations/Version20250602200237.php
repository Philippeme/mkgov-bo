<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250602200237 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE procedures (id INT AUTO_INCREMENT NOT NULL, pname VARCHAR(255) NOT NULL, family VARCHAR(100) NOT NULL, excerpt LONGTEXT NOT NULL, shortdesc LONGTEXT NOT NULL, longdesc LONGTEXT NOT NULL, processtime LONGTEXT NOT NULL, servicecost NUMERIC(10, 0) NOT NULL, image VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, published TINYINT(1) NOT NULL, display_order INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        // Create users table
        $this->addSql(<<<'SQL'
            CREATE TABLE users (
                id INT AUTO_INCREMENT NOT NULL, 
                username VARCHAR(100) NOT NULL, 
                email VARCHAR(255) NOT NULL, 
                password VARCHAR(255) NOT NULL, 
                first_name VARCHAR(100) NOT NULL, 
                last_name VARCHAR(100) NOT NULL, 
                phone_number VARCHAR(20) DEFAULT NULL, 
                avatar VARCHAR(255) DEFAULT NULL, 
                is_active TINYINT(1) NOT NULL DEFAULT 1, 
                is_verified TINYINT(1) NOT NULL DEFAULT 0, 
                last_login_at DATETIME DEFAULT NULL, 
                created_at DATETIME NOT NULL, 
                updated_at DATETIME NOT NULL, 
                display_order INT NOT NULL DEFAULT 0, 
                UNIQUE INDEX UNIQ_1483A5E9F85E0677 (username), 
                UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), 
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        // Create roles table
        $this->addSql(<<<'SQL'
            CREATE TABLE roles (
                id INT AUTO_INCREMENT NOT NULL, 
                name VARCHAR(100) NOT NULL, 
                display_name VARCHAR(255) NOT NULL, 
                description LONGTEXT DEFAULT NULL, 
                is_active TINYINT(1) NOT NULL DEFAULT 1, 
                is_system TINYINT(1) NOT NULL DEFAULT 0, 
                created_at DATETIME NOT NULL, 
                updated_at DATETIME NOT NULL, 
                display_order INT NOT NULL DEFAULT 0, 
                UNIQUE INDEX UNIQ_B63E2EC75E237E06 (name), 
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        // Create user_roles junction table
        $this->addSql(<<<'SQL'
            CREATE TABLE user_roles (
                user_id INT NOT NULL, 
                role_id INT NOT NULL, 
                INDEX IDX_54FCD59FA76ED395 (user_id), 
                INDEX IDX_54FCD59FD60322AC (role_id), 
                PRIMARY KEY(user_id, role_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        // Create role_permissions junction table
        $this->addSql(<<<'SQL'
            CREATE TABLE role_permissions (
                role_id INT NOT NULL, 
                permission_id INT NOT NULL, 
                INDEX IDX_5256D9B3D60322AC (role_id), 
                INDEX IDX_5256D9B3FED90CCA (permission_id), 
                PRIMARY KEY(role_id, permission_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        // Add foreign key constraints
        $this->addSql('ALTER TABLE user_roles ADD CONSTRAINT FK_54FCD59FA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_roles ADD CONSTRAINT FK_54FCD59FD60322AC FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE role_permissions ADD CONSTRAINT FK_5256D9B3D60322AC FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE role_permissions ADD CONSTRAINT FK_5256D9B3FED90CCA FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE');

        // Insert default roles
        $this->addSql("INSERT INTO roles (name, display_name, description, is_system, is_active, display_order, created_at, updated_at) VALUES 
            ('ROLE_SUPER_ADMIN', 'Super Administrator', 'Full system access and administration rights', 1, 1, 1, NOW(), NOW()),
            ('ROLE_ADMIN', 'Administrator', 'Administrative access to most features', 1, 1, 2, NOW(), NOW()),
            ('ROLE_MANAGER', 'Manager', 'Management level access with limited administrative rights', 1, 1, 3, NOW(), NOW()),
            ('ROLE_USER', 'User', 'Standard user access', 1, 1, 4, NOW(), NOW())
        ");

        // Insert default admin user (password: admin123)
        $this->addSql("INSERT INTO users (username, email, password, first_name, last_name, is_active, is_verified, display_order, created_at, updated_at) VALUES 
            ('admin', 'admin@mkgov.cm', '\$2y\$13\$NtDROl4iyw/zJol2el27k.9O/78XAfVGIYQIzj6WLa.McU8rir62e', 'System', 'Administrator', 1, 1, 1, NOW(), NOW())
        ");

        // Assign ROLE_SUPER_ADMIN to default admin user
        $this->addSql("INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u, roles r WHERE u.username = 'admin' AND r.name = 'ROLE_SUPER_ADMIN'");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            DROP TABLE procedures
        SQL);

        // Drop foreign key constraints first
        $this->addSql('ALTER TABLE user_roles DROP FOREIGN KEY FK_54FCD59FA76ED395');
        $this->addSql('ALTER TABLE user_roles DROP FOREIGN KEY FK_54FCD59FD60322AC');
        $this->addSql('ALTER TABLE role_permissions DROP FOREIGN KEY FK_5256D9B3D60322AC');
        $this->addSql('ALTER TABLE role_permissions DROP FOREIGN KEY FK_5256D9B3FED90CCA');

        // Drop tables
        $this->addSql('DROP TABLE user_roles');
        $this->addSql('DROP TABLE role_permissions');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE roles');
    }
}
