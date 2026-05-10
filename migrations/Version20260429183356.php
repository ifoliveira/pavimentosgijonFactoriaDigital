<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260429183356 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE factura_proveedor_linea ADD importe_bruto DOUBLE PRECISION DEFAULT NULL, ADD porcentaje_iva DOUBLE PRECISION DEFAULT NULL, ADD importe_iva DOUBLE PRECISION DEFAULT NULL, ADD tiene_recargo_equivalencia TINYINT(1) NOT NULL, ADD porcentaje_recargo_equivalencia DOUBLE PRECISION DEFAULT NULL, ADD importe_recargo_equivalencia DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE factura_proveedor_linea DROP importe_bruto, DROP porcentaje_iva, DROP importe_iva, DROP tiene_recargo_equivalencia, DROP porcentaje_recargo_equivalencia, DROP importe_recargo_equivalencia');
    }
}
