<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261110141143 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add staff accounts and loan application assignments';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE loan_applications ADD assigned_staff_id INT DEFAULT NULL');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              loan_applications
            ADD
              CONSTRAINT fk_loan_applications_assigned_staff FOREIGN KEY (assigned_staff_id) REFERENCES user (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql('CREATE INDEX idx_assigned_staff_id ON loan_applications (assigned_staff_id)');
        $this->addSql('ALTER TABLE user ADD is_staff TINYINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE loan_applications DROP FOREIGN KEY fk_loan_applications_assigned_staff');
        $this->addSql('DROP INDEX idx_assigned_staff_id ON loan_applications');
        $this->addSql('ALTER TABLE loan_applications DROP assigned_staff_id');
        $this->addSql('ALTER TABLE user DROP is_staff');
    }
}
