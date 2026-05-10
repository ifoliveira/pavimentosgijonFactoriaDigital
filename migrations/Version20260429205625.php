<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260429205625 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE factura_proveedor_linea_asignacion (id INT AUTO_INCREMENT NOT NULL, linea_id INT NOT NULL, proyecto_id INT DEFAULT NULL, proyecto_gasto_id INT DEFAULT NULL, cantidad DOUBLE PRECISION NOT NULL, importe NUMERIC(10, 2) NOT NULL, tipo_destino VARCHAR(50) NOT NULL, estado VARCHAR(50) NOT NULL, INDEX IDX_FD1115D985FE79F8 (linea_id), INDEX IDX_FD1115D9F625D1BA (proyecto_id), INDEX IDX_FD1115D9A4B068B5 (proyecto_gasto_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE factura_proveedor_linea_asignacion ADD CONSTRAINT FK_FD1115D985FE79F8 FOREIGN KEY (linea_id) REFERENCES factura_proveedor_linea (id)');
        $this->addSql('ALTER TABLE factura_proveedor_linea_asignacion ADD CONSTRAINT FK_FD1115D9F625D1BA FOREIGN KEY (proyecto_id) REFERENCES proyecto (id)');
        $this->addSql('ALTER TABLE factura_proveedor_linea_asignacion ADD CONSTRAINT FK_FD1115D9A4B068B5 FOREIGN KEY (proyecto_gasto_id) REFERENCES proyecto_gasto (id)');
        $this->addSql('ALTER TABLE factura_proveedor_linea DROP FOREIGN KEY FK_61B94C84A4B068B5');
        $this->addSql('ALTER TABLE factura_proveedor_linea DROP FOREIGN KEY FK_61B94C84F625D1BA');
        $this->addSql('DROP INDEX IDX_61B94C84A4B068B5 ON factura_proveedor_linea');
        $this->addSql('DROP INDEX IDX_61B94C84F625D1BA ON factura_proveedor_linea');
        $this->addSql('ALTER TABLE factura_proveedor_linea DROP proyecto_id, DROP proyecto_gasto_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE factura_proveedor_linea_asignacion DROP FOREIGN KEY FK_FD1115D985FE79F8');
        $this->addSql('ALTER TABLE factura_proveedor_linea_asignacion DROP FOREIGN KEY FK_FD1115D9F625D1BA');
        $this->addSql('ALTER TABLE factura_proveedor_linea_asignacion DROP FOREIGN KEY FK_FD1115D9A4B068B5');
        $this->addSql('DROP TABLE factura_proveedor_linea_asignacion');
        $this->addSql('ALTER TABLE factura_proveedor_linea ADD proyecto_id INT DEFAULT NULL, ADD proyecto_gasto_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE factura_proveedor_linea ADD CONSTRAINT FK_61B94C84A4B068B5 FOREIGN KEY (proyecto_gasto_id) REFERENCES proyecto_gasto (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE factura_proveedor_linea ADD CONSTRAINT FK_61B94C84F625D1BA FOREIGN KEY (proyecto_id) REFERENCES proyecto (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_61B94C84A4B068B5 ON factura_proveedor_linea (proyecto_gasto_id)');
        $this->addSql('CREATE INDEX IDX_61B94C84F625D1BA ON factura_proveedor_linea (proyecto_id)');
    }
}
