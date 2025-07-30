<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250729193837 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE stock_item (id INT AUTO_INCREMENT NOT NULL, producto_id INT NOT NULL, cantidad INT NOT NULL, precio_unitario DOUBLE PRECISION NOT NULL, fecha_entrada DATETIME NOT NULL, proveedor VARCHAR(255) DEFAULT NULL, factura_id VARCHAR(255) DEFAULT NULL, INDEX IDX_6017DDA7645698E (producto_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE uso_de_stock (id INT AUTO_INCREMENT NOT NULL, producto_id INT NOT NULL, stock_item_id INT NOT NULL, presupuesto_id INT DEFAULT NULL, cantidad INT NOT NULL, fecha DATETIME NOT NULL, comentario VARCHAR(255) DEFAULT NULL, INDEX IDX_D018037F7645698E (producto_id), INDEX IDX_D018037FBC942FD (stock_item_id), INDEX IDX_D018037F90119F0F (presupuesto_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE stock_item ADD CONSTRAINT FK_6017DDA7645698E FOREIGN KEY (producto_id) REFERENCES productos (id)');
        $this->addSql('ALTER TABLE uso_de_stock ADD CONSTRAINT FK_D018037F7645698E FOREIGN KEY (producto_id) REFERENCES productos (id)');
        $this->addSql('ALTER TABLE uso_de_stock ADD CONSTRAINT FK_D018037FBC942FD FOREIGN KEY (stock_item_id) REFERENCES stock_item (id)');
        $this->addSql('ALTER TABLE uso_de_stock ADD CONSTRAINT FK_D018037F90119F0F FOREIGN KEY (presupuesto_id) REFERENCES presupuestos (id)');
        $this->addSql('ALTER TABLE consultas ADD presupuesto_ai JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_item DROP FOREIGN KEY FK_6017DDA7645698E');
        $this->addSql('ALTER TABLE uso_de_stock DROP FOREIGN KEY FK_D018037F7645698E');
        $this->addSql('ALTER TABLE uso_de_stock DROP FOREIGN KEY FK_D018037FBC942FD');
        $this->addSql('ALTER TABLE uso_de_stock DROP FOREIGN KEY FK_D018037F90119F0F');
        $this->addSql('DROP TABLE stock_item');
        $this->addSql('DROP TABLE uso_de_stock');
        $this->addSql('ALTER TABLE consultas DROP presupuesto_ai');
    }
}
