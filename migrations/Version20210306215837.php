<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210306215837 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cestas (id INT AUTO_INCREMENT NOT NULL, estado_cs_id INT NOT NULL, user_cs INT NOT NULL, fecha_cs DATE NOT NULL, importe_tot_cs DOUBLE PRECISION NOT NULL, descueto_cs DOUBLE PRECISION NOT NULL, tipopago_cs VARCHAR(15) NOT NULL, numticket_cs INT NOT NULL, timestamp_cs DATETIME NOT NULL, INDEX IDX_F1E891F4F2C16F22 (estado_cs_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE detallecesta (id INT AUTO_INCREMENT NOT NULL, cesta_dc_id INT NOT NULL, producto_dc_id INT NOT NULL, cantidad_dc INT NOT NULL, pvp_dc DOUBLE PRECISION NOT NULL, descuento_dc DOUBLE PRECISION NOT NULL, timestamp_dc DATETIME NOT NULL, INDEX IDX_A3E82772787386AA (cesta_dc_id), INDEX IDX_A3E827725A246A35 (producto_dc_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE estadocestas (id INT AUTO_INCREMENT NOT NULL, descripcion_ec VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE forecast (id INT AUTO_INCREMENT NOT NULL, tipo_fr_id INT NOT NULL, concepto_fr VARCHAR(255) NOT NULL, fecha_fr DATE NOT NULL, importe_fr DOUBLE PRECISION NOT NULL, origen_fr VARCHAR(50) NOT NULL, fijovar_fr VARCHAR(1) NOT NULL, INDEX IDX_2A9C78448EDA9B0D (tipo_fr_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE cestas');
        $this->addSql('DROP TABLE detallecesta');
        $this->addSql('DROP TABLE estadocestas');
        $this->addSql('DROP TABLE forecast');
    }
}
