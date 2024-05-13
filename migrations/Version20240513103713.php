<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240513103713 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cestas ADD user_admin_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE cestas ADD CONSTRAINT FK_F1E891F484A66610 FOREIGN KEY (user_admin_id) REFERENCES `admin` (id)');
        $this->addSql('CREATE INDEX IDX_F1E891F484A66610 ON cestas (user_admin_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cestas DROP FOREIGN KEY FK_F1E891F484A66610');
        $this->addSql('DROP INDEX IDX_F1E891F484A66610 ON cestas');
        $this->addSql('ALTER TABLE cestas DROP user_admin_id');
    }
}
