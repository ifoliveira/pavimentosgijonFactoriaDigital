<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260802222930 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE forecast ADD factura_proveedor_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE forecast ADD CONSTRAINT FK_2A9C78448645029A FOREIGN KEY (factura_proveedor_id) REFERENCES factura_proveedor (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_2A9C78448645029A ON forecast (factura_proveedor_id)');
        $this->addSql('ALTER TABLE proyecto_gasto DROP FOREIGN KEY FK_ACA8DA5AF8DCC97');
        $this->addSql('ALTER TABLE proyecto_gasto ADD CONSTRAINT FK_ACA8DA5AF8DCC97 FOREIGN KEY (forecast_id) REFERENCES forecast (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE forecast DROP FOREIGN KEY FK_2A9C78448645029A');
        $this->addSql('DROP INDEX IDX_2A9C78448645029A ON forecast');
        $this->addSql('ALTER TABLE forecast DROP factura_proveedor_id');
        $this->addSql('ALTER TABLE proyecto_gasto DROP FOREIGN KEY FK_ACA8DA5AF8DCC97');
        $this->addSql('ALTER TABLE proyecto_gasto ADD CONSTRAINT FK_ACA8DA5AF8DCC97 FOREIGN KEY (forecast_id) REFERENCES forecast (id) ON UPDATE NO ACTION ON DELETE CASCADE');
    }
}
