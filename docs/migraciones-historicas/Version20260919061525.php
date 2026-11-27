<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260919061525 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE presupuesto_web RENAME INDEX idx_8b65d0a855458d TO IDX_EE325CCC55458D');
        $this->addSql('ALTER TABLE presupuesto_web_comunicacion RENAME INDEX idx_a53c8d326cf9dd50 TO IDX_13C5AF8C202DE922');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE presupuesto_web RENAME INDEX idx_ee325ccc55458d TO IDX_8B65D0A855458D');
        $this->addSql('ALTER TABLE presupuesto_web_comunicacion RENAME INDEX idx_13c5af8c202de922 TO IDX_A53C8D326CF9DD50');
    }
}
