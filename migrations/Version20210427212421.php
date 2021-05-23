<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210427212421 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE presupuestos ADD ticketsnal_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE presupuestos ADD CONSTRAINT FK_4CF2F0DBDC1FCCE FOREIGN KEY (ticketsnal_id) REFERENCES cestas (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4CF2F0DBDC1FCCE ON presupuestos (ticketsnal_id)');
           }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE admin MODIFY id INT NOT NULL');
        $this->addSql('DROP INDEX UNIQ_880E0D76F85E0677 ON admin');
        $this->addSql('ALTER TABLE admin DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE admin CHANGE id id INT NOT NULL, CHANGE username username VARCHAR(180) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, CHANGE password password VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('ALTER TABLE banco MODIFY id INT NOT NULL');
        $this->addSql('ALTER TABLE banco DROP FOREIGN KEY FK_77DEE1D130B640AF');
        $this->addSql('DROP INDEX IDX_77DEE1D130B640AF ON banco');
        $this->addSql('ALTER TABLE banco DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE banco CHANGE id id INT NOT NULL');
        $this->addSql('ALTER TABLE cestas CHANGE tipopago_cs tipopago_cs VARCHAR(15) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, CHANGE numticket_cs numticket_cs VARCHAR(55) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('ALTER TABLE clientes CHANGE nombre_cl nombre_cl VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, CHANGE apellidos_cl apellidos_cl VARCHAR(150) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, CHANGE ciudad_cl ciudad_cl VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, CHANGE direccion_cl direccion_cl VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, CHANGE telefono1_cl telefono1_cl VARCHAR(9) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('ALTER TABLE detallecesta DROP FOREIGN KEY FK_A3E82772787386AA');
        $this->addSql('ALTER TABLE detallecesta DROP FOREIGN KEY FK_A3E827725A246A35');
        $this->addSql('DROP INDEX IDX_A3E82772787386AA ON detallecesta');
        $this->addSql('DROP INDEX IDX_A3E827725A246A35 ON detallecesta');
        $this->addSql('ALTER TABLE detallecesta CHANGE cesta_dc_id cesta_dc_id INT NOT NULL');
        $this->addSql('ALTER TABLE efectivo MODIFY id INT NOT NULL');
        $this->addSql('ALTER TABLE efectivo DROP FOREIGN KEY FK_94D7FEC0B438A8E4');
        $this->addSql('DROP INDEX IDX_94D7FEC0B438A8E4 ON efectivo');
        $this->addSql('ALTER TABLE efectivo DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE efectivo CHANGE id id INT NOT NULL, CHANGE concepto_ef concepto_ef VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('ALTER TABLE estadocestas MODIFY id INT NOT NULL');
        $this->addSql('ALTER TABLE estadocestas DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE estadocestas CHANGE id id INT NOT NULL, CHANGE descripcion_ec descripcion_ec VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('ALTER TABLE forecast MODIFY id INT NOT NULL');
        $this->addSql('ALTER TABLE forecast DROP FOREIGN KEY FK_2A9C78448EDA9B0D');
        $this->addSql('DROP INDEX IDX_2A9C78448EDA9B0D ON forecast');
        $this->addSql('ALTER TABLE forecast DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE forecast CHANGE id id INT NOT NULL, CHANGE concepto_fr concepto_fr VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, CHANGE origen_fr origen_fr VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, CHANGE fijovar_fr fijovar_fr VARCHAR(1) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, CHANGE estado_fr estado_fr VARCHAR(10) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('ALTER TABLE presupuestos DROP FOREIGN KEY FK_4CF2F0DA0F38A33');
        $this->addSql('ALTER TABLE presupuestos DROP FOREIGN KEY FK_4CF2F0D48CAF799');
        $this->addSql('ALTER TABLE presupuestos DROP FOREIGN KEY FK_4CF2F0D700047D2');
        $this->addSql('ALTER TABLE presupuestos DROP FOREIGN KEY FK_4CF2F0D798551F2');
        $this->addSql('ALTER TABLE presupuestos DROP FOREIGN KEY FK_4CF2F0DBDC1FCCE');
        $this->addSql('DROP INDEX IDX_4CF2F0DA0F38A33 ON presupuestos');
        $this->addSql('DROP INDEX IDX_4CF2F0D48CAF799 ON presupuestos');
        $this->addSql('DROP INDEX UNIQ_4CF2F0D700047D2 ON presupuestos');
        $this->addSql('DROP INDEX IDX_4CF2F0D798551F2 ON presupuestos');
        $this->addSql('DROP INDEX UNIQ_4CF2F0DBDC1FCCE ON presupuestos');
        $this->addSql('ALTER TABLE presupuestos DROP ticketsnal_id, CHANGE ticket_id ticket_id INT DEFAULT NULL, CHANGE fechaini_pe fechaini_pe DATE DEFAULT NULL, CHANGE costetot_pe costetot_pe DOUBLE PRECISION DEFAULT NULL, CHANGE tipopagotot_pe tipopagotot_pe VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, CHANGE importesnal_pe importesnal_pe DOUBLE PRECISION DEFAULT NULL, CHANGE tipopagosnal_pe tipopagosnal_pe VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, CHANGE timestamp_mod_pe timestamp_mod_pe DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE productos DROP FOREIGN KEY FK_767490E61408F16C');
        $this->addSql('DROP INDEX IDX_767490E61408F16C ON productos');
        $this->addSql('ALTER TABLE productos CHANGE descripcion_pd descripcion_pd VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('ALTER TABLE tipoproducto MODIFY id INT NOT NULL');
        $this->addSql('ALTER TABLE tipoproducto DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE tipoproducto CHANGE id id INT NOT NULL, CHANGE decripcion_tp decripcion_tp VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('ALTER TABLE tiposmovimiento MODIFY id INT NOT NULL');
        $this->addSql('ALTER TABLE tiposmovimiento DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE tiposmovimiento CHANGE id id INT NOT NULL, CHANGE descripcion_tm descripcion_tm VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`');
    }
}
