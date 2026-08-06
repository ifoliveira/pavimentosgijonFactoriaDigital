<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260802084350 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE proyecto_gasto DROP FOREIGN KEY FK_ACA8DA5AF8DCC97');
        $this->addSql('ALTER TABLE proyecto_gasto ADD CONSTRAINT FK_ACA8DA5AF8DCC97 FOREIGN KEY (forecast_id) REFERENCES forecast (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE proyecto_gasto DROP FOREIGN KEY FK_ACA8DA5AF8DCC97');
        $this->addSql('ALTER TABLE proyecto_gasto ADD CONSTRAINT FK_ACA8DA5AF8DCC97 FOREIGN KEY (forecast_id) REFERENCES forecast (id) ON UPDATE NO ACTION ON DELETE SET NULL');
    }
}
