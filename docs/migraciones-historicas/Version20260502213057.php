<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260502213057 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE catalogo_producto (id INT AUTO_INCREMENT NOT NULL, codigo VARCHAR(80) DEFAULT NULL, nombre VARCHAR(255) NOT NULL, descripcion LONGTEXT DEFAULT NULL, familia VARCHAR(80) NOT NULL, subfamilia VARCHAR(100) DEFAULT NULL, marca VARCHAR(100) DEFAULT NULL, modelo VARCHAR(100) DEFAULT NULL, unidad VARCHAR(20) NOT NULL, precio_venta NUMERIC(10, 2) NOT NULL, precio_coste NUMERIC(10, 2) NOT NULL, tipo_iva NUMERIC(5, 2) NOT NULL, ancho NUMERIC(8, 2) DEFAULT NULL, alto NUMERIC(8, 2) DEFAULT NULL, largo NUMERIC(8, 2) DEFAULT NULL, fondo NUMERIC(8, 2) DEFAULT NULL, medida_texto VARCHAR(100) DEFAULT NULL, atributos JSON DEFAULT NULL, controla_stock TINYINT(1) NOT NULL, stock_actual NUMERIC(10, 2) NOT NULL, stock_minimo NUMERIC(10, 2) NOT NULL, activo TINYINT(1) NOT NULL, visible_presupuesto TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE catalogo_producto_configuracion (id INT AUTO_INCREMENT NOT NULL, producto_id INT NOT NULL, configurador_codigo VARCHAR(50) NOT NULL, uso VARCHAR(80) NOT NULL, tipo VARCHAR(100) DEFAULT NULL, ancho_min NUMERIC(8, 2) DEFAULT NULL, ancho_max NUMERIC(8, 2) DEFAULT NULL, largo_min NUMERIC(8, 2) DEFAULT NULL, largo_max NUMERIC(8, 2) DEFAULT NULL, alto_min NUMERIC(8, 2) DEFAULT NULL, alto_max NUMERIC(8, 2) DEFAULT NULL, prioridad INT NOT NULL, recomendado TINYINT(1) NOT NULL, activo TINYINT(1) NOT NULL, condiciones JSON DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_9B4D4F557645698E (producto_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE catalogo_producto_configuracion ADD CONSTRAINT FK_9B4D4F557645698E FOREIGN KEY (producto_id) REFERENCES catalogo_producto (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE catalogo_producto_configuracion DROP FOREIGN KEY FK_9B4D4F557645698E');
        $this->addSql('DROP TABLE catalogo_producto');
        $this->addSql('DROP TABLE catalogo_producto_configuracion');
    }
}
