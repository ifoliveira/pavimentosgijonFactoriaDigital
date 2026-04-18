<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260416093225 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE mano_obra_texto_seleccionado (id INT AUTO_INCREMENT NOT NULL, mano_obra_id INT NOT NULL, texto_mano_obra_id INT NOT NULL, orden INT DEFAULT NULL, INDEX IDX_F56E87FF3DCAD41B (mano_obra_id), INDEX IDX_F56E87FFB0696FF7 (texto_mano_obra_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE mano_obra_texto_seleccionado ADD CONSTRAINT FK_F56E87FF3DCAD41B FOREIGN KEY (mano_obra_id) REFERENCES mano_obra (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mano_obra_texto_seleccionado ADD CONSTRAINT FK_F56E87FFB0696FF7 FOREIGN KEY (texto_mano_obra_id) REFERENCES texto_mano_obra (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mano_obra_texto_seleccionado DROP FOREIGN KEY FK_F56E87FF3DCAD41B');
        $this->addSql('ALTER TABLE mano_obra_texto_seleccionado DROP FOREIGN KEY FK_F56E87FFB0696FF7');
        $this->addSql('DROP TABLE mano_obra_texto_seleccionado');
    }
}
