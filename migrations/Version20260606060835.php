<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260606060835 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE stock_reserva (id INT AUTO_INCREMENT NOT NULL, producto_id INT DEFAULT NULL, stock_movimiento_entrada_id INT DEFAULT NULL, documento_id INT DEFAULT NULL, documento_linea_id INT DEFAULT NULL, proyecto_id INT DEFAULT NULL, descripcion_producto LONGTEXT NOT NULL, referencia_proveedor VARCHAR(100) DEFAULT NULL, cantidad NUMERIC(10, 3) NOT NULL, coste_unitario NUMERIC(10, 2) DEFAULT NULL, estado VARCHAR(30) NOT NULL, fecha_reserva DATETIME NOT NULL, fecha_caducidad DATETIME DEFAULT NULL, fecha_resolucion DATETIME DEFAULT NULL, observaciones LONGTEXT DEFAULT NULL, INDEX IDX_12B3E987645698E (producto_id), INDEX IDX_12B3E98DAEDF689 (stock_movimiento_entrada_id), INDEX IDX_12B3E9845C0CF75 (documento_id), UNIQUE INDEX UNIQ_12B3E98D1F59601 (documento_linea_id), INDEX IDX_12B3E98F625D1BA (proyecto_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE stock_reserva ADD CONSTRAINT FK_12B3E987645698E FOREIGN KEY (producto_id) REFERENCES productos (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE stock_reserva ADD CONSTRAINT FK_12B3E98DAEDF689 FOREIGN KEY (stock_movimiento_entrada_id) REFERENCES stock_movimiento (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE stock_reserva ADD CONSTRAINT FK_12B3E9845C0CF75 FOREIGN KEY (documento_id) REFERENCES documento (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE stock_reserva ADD CONSTRAINT FK_12B3E98D1F59601 FOREIGN KEY (documento_linea_id) REFERENCES documento_linea (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE stock_reserva ADD CONSTRAINT FK_12B3E98F625D1BA FOREIGN KEY (proyecto_id) REFERENCES proyecto (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE documento_linea ADD stock_reserva_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE documento_linea ADD CONSTRAINT FK_208105767838CE44 FOREIGN KEY (stock_reserva_id) REFERENCES stock_reserva (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_208105767838CE44 ON documento_linea (stock_reserva_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE documento_linea DROP FOREIGN KEY FK_208105767838CE44');
        $this->addSql('ALTER TABLE stock_reserva DROP FOREIGN KEY FK_12B3E987645698E');
        $this->addSql('ALTER TABLE stock_reserva DROP FOREIGN KEY FK_12B3E98DAEDF689');
        $this->addSql('ALTER TABLE stock_reserva DROP FOREIGN KEY FK_12B3E9845C0CF75');
        $this->addSql('ALTER TABLE stock_reserva DROP FOREIGN KEY FK_12B3E98D1F59601');
        $this->addSql('ALTER TABLE stock_reserva DROP FOREIGN KEY FK_12B3E98F625D1BA');
        $this->addSql('DROP TABLE stock_reserva');
        $this->addSql('DROP INDEX UNIQ_208105767838CE44 ON documento_linea');
        $this->addSql('ALTER TABLE documento_linea DROP stock_reserva_id');
    }
}
