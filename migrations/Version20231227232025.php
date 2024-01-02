<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231227232025 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE banco ADD conciliado TINYINT(1) DEFAULT NULL, CHANGE concepto_bn concepto_bn LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE cestas DROP FOREIGN KEY FK_F1E891F4F2C16F22');
        $this->addSql('ALTER TABLE cestas DROP FOREIGN KEY FK_F1E891F42D65116B');
        $this->addSql('ALTER TABLE cestas DROP FOREIGN KEY cestasII_FK');
        $this->addSql('ALTER TABLE cestas DROP FOREIGN KEY FK_F1E891F451BC2A97');
        $this->addSql('DROP INDEX UNIQ_F1E891F42D65116B ON cestas');
        $this->addSql('DROP INDEX IDX_F1E891F4F2C16F22 ON cestas');
        $this->addSql('DROP INDEX cestasII_FK ON cestas');
        $this->addSql('ALTER TABLE cestas DROP banco_cs_id_id, DROP importe_pago_cs');
        $this->addSql('ALTER TABLE cestas ADD CONSTRAINT FK_F1E891F451BC2A97 FOREIGN KEY (prespuesto_cs_id) REFERENCES presupuestos (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE cestas RENAME INDEX fk_f1e891f451bc2a97 TO IDX_F1E891F451BC2A97');
        $this->addSql('ALTER TABLE clientes ADD dni VARCHAR(9) DEFAULT NULL');
        $this->addSql('ALTER TABLE economicpresu ADD timestamp DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE forecast ADD banco_id INT DEFAULT NULL, ADD timestamp DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE forecast ADD CONSTRAINT FK_2A9C7844CC04A73E FOREIGN KEY (banco_id) REFERENCES banco (id)');
        $this->addSql('CREATE INDEX IDX_2A9C7844CC04A73E ON forecast (banco_id)');
        $this->addSql('ALTER TABLE mano_obra ADD coste DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE banco DROP conciliado, CHANGE concepto_bn concepto_bn LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE cestas DROP FOREIGN KEY FK_F1E891F451BC2A97');
        $this->addSql('ALTER TABLE cestas ADD banco_cs_id_id INT DEFAULT NULL, ADD importe_pago_cs DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE cestas ADD CONSTRAINT FK_F1E891F4F2C16F22 FOREIGN KEY (estado_cs) REFERENCES estadocestas (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cestas ADD CONSTRAINT FK_F1E891F42D65116B FOREIGN KEY (banco_cs_id_id) REFERENCES banco (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE cestas ADD CONSTRAINT cestasII_FK FOREIGN KEY (user_cs) REFERENCES `admin` (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE cestas ADD CONSTRAINT FK_F1E891F451BC2A97 FOREIGN KEY (prespuesto_cs_id) REFERENCES presupuestos (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F1E891F42D65116B ON cestas (banco_cs_id_id)');
        $this->addSql('CREATE INDEX IDX_F1E891F4F2C16F22 ON cestas (estado_cs)');
        $this->addSql('CREATE INDEX cestasII_FK ON cestas (user_cs)');
        $this->addSql('ALTER TABLE cestas RENAME INDEX idx_f1e891f451bc2a97 TO FK_F1E891F451BC2A97');
        $this->addSql('ALTER TABLE clientes DROP dni');
        $this->addSql('ALTER TABLE economicpresu DROP timestamp');
        $this->addSql('ALTER TABLE forecast DROP FOREIGN KEY FK_2A9C7844CC04A73E');
        $this->addSql('DROP INDEX IDX_2A9C7844CC04A73E ON forecast');
        $this->addSql('ALTER TABLE forecast DROP banco_id, DROP timestamp');
        $this->addSql('ALTER TABLE mano_obra DROP coste');
    }
}
