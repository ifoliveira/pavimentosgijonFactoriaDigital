<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210312234401 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE banco CHANGE categoria_bn categoria_bn INT DEFAULT NULL');
        $this->addSql('ALTER TABLE banco RENAME INDEX banco_fk TO IDX_77DEE1D130B640AF');
        $this->addSql('DROP INDEX IDX_F1E891F4F2C16F22 ON cestas');
        $this->addSql('ALTER TABLE cestas CHANGE numticket_cs numticket_cs VARCHAR(55) NOT NULL, CHANGE timestamp_cs timestamp_cs DATETIME NOT NULL, CHANGE estado_cs estado_cs_id INT NOT NULL');
        $this->addSql('ALTER TABLE cestas ADD CONSTRAINT FK_F1E891F4F2C16F22 FOREIGN KEY (estado_cs_id) REFERENCES estadocestas (id)');
        $this->addSql('CREATE INDEX IDX_F1E891F4F2C16F22 ON cestas (estado_cs_id)');
        $this->addSql('ALTER TABLE detallecesta ADD CONSTRAINT FK_A3E82772787386AA FOREIGN KEY (cesta_dc_id) REFERENCES cestas (id)');
        $this->addSql('ALTER TABLE detallecesta ADD CONSTRAINT FK_A3E827725A246A35 FOREIGN KEY (producto_dc_id) REFERENCES productos (id)');
        $this->addSql('ALTER TABLE efectivo CHANGE timestamp_ef timestamp_ef DATETIME NOT NULL');
        $this->addSql('ALTER TABLE forecast DROP timestamp_fr');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE banco CHANGE categoria_bn categoria_bn INT NOT NULL');
        $this->addSql('ALTER TABLE banco RENAME INDEX idx_77dee1d130b640af TO banco_FK');
        $this->addSql('ALTER TABLE cestas DROP FOREIGN KEY FK_F1E891F4F2C16F22');
        $this->addSql('DROP INDEX IDX_F1E891F4F2C16F22 ON cestas');
        $this->addSql('ALTER TABLE cestas CHANGE numticket_cs numticket_cs TEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, CHANGE timestamp_cs timestamp_cs DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE estado_cs_id estado_cs INT NOT NULL');
        $this->addSql('CREATE INDEX IDX_F1E891F4F2C16F22 ON cestas (estado_cs)');
        $this->addSql('ALTER TABLE detallecesta DROP FOREIGN KEY FK_A3E82772787386AA');
        $this->addSql('ALTER TABLE detallecesta DROP FOREIGN KEY FK_A3E827725A246A35');
        $this->addSql('ALTER TABLE efectivo CHANGE timestamp_ef timestamp_ef DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE forecast ADD timestamp_fr DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
    }
}
