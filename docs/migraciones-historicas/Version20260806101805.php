<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260806101805 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE proyecto_cobro ADD efectivo_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE proyecto_cobro ADD CONSTRAINT FK_F24965686566D8A0 FOREIGN KEY (efectivo_id) REFERENCES efectivo (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_F24965686566D8A0 ON proyecto_cobro (efectivo_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE proyecto_cobro DROP FOREIGN KEY FK_F24965686566D8A0');
        $this->addSql('DROP INDEX IDX_F24965686566D8A0 ON proyecto_cobro');
        $this->addSql('ALTER TABLE proyecto_cobro DROP efectivo_id');
    }
}
