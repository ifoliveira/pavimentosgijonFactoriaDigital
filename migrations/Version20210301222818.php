<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210301222818 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE efectivo (id INT AUTO_INCREMENT NOT NULL, concepto_ef VARCHAR(255) NOT NULL, fecha_ef DATE NOT NULL, importe_ef DOUBLE PRECISION NOT NULL, timestamp_ef DATETIME NOT NULL, tipoEf INT DEFAULT NULL, INDEX IDX_94D7FEC0B438A8E4 (tipoEf), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tiposmovimiento (id INT AUTO_INCREMENT NOT NULL, descripcion_tm VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE efectivo ADD CONSTRAINT FK_94D7FEC0B438A8E4 FOREIGN KEY (tipoEf) REFERENCES tiposmovimiento (id)');
        $this->addSql('ALTER TABLE banco CHANGE timestamp_bn timestamp_bn DATETIME NOT NULL');
        $this->addSql('ALTER TABLE productos CHANGE tipo_pd_id tipo_pd_id INT DEFAULT NULL, CHANGE fec_alta_pd fec_alta_pd DATETIME NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE efectivo DROP FOREIGN KEY FK_94D7FEC0B438A8E4');
        $this->addSql('DROP TABLE efectivo');
        $this->addSql('DROP TABLE tiposmovimiento');
        $this->addSql('ALTER TABLE banco CHANGE timestamp_bn timestamp_bn DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE productos CHANGE tipo_pd_id tipo_pd_id INT NOT NULL, CHANGE fec_alta_pd fec_alta_pd DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
    }
}
