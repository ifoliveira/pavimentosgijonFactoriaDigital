<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260417221533 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE proyecto_gasto (id INT AUTO_INCREMENT NOT NULL, proyecto_id INT NOT NULL, documento_id INT DEFAULT NULL, forecast_id INT DEFAULT NULL, categoria VARCHAR(50) NOT NULL, concepto VARCHAR(255) NOT NULL, proveedor VARCHAR(150) DEFAULT NULL, fecha_prevista DATE NOT NULL, fecha_real DATE DEFAULT NULL, importe_previsto NUMERIC(10, 2) NOT NULL, importe_real NUMERIC(10, 2) DEFAULT NULL, estado VARCHAR(20) NOT NULL, genera_forecast TINYINT(1) NOT NULL, notas LONGTEXT DEFAULT NULL, creado_en DATETIME NOT NULL, actualizado_en DATETIME DEFAULT NULL, INDEX IDX_ACA8DA5AF625D1BA (proyecto_id), INDEX IDX_ACA8DA5A45C0CF75 (documento_id), INDEX IDX_ACA8DA5AF8DCC97 (forecast_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE proyecto_gasto ADD CONSTRAINT FK_ACA8DA5AF625D1BA FOREIGN KEY (proyecto_id) REFERENCES proyecto (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE proyecto_gasto ADD CONSTRAINT FK_ACA8DA5A45C0CF75 FOREIGN KEY (documento_id) REFERENCES documento (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE proyecto_gasto ADD CONSTRAINT FK_ACA8DA5AF8DCC97 FOREIGN KEY (forecast_id) REFERENCES forecast (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE proyecto_gasto DROP FOREIGN KEY FK_ACA8DA5AF625D1BA');
        $this->addSql('ALTER TABLE proyecto_gasto DROP FOREIGN KEY FK_ACA8DA5A45C0CF75');
        $this->addSql('ALTER TABLE proyecto_gasto DROP FOREIGN KEY FK_ACA8DA5AF8DCC97');
        $this->addSql('DROP TABLE proyecto_gasto');
    }
}
