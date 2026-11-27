<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260919093438 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE presupuesto_web_interaccion RENAME INDEX idx_e0421ce36cf9dd50 TO IDX_1D06F271202DE922');
        $this->addSql('ALTER TABLE presupuesto_web_interaccion RENAME INDEX idx_e0421ce3366a2741 TO IDX_1D06F27138AE5D4E');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE presupuesto_web_interaccion RENAME INDEX idx_1d06f271202de922 TO IDX_E0421CE36CF9DD50');
        $this->addSql('ALTER TABLE presupuesto_web_interaccion RENAME INDEX idx_1d06f27138ae5d4e TO IDX_E0421CE3366A2741');
    }
}
