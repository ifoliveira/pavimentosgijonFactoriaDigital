<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260428151730 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE factura_proveedor (id INT AUTO_INCREMENT NOT NULL, proveedor_nombre VARCHAR(255) DEFAULT NULL, numero_factura VARCHAR(100) DEFAULT NULL, fecha_factura DATE DEFAULT NULL, total_base DOUBLE PRECISION DEFAULT NULL, total_iva DOUBLE PRECISION DEFAULT NULL, total_factura DOUBLE PRECISION DEFAULT NULL, json_original JSON DEFAULT NULL, estado_asignacion VARCHAR(50) NOT NULL, fecha_creacion DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE factura_proveedor_linea (id INT AUTO_INCREMENT NOT NULL, factura_proveedor_id INT NOT NULL, proyecto_id INT DEFAULT NULL, producto_id INT DEFAULT NULL, proyecto_gasto_id INT DEFAULT NULL, descripcion VARCHAR(255) DEFAULT NULL, cantidad DOUBLE PRECISION DEFAULT NULL, precio_unitario DOUBLE PRECISION DEFAULT NULL, base DOUBLE PRECISION DEFAULT NULL, iva DOUBLE PRECISION DEFAULT NULL, total DOUBLE PRECISION DEFAULT NULL, tipo_destino VARCHAR(50) DEFAULT NULL, estado VARCHAR(50) NOT NULL, INDEX IDX_61B94C848645029A (factura_proveedor_id), INDEX IDX_61B94C84F625D1BA (proyecto_id), INDEX IDX_61B94C847645698E (producto_id), INDEX IDX_61B94C84A4B068B5 (proyecto_gasto_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE factura_proveedor_linea ADD CONSTRAINT FK_61B94C848645029A FOREIGN KEY (factura_proveedor_id) REFERENCES factura_proveedor (id)');
        $this->addSql('ALTER TABLE factura_proveedor_linea ADD CONSTRAINT FK_61B94C84F625D1BA FOREIGN KEY (proyecto_id) REFERENCES proyecto (id)');
        $this->addSql('ALTER TABLE factura_proveedor_linea ADD CONSTRAINT FK_61B94C847645698E FOREIGN KEY (producto_id) REFERENCES productos (id)');
        $this->addSql('ALTER TABLE factura_proveedor_linea ADD CONSTRAINT FK_61B94C84A4B068B5 FOREIGN KEY (proyecto_gasto_id) REFERENCES proyecto_gasto (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE factura_proveedor_linea DROP FOREIGN KEY FK_61B94C848645029A');
        $this->addSql('ALTER TABLE factura_proveedor_linea DROP FOREIGN KEY FK_61B94C84F625D1BA');
        $this->addSql('ALTER TABLE factura_proveedor_linea DROP FOREIGN KEY FK_61B94C847645698E');
        $this->addSql('ALTER TABLE factura_proveedor_linea DROP FOREIGN KEY FK_61B94C84A4B068B5');
        $this->addSql('DROP TABLE factura_proveedor');
        $this->addSql('DROP TABLE factura_proveedor_linea');
    }
}
