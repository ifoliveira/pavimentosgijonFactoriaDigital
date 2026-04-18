<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260402113625 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE coste_gremio (id INT AUTO_INCREMENT NOT NULL, proyecto_id INT NOT NULL, banco_id INT DEFAULT NULL, gremio VARCHAR(150) NOT NULL, concepto LONGTEXT DEFAULT NULL, importe_estimado NUMERIC(10, 2) NOT NULL, importe_real NUMERIC(10, 2) DEFAULT NULL, estado_pago VARCHAR(20) NOT NULL, metodo_pago VARCHAR(20) DEFAULT NULL, fecha_pago DATE DEFAULT NULL, numero_factura_gremio VARCHAR(100) DEFAULT NULL, notas LONGTEXT DEFAULT NULL, creado_en DATETIME NOT NULL, actualizado_en DATETIME DEFAULT NULL, INDEX IDX_A9B4FE82F625D1BA (proyecto_id), INDEX IDX_A9B4FE82CC04A73E (banco_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE documento (id INT AUTO_INCREMENT NOT NULL, cliente_id INT NOT NULL, proyecto_id INT DEFAULT NULL, factura_vinculada_id INT DEFAULT NULL, serie VARCHAR(10) NOT NULL, numero INT NOT NULL, tipo_documento VARCHAR(20) NOT NULL, estado_comercial VARCHAR(20) NOT NULL, estado_cobro VARCHAR(20) NOT NULL, estado_ejecucion VARCHAR(20) NOT NULL, fecha_emision DATE NOT NULL, fecha_vencimiento DATE DEFAULT NULL, fecha_aceptacion DATE DEFAULT NULL, base_imponible NUMERIC(10, 2) NOT NULL, total_iva NUMERIC(10, 2) NOT NULL, total NUMERIC(10, 2) NOT NULL, total_cobrado NUMERIC(10, 2) NOT NULL, total_coste NUMERIC(10, 2) NOT NULL, notas LONGTEXT DEFAULT NULL, creado_en DATETIME NOT NULL, actualizado_en DATETIME DEFAULT NULL, INDEX IDX_B6B12EC7DE734E51 (cliente_id), INDEX IDX_B6B12EC7F625D1BA (proyecto_id), INDEX IDX_B6B12EC761A8A4DE (factura_vinculada_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE documento_cobro (id INT AUTO_INCREMENT NOT NULL, documento_id INT NOT NULL, banco_id INT DEFAULT NULL, fecha DATE NOT NULL, metodo VARCHAR(20) NOT NULL, importe_bruto NUMERIC(10, 2) NOT NULL, porcentaje_recargo NUMERIC(5, 2) NOT NULL, importe_recargo NUMERIC(10, 2) NOT NULL, importe_neto NUMERIC(10, 2) NOT NULL, referencia VARCHAR(100) DEFAULT NULL, notas LONGTEXT DEFAULT NULL, creado_en DATETIME NOT NULL, INDEX IDX_6C9B9D8E45C0CF75 (documento_id), INDEX IDX_6C9B9D8ECC04A73E (banco_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE documento_linea (id INT AUTO_INCREMENT NOT NULL, documento_id INT NOT NULL, producto_id INT DEFAULT NULL, origen_presupuesto_id INT DEFAULT NULL, posicion INT NOT NULL, tipo_linea VARCHAR(20) NOT NULL, descripcion LONGTEXT NOT NULL, cantidad NUMERIC(10, 3) NOT NULL, unidad VARCHAR(20) NOT NULL, precio_unitario NUMERIC(10, 2) NOT NULL, coste_unitario NUMERIC(10, 2) NOT NULL, descuento NUMERIC(5, 2) NOT NULL, tipo_iva NUMERIC(5, 2) NOT NULL, subtotal NUMERIC(10, 2) NOT NULL, total_iva NUMERIC(10, 2) NOT NULL, total_coste NUMERIC(10, 2) NOT NULL, afecta_stock TINYINT(1) NOT NULL, stock_movido TINYINT(1) NOT NULL, INDEX IDX_2081057645C0CF75 (documento_id), INDEX IDX_208105767645698E (producto_id), INDEX IDX_20810576DD2DCB24 (origen_presupuesto_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE movimiento_stock (id INT AUTO_INCREMENT NOT NULL, producto_id INT NOT NULL, documento_linea_id INT DEFAULT NULL, tipo VARCHAR(20) NOT NULL, cantidad NUMERIC(10, 3) NOT NULL, ajuste_negativo TINYINT(1) NOT NULL, coste_unitario NUMERIC(10, 2) NOT NULL, stock_resultante NUMERIC(10, 3) NOT NULL, fecha DATE NOT NULL, creado_en DATETIME NOT NULL, motivo LONGTEXT DEFAULT NULL, INDEX IDX_4AEEC2D07645698E (producto_id), INDEX IDX_4AEEC2D0D1F59601 (documento_linea_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE proyecto (id INT AUTO_INCREMENT NOT NULL, cliente_id INT NOT NULL, nombre VARCHAR(255) NOT NULL, fecha_inicio DATE NOT NULL, fecha_fin_prevista DATE DEFAULT NULL, fecha_fin_real DATE DEFAULT NULL, notas LONGTEXT DEFAULT NULL, total_presupuestado NUMERIC(10, 2) NOT NULL, total_facturado NUMERIC(10, 2) NOT NULL, total_cobrado NUMERIC(10, 2) NOT NULL, creado_en DATETIME NOT NULL, actualizado_en DATETIME DEFAULT NULL, INDEX IDX_6FD202B9DE734E51 (cliente_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE serie_documento (id INT AUTO_INCREMENT NOT NULL, codigo VARCHAR(10) NOT NULL, ultimo_numero INT NOT NULL, creado_en DATETIME NOT NULL, actualizado_en DATETIME DEFAULT NULL, UNIQUE INDEX uniq_serie_documento_codigo (codigo), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE coste_gremio ADD CONSTRAINT FK_A9B4FE82F625D1BA FOREIGN KEY (proyecto_id) REFERENCES proyecto (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE coste_gremio ADD CONSTRAINT FK_A9B4FE82CC04A73E FOREIGN KEY (banco_id) REFERENCES banco (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE documento ADD CONSTRAINT FK_B6B12EC7DE734E51 FOREIGN KEY (cliente_id) REFERENCES clientes (id)');
        $this->addSql('ALTER TABLE documento ADD CONSTRAINT FK_B6B12EC7F625D1BA FOREIGN KEY (proyecto_id) REFERENCES proyecto (id)');
        $this->addSql('ALTER TABLE documento ADD CONSTRAINT FK_B6B12EC761A8A4DE FOREIGN KEY (factura_vinculada_id) REFERENCES documento (id)');
        $this->addSql('ALTER TABLE documento_cobro ADD CONSTRAINT FK_6C9B9D8E45C0CF75 FOREIGN KEY (documento_id) REFERENCES documento (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE documento_cobro ADD CONSTRAINT FK_6C9B9D8ECC04A73E FOREIGN KEY (banco_id) REFERENCES banco (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE documento_linea ADD CONSTRAINT FK_2081057645C0CF75 FOREIGN KEY (documento_id) REFERENCES documento (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE documento_linea ADD CONSTRAINT FK_208105767645698E FOREIGN KEY (producto_id) REFERENCES productos (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE documento_linea ADD CONSTRAINT FK_20810576DD2DCB24 FOREIGN KEY (origen_presupuesto_id) REFERENCES documento (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE movimiento_stock ADD CONSTRAINT FK_4AEEC2D07645698E FOREIGN KEY (producto_id) REFERENCES productos (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE movimiento_stock ADD CONSTRAINT FK_4AEEC2D0D1F59601 FOREIGN KEY (documento_linea_id) REFERENCES documento_linea (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE proyecto ADD CONSTRAINT FK_6FD202B9DE734E51 FOREIGN KEY (cliente_id) REFERENCES clientes (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE coste_gremio DROP FOREIGN KEY FK_A9B4FE82F625D1BA');
        $this->addSql('ALTER TABLE coste_gremio DROP FOREIGN KEY FK_A9B4FE82CC04A73E');
        $this->addSql('ALTER TABLE documento DROP FOREIGN KEY FK_B6B12EC7DE734E51');
        $this->addSql('ALTER TABLE documento DROP FOREIGN KEY FK_B6B12EC7F625D1BA');
        $this->addSql('ALTER TABLE documento DROP FOREIGN KEY FK_B6B12EC761A8A4DE');
        $this->addSql('ALTER TABLE documento_cobro DROP FOREIGN KEY FK_6C9B9D8E45C0CF75');
        $this->addSql('ALTER TABLE documento_cobro DROP FOREIGN KEY FK_6C9B9D8ECC04A73E');
        $this->addSql('ALTER TABLE documento_linea DROP FOREIGN KEY FK_2081057645C0CF75');
        $this->addSql('ALTER TABLE documento_linea DROP FOREIGN KEY FK_208105767645698E');
        $this->addSql('ALTER TABLE documento_linea DROP FOREIGN KEY FK_20810576DD2DCB24');
        $this->addSql('ALTER TABLE movimiento_stock DROP FOREIGN KEY FK_4AEEC2D07645698E');
        $this->addSql('ALTER TABLE movimiento_stock DROP FOREIGN KEY FK_4AEEC2D0D1F59601');
        $this->addSql('ALTER TABLE proyecto DROP FOREIGN KEY FK_6FD202B9DE734E51');
        $this->addSql('DROP TABLE coste_gremio');
        $this->addSql('DROP TABLE documento');
        $this->addSql('DROP TABLE documento_cobro');
        $this->addSql('DROP TABLE documento_linea');
        $this->addSql('DROP TABLE movimiento_stock');
        $this->addSql('DROP TABLE proyecto');
        $this->addSql('DROP TABLE serie_documento');
    }
}
