<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration complète pour le système d'utilisateurs et rôles
 */
final class Version20250618120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create complete user management system with roles and permissions';
    }

    public function up(Schema $schema): void
    {
        // Create permissions table
        $this->addSql(<<<'SQL'
            CREATE TABLE permissions (
                id INT AUTO_INCREMENT NOT NULL, 
                name VARCHAR(255) NOT NULL, 
                description LONGTEXT NOT NULL, 
                created_at DATETIME NOT NULL, 
                updated_at DATETIME NOT NULL, 
                display_order INT NOT NULL DEFAULT 0, 
                UNIQUE INDEX UNIQ_2DEDCC6F5E237E06 (name), 
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

        // Create families table
        $this->addSql(<<<'SQL'
            CREATE TABLE families (
                id INT AUTO_INCREMENT NOT NULL, 
                fname VARCHAR(255) NOT NULL, 
                description LONGTEXT NOT NULL, 
                created_at DATETIME NOT NULL, 
                updated_at DATETIME NOT NULL, 
                display_order INT NOT NULL DEFAULT 0, 
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        // Create procedures table
        $this->addSql(<<<'SQL'
            CREATE TABLE procedures (
                id INT AUTO_INCREMENT NOT NULL, 
                pname VARCHAR(255) NOT NULL, 
                family VARCHAR(100) NOT NULL, 
                shortdesc LONGTEXT NOT NULL, 
                longdesc LONGTEXT NOT NULL, 
                processtime INT NOT NULL, 
                servicecost DECIMAL(10, 2) NOT NULL, 
                image VARCHAR(255) DEFAULT NULL, 
                created_at DATETIME NOT NULL, 
                updated_at DATETIME NOT NULL, 
                published TINYINT(1) NOT NULL DEFAULT 1, 
                display_order INT NOT NULL DEFAULT 0, 
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        // Create institutions table
        $this->addSql(<<<'SQL'
            CREATE TABLE institutions (
                id INT AUTO_INCREMENT NOT NULL, 
                iname VARCHAR(255) NOT NULL, 
                hq LONGTEXT NOT NULL, 
                department VARCHAR(255) NOT NULL, 
                phonenumber VARCHAR(255) NOT NULL, 
                email VARCHAR(255) NOT NULL, 
                contactperson VARCHAR(255) NOT NULL, 
                created_at DATETIME NOT NULL, 
                updated_at DATETIME NOT NULL, 
                display_order INT NOT NULL DEFAULT 0, 
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        // Create documents table
        $this->addSql(<<<'SQL'
            CREATE TABLE documents (
                id INT AUTO_INCREMENT NOT NULL, 
                dname VARCHAR(255) NOT NULL, 
                description LONGTEXT NOT NULL, 
                category VARCHAR(100) NOT NULL, 
                pdf VARCHAR(255) DEFAULT NULL, 
                created_at DATETIME NOT NULL, 
                updated_at DATETIME NOT NULL, 
                display_order INT NOT NULL DEFAULT 0, 
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        // Create persons table
        $this->addSql(<<<'SQL'
            CREATE TABLE persons (
                id INT AUTO_INCREMENT NOT NULL, 
                first_name VARCHAR(100) NOT NULL, 
                last_name VARCHAR(100) NOT NULL, 
                middle_name VARCHAR(100) DEFAULT NULL, 
                email VARCHAR(255) NOT NULL, 
                phone_number VARCHAR(20) NOT NULL, 
                date_of_birth DATE NOT NULL, 
                gender VARCHAR(1) NOT NULL, 
                national_id VARCHAR(50) NOT NULL, 
                nationality VARCHAR(100) DEFAULT 'Cameroonian', 
                place_of_birth VARCHAR(100) DEFAULT NULL, 
                address LONGTEXT NOT NULL, 
                city VARCHAR(100) DEFAULT NULL, 
                region VARCHAR(100) DEFAULT NULL, 
                postal_code VARCHAR(10) DEFAULT NULL, 
                profession VARCHAR(100) DEFAULT NULL, 
                employer VARCHAR(255) DEFAULT NULL, 
                marital_status VARCHAR(100) DEFAULT NULL, 
                image VARCHAR(255) DEFAULT NULL, 
                created_at DATETIME NOT NULL, 
                updated_at DATETIME NOT NULL, 
                verified_at DATETIME DEFAULT NULL, 
                display_order INT NOT NULL DEFAULT 0, 
                UNIQUE INDEX UNIQ_A25D7D48E7927C74 (email), 
                UNIQUE INDEX UNIQ_A25D7D48AA1D0712 (national_id), 
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        // Create junction tables
        $this->addSql(<<<'SQL'
            CREATE TABLE user_roles (
                user_id INT NOT NULL, 
                role_id INT NOT NULL, 
                INDEX IDX_54FCD59FA76ED395 (user_id), 
                INDEX IDX_54FCD59FD60322AC (role_id), 
                PRIMARY KEY(user_id, role_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

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

        // Insert default permissions
        $this->addSql("INSERT INTO permissions (name, description, display_order, created_at, updated_at) VALUES 
            ('user.view', 'View users', 1, NOW(), NOW()),
            ('user.create', 'Create users', 2, NOW(), NOW()),
            ('user.edit', 'Edit users', 3, NOW(), NOW()),
            ('user.delete', 'Delete users', 4, NOW(), NOW()),
            ('role.view', 'View roles', 5, NOW(), NOW()),
            ('role.create', 'Create roles', 6, NOW(), NOW()),
            ('role.edit', 'Edit roles', 7, NOW(), NOW()),
            ('role.delete', 'Delete roles', 8, NOW(), NOW()),
            ('permission.view', 'View permissions', 9, NOW(), NOW()),
            ('permission.manage', 'Manage permissions', 10, NOW(), NOW()),
            ('procedure.view', 'View procedures', 11, NOW(), NOW()),
            ('procedure.create', 'Create procedures', 12, NOW(), NOW()),
            ('procedure.edit', 'Edit procedures', 13, NOW(), NOW()),
            ('procedure.delete', 'Delete procedures', 14, NOW(), NOW()),
            ('admin.access', 'Access administration area', 15, NOW(), NOW())
        ");

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

        // Assign all permissions to SUPER_ADMIN role
        $this->addSql("INSERT INTO role_permissions (role_id, permission_id) 
            SELECT r.id, p.id FROM roles r, permissions p WHERE r.name = 'ROLE_SUPER_ADMIN'");

        // Assign ROLE_SUPER_ADMIN to default admin user
        $this->addSql("INSERT INTO user_roles (user_id, role_id) 
            SELECT u.id, r.id FROM users u, roles r WHERE u.username = 'admin' AND r.name = 'ROLE_SUPER_ADMIN'");

        // Insert sample families
        $this->addSql("INSERT INTO families (fname, description, display_order, created_at, updated_at) VALUES 
            ('Police & Justice', 'Services related to police and justice matters including passports, national ID, criminal records', 1, NOW(), NOW()),
            ('Family Services', 'Civil status documents including birth, marriage, and death certificates', 2, NOW(), NOW()),
            ('Transport', 'Vehicle and transportation related services including driving licenses and vehicle registration', 3, NOW(), NOW()),
            ('Education', 'Educational services including diploma certification and school authorization', 4, NOW(), NOW()),
            ('Business', 'Business registration and commercial services', 5, NOW(), NOW())
        ");

        // Create messenger_messages table if it doesn't exist
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS messenger_messages (
                id BIGINT AUTO_INCREMENT NOT NULL, 
                body LONGTEXT NOT NULL, 
                headers LONGTEXT NOT NULL, 
                queue_name VARCHAR(190) NOT NULL, 
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', 
                available_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', 
                delivered_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', 
                INDEX IDX_75EA56E0FB7336F0 (queue_name), 
                INDEX IDX_75EA56E0E3BD61CE (available_at), 
                INDEX IDX_75EA56E016BA31DB (delivered_at), 
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
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
        $this->addSql('DROP TABLE permissions');
        $this->addSql('DROP TABLE families');
        $this->addSql('DROP TABLE procedures');
        $this->addSql('DROP TABLE institutions');
        $this->addSql('DROP TABLE documents');
        $this->addSql('DROP TABLE persons');
        $this->addSql('DROP TABLE messenger_messages');
    }
}