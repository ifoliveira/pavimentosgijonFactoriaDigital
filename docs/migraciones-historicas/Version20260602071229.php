<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260602071229 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_movimiento CHANGE coste_unitario_base coste_unitario_base NUMERIC(10, 2) DEFAULT NULL, CHANGE importe_iva_unitario importe_iva_unitario NUMERIC(10, 2) DEFAULT NULL, CHANGE importe_recargo_unitario importe_recargo_unitario NUMERIC(10, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_movimiento CHANGE coste_unitario_base coste_unitario_base NUMERIC(10, 4) DEFAULT NULL, CHANGE importe_iva_unitario importe_iva_unitario NUMERIC(10, 4) DEFAULT NULL, CHANGE importe_recargo_unitario importe_recargo_unitario NUMERIC(10, 4) DEFAULT NULL');
    }
}
