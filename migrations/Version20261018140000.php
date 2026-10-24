<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rectificativas totales de tickets TPV: vínculo al original, motivo e idempotencia de rectificación y reembolso';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE documento ADD documento_rectificado_id INT DEFAULT NULL, ADD motivo_rectificacion VARCHAR(255) DEFAULT NULL, ADD tpv_rectificacion_huella VARCHAR(64) DEFAULT NULL, ADD tpv_reembolso_uuid VARCHAR(36) DEFAULT NULL, ADD tpv_reembolso_huella VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_DOCUMENTO_RECTIFICADO ON documento (documento_rectificado_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DOCUMENTO_TPV_REEMBOLSO ON documento (tpv_reembolso_uuid)');
        $this->addSql('ALTER TABLE documento ADD CONSTRAINT FK_DOCUMENTO_RECTIFICADO FOREIGN KEY (documento_rectificado_id) REFERENCES documento (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE documento DROP FOREIGN KEY FK_DOCUMENTO_RECTIFICADO');
        $this->addSql('DROP INDEX IDX_DOCUMENTO_RECTIFICADO ON documento');
        $this->addSql('DROP INDEX UNIQ_DOCUMENTO_TPV_REEMBOLSO ON documento');
        $this->addSql('ALTER TABLE documento DROP documento_rectificado_id, DROP motivo_rectificacion, DROP tpv_rectificacion_huella, DROP tpv_reembolso_uuid, DROP tpv_reembolso_huella');
    }
}
