<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260602054714 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE stock_movimiento (id INT AUTO_INCREMENT NOT NULL, producto_id INT DEFAULT NULL, factura_proveedor_linea_asignacion_id INT DEFAULT NULL, proyecto_id INT DEFAULT NULL, descripcion_producto VARCHAR(255) NOT NULL, referencia_proveedor VARCHAR(100) DEFAULT NULL, tipo_movimiento VARCHAR(50) NOT NULL, cantidad NUMERIC(10, 2) NOT NULL, precio_coste_unitario NUMERIC(10, 2) DEFAULT NULL, fecha DATETIME NOT NULL, observaciones LONGTEXT DEFAULT NULL, creado_en DATETIME NOT NULL, actualizado_en DATETIME DEFAULT NULL, INDEX IDX_66B6E7D67645698E (producto_id), INDEX IDX_66B6E7D6B91C0D94 (factura_proveedor_linea_asignacion_id), INDEX IDX_66B6E7D6F625D1BA (proyecto_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE stock_movimiento ADD CONSTRAINT FK_66B6E7D67645698E FOREIGN KEY (producto_id) REFERENCES catalogo_producto (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE stock_movimiento ADD CONSTRAINT FK_66B6E7D6B91C0D94 FOREIGN KEY (factura_proveedor_linea_asignacion_id) REFERENCES factura_proveedor_linea_asignacion (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE stock_movimiento ADD CONSTRAINT FK_66B6E7D6F625D1BA FOREIGN KEY (proyecto_id) REFERENCES proyecto (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_movimiento DROP FOREIGN KEY FK_66B6E7D67645698E');
        $this->addSql('ALTER TABLE stock_movimiento DROP FOREIGN KEY FK_66B6E7D6B91C0D94');
        $this->addSql('ALTER TABLE stock_movimiento DROP FOREIGN KEY FK_66B6E7D6F625D1BA');
        $this->addSql('DROP TABLE stock_movimiento');
    }
}
