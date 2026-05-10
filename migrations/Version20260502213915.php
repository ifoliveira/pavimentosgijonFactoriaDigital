<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260502213915 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE documento_linea ADD catalogo_producto_id INT DEFAULT NULL, ADD origen_linea VARCHAR(30) NOT NULL DEFAULT \'manual\'');
        $this->addSql('ALTER TABLE documento_linea ADD CONSTRAINT FK_208105761EFE0C91 FOREIGN KEY (catalogo_producto_id) REFERENCES catalogo_producto (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_208105761EFE0C91 ON documento_linea (catalogo_producto_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE documento_linea DROP FOREIGN KEY FK_208105761EFE0C91');
        $this->addSql('DROP INDEX IDX_208105761EFE0C91 ON documento_linea');
        $this->addSql('ALTER TABLE documento_linea DROP catalogo_producto_id, DROP origen_linea');
    }
}
