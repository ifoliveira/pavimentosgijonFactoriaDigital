<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Campos minimos para ventas TPV idempotentes sobre Cestas y Detallecesta';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cestas ADD tpv_uuid VARCHAR(36) DEFAULT NULL, ADD tpv_numero_ticket_local VARCHAR(55) DEFAULT NULL, ADD tpv_caja VARCHAR(50) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F1E891F4C33FE044 ON cestas (tpv_uuid)');
        $this->addSql('ALTER TABLE detallecesta ADD tpv_precio_original DOUBLE PRECISION DEFAULT NULL, ADD tpv_tipo_iva DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_F1E891F4C33FE044 ON cestas');
        $this->addSql('ALTER TABLE cestas DROP tpv_uuid, DROP tpv_numero_ticket_local, DROP tpv_caja');
        $this->addSql('ALTER TABLE detallecesta DROP tpv_precio_original, DROP tpv_tipo_iva');
    }
}
