<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Exception\IrreversibleMigration;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261110131132 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE loan_applications (id INT AUTO_INCREMENT NOT NULL, loan_type VARCHAR(30) NOT NULL, id_type VARCHAR(50) NOT NULL, employment_status VARCHAR(20) NOT NULL, occupation VARCHAR(100) NOT NULL, employer_name VARCHAR(150) DEFAULT NULL, length_of_employment VARCHAR(50) DEFAULT NULL, employer_contact_number VARCHAR(20) DEFAULT NULL, business_name VARCHAR(150) DEFAULT NULL, business_type VARCHAR(100) DEFAULT NULL, monthly_income NUMERIC(12, 2) NOT NULL, payment_frequency VARCHAR(20) DEFAULT \'monthly\' NOT NULL, interest_rate NUMERIC(5, 2) DEFAULT NULL, next_due_date DATE DEFAULT NULL, amount NUMERIC(12, 2) NOT NULL, term_months SMALLINT UNSIGNED NOT NULL, purpose VARCHAR(500) NOT NULL, valid_id_path VARCHAR(255) NOT NULL, proof_of_income_path VARCHAR(255) NOT NULL, proof_of_address_path VARCHAR(255) NOT NULL, employment_certificate_path VARCHAR(255) DEFAULT NULL, status ENUM(\'pending\', \'approved\', \'rejected\') DEFAULT \'pending\' NOT NULL, submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, user_id INT NOT NULL, INDEX idx_user_id (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE login_attempts (id INT UNSIGNED AUTO_INCREMENT NOT NULL, username VARCHAR(50) NOT NULL, attempted_at DATETIME NOT NULL, INDEX idx_username_time (username, attempted_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE notifications (id INT AUTO_INCREMENT NOT NULL, message VARCHAR(255) NOT NULL, type VARCHAR(30) NOT NULL, is_read TINYINT DEFAULT 0 NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, user_id INT NOT NULL, INDEX idx_notifications_user_read (user_id, is_read, created_at), INDEX IDX_6000B0D3A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE payments (id INT AUTO_INCREMENT NOT NULL, payment_date DATE NOT NULL, amount_paid NUMERIC(12, 2) NOT NULL, payment_method VARCHAR(20) NOT NULL, reference_number VARCHAR(100) DEFAULT NULL, proof_of_payment_path VARCHAR(255) DEFAULT NULL, status VARCHAR(20) DEFAULT \'confirmed\' NOT NULL, recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, loan_id INT NOT NULL, INDEX idx_payments_date (payment_date), INDEX idx_payments_loan_status (loan_id, status), UNIQUE INDEX uq_payments_reference (reference_number), INDEX IDX_65D29B32CE73868F (loan_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE remember_tokens (id INT UNSIGNED AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, token_hash CHAR(64) NOT NULL, expires_at DATETIME NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX user_id (user_id), INDEX expires_at (expires_at), UNIQUE INDEX token_hash (token_hash), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE system_settings (setting_key VARCHAR(50) NOT NULL, setting_value VARCHAR(255) NOT NULL, PRIMARY KEY (setting_key)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(50) NOT NULL, first_name VARCHAR(50) NOT NULL, middle_name VARCHAR(50) DEFAULT NULL, last_name VARCHAR(50) NOT NULL, email VARCHAR(150) NOT NULL, address VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, is_admin TINYINT DEFAULT 0 NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, phone_number VARCHAR(20) NOT NULL, UNIQUE INDEX username (username), UNIQUE INDEX email (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE loan_applications ADD CONSTRAINT fk_loan_applications_user FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT notifications_ibfk_1 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT payments_ibfk_1 FOREIGN KEY (loan_id) REFERENCES loan_applications (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        throw new IrreversibleMigration('The initial Mletchido schema migration is intentionally irreversible.');
    }
}
