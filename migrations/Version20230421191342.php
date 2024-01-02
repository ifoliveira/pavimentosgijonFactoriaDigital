<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230421191342 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE efectivo ADD presupuestoef_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE efectivo ADD CONSTRAINT FK_94D7FEC0C3DDDF5B FOREIGN KEY (presupuestoef_id) REFERENCES presupuestos (id)');
        $this->addSql('CREATE INDEX IDX_94D7FEC0C3DDDF5B ON efectivo (presupuestoef_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE efectivo DROP FOREIGN KEY FK_94D7FEC0C3DDDF5B');
        $this->addSql('DROP INDEX IDX_94D7FEC0C3DDDF5B ON efectivo');
        $this->addSql('ALTER TABLE efectivo DROP presupuestoef_id');
            }
}
