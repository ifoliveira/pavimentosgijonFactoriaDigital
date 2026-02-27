<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260222231258 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE sesion (id VARCHAR(36) NOT NULL, visitante_id VARCHAR(36) NOT NULL, fecha_inicio DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', fecha_fin DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ruta_entrada VARCHAR(255) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, dispositivo VARCHAR(100) DEFAULT NULL, numero_eventos INT NOT NULL, INDEX IDX_1B45E21BD80AA8AF (visitante_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE sesion ADD CONSTRAINT FK_1B45E21BD80AA8AF FOREIGN KEY (visitante_id) REFERENCES visitante (id)');
        $this->addSql('ALTER TABLE encuesta CHANGE p6 p6 LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:array)\'');
        $this->addSql('ALTER TABLE evento ADD sesion_id VARCHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE evento ADD CONSTRAINT FK_47860B051CCCADCB FOREIGN KEY (sesion_id) REFERENCES sesion (id)');
        $this->addSql('CREATE INDEX IDX_47860B051CCCADCB ON evento (sesion_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE evento DROP FOREIGN KEY FK_47860B051CCCADCB');
        $this->addSql('ALTER TABLE sesion DROP FOREIGN KEY FK_1B45E21BD80AA8AF');
        $this->addSql('DROP TABLE sesion');
        $this->addSql('ALTER TABLE encuesta CHANGE p6 p6 TINYTEXT DEFAULT NULL COMMENT \'(DC2Type:array)\'');
        $this->addSql('DROP INDEX IDX_47860B051CCCADCB ON evento');
        $this->addSql('ALTER TABLE evento DROP sesion_id');
    }
}
