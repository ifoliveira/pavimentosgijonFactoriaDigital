<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260509072514 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE proyecto_cobro (id INT AUTO_INCREMENT NOT NULL, proyecto_id INT NOT NULL, banco_id INT DEFAULT NULL, fecha DATE NOT NULL, metodo VARCHAR(20) NOT NULL, importe_bruto NUMERIC(10, 2) NOT NULL, porcentaje_recargo NUMERIC(5, 2) NOT NULL, importe_recargo NUMERIC(10, 2) NOT NULL, importe_neto NUMERIC(10, 2) NOT NULL, referencia VARCHAR(100) DEFAULT NULL, notas LONGTEXT DEFAULT NULL, creado_en DATETIME NOT NULL, INDEX IDX_F2496568F625D1BA (proyecto_id), INDEX IDX_F2496568CC04A73E (banco_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE proyecto_cobro ADD CONSTRAINT FK_F2496568F625D1BA FOREIGN KEY (proyecto_id) REFERENCES proyecto (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE proyecto_cobro ADD CONSTRAINT FK_F2496568CC04A73E FOREIGN KEY (banco_id) REFERENCES banco (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE proyecto_cobro DROP FOREIGN KEY FK_F2496568F625D1BA');
        $this->addSql('ALTER TABLE proyecto_cobro DROP FOREIGN KEY FK_F2496568CC04A73E');
        $this->addSql('DROP TABLE proyecto_cobro');
    }
}
