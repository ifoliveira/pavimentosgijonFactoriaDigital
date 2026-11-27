<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260607105633 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE documento_linea ADD precio_coste_unitario NUMERIC(10, 2) DEFAULT NULL, ADD coste_unitario_base NUMERIC(10, 2) DEFAULT NULL, ADD porcentaje_iva NUMERIC(5, 2) DEFAULT NULL, ADD importe_iva_unitario NUMERIC(10, 2) DEFAULT NULL, ADD tiene_recargo_equivalencia TINYINT(1) DEFAULT 0 NOT NULL, ADD porcentaje_recargo_equivalencia NUMERIC(5, 2) DEFAULT NULL, ADD importe_recargo_unitario NUMERIC(10, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE documento_linea DROP precio_coste_unitario, DROP coste_unitario_base, DROP porcentaje_iva, DROP importe_iva_unitario, DROP tiene_recargo_equivalencia, DROP porcentaje_recargo_equivalencia, DROP importe_recargo_unitario');
    }
}
