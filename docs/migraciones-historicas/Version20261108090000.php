<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261108090000 extends AbstractMigration {
    public function getDescription(): string { return 'Relacion unica entre cobro de proyecto y factura de anticipo'; }
    public function up(Schema $schema): void { $this->addSql('ALTER TABLE proyecto_cobro ADD factura_anticipo_id INT DEFAULT NULL'); $this->addSql('CREATE UNIQUE INDEX UNIQ_PROYECTO_COBRO_FACTURA_ANTICIPO ON proyecto_cobro (factura_anticipo_id)'); $this->addSql('ALTER TABLE proyecto_cobro ADD CONSTRAINT FK_PROYECTO_COBRO_FACTURA_ANTICIPO FOREIGN KEY (factura_anticipo_id) REFERENCES documento (id) ON DELETE RESTRICT'); }
    public function down(Schema $schema): void { $this->addSql('ALTER TABLE proyecto_cobro DROP FOREIGN KEY FK_PROYECTO_COBRO_FACTURA_ANTICIPO'); $this->addSql('DROP INDEX UNIQ_PROYECTO_COBRO_FACTURA_ANTICIPO ON proyecto_cobro'); $this->addSql('ALTER TABLE proyecto_cobro DROP factura_anticipo_id'); }
}
