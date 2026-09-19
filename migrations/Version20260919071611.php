<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260919071611 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE presupuesto_web_acceso RENAME INDEX idx_2531742b6cf9dd50 TO IDX_76EB7006202DE922');
        $this->addSql('ALTER TABLE presupuesto_web_acceso RENAME INDEX idx_2531742b366a2741 TO IDX_76EB700638AE5D4E');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE presupuesto_web_acceso RENAME INDEX idx_76eb7006202de922 TO IDX_2531742B6CF9DD50');
        $this->addSql('ALTER TABLE presupuesto_web_acceso RENAME INDEX idx_76eb700638ae5d4e TO IDX_2531742B366A2741');
    }
}
