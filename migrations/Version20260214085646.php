<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260214085646 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE evento (id INT AUTO_INCREMENT NOT NULL, visitante_id INT NOT NULL, tipo VARCHAR(50) DEFAULT NULL, datos JSON DEFAULT NULL, fecha_creacion DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_47860B05D80AA8AF (visitante_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE visitante (id INT AUTO_INCREMENT NOT NULL, fecha_primera_visita DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', fecha_ultima_visita DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', origen_normalizado VARCHAR(255) DEFAULT NULL, utm_origen VARCHAR(255) DEFAULT NULL, utm_medio VARCHAR(255) DEFAULT NULL, utm_campaña VARCHAR(255) DEFAULT NULL, gclid VARCHAR(255) DEFAULT NULL, referente LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE evento ADD CONSTRAINT FK_47860B05D80AA8AF FOREIGN KEY (visitante_id) REFERENCES visitante (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE evento DROP FOREIGN KEY FK_47860B05D80AA8AF');
        $this->addSql('DROP TABLE evento');
        $this->addSql('DROP TABLE visitante');
    }
}
