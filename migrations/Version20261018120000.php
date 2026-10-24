<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ventas TPV como Documentos: UUID único, referencias TPV y precios con IVA para conservar los totales';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE documento ADD tpv_uuid VARCHAR(36) DEFAULT NULL, ADD tpv_numero_ticket_local VARCHAR(55) DEFAULT NULL, ADD tpv_caja VARCHAR(50) DEFAULT NULL, ADD tpv_forma_pago VARCHAR(20) DEFAULT NULL, ADD tpv_fecha DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DOCUMENTO_TPV_UUID ON documento (tpv_uuid)');
        $this->addSql('ALTER TABLE documento_linea ADD precio_unitario_con_iva NUMERIC(10, 2) DEFAULT NULL, ADD tpv_precio_original NUMERIC(10, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_DOCUMENTO_TPV_UUID ON documento');
        $this->addSql('ALTER TABLE documento DROP tpv_uuid, DROP tpv_numero_ticket_local, DROP tpv_caja, DROP tpv_forma_pago, DROP tpv_fecha');
        $this->addSql('ALTER TABLE documento_linea DROP precio_unitario_con_iva, DROP tpv_precio_original');
    }
}
