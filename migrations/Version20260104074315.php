<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260104074315 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE detallecesta ADD coste_actualizado_por_factura TINYINT(1) DEFAULT NULL, ADD factura_origen VARCHAR(255) DEFAULT NULL, ADD fecha_actualizacion_coste DATETIME DEFAULT NULL, ADD coste_anterior DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE detallecesta DROP coste_actualizado_por_factura, DROP factura_origen, DROP fecha_actualizacion_coste, DROP coste_anterior');
    }
}
