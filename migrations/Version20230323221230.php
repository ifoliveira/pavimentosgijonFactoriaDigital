<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230323221230 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE economicpresu (id INT AUTO_INCREMENT NOT NULL, idpresu_eco_id INT NOT NULL, concepto_eco VARCHAR(100) DEFAULT NULL, importe_eco DOUBLE PRECISION DEFAULT NULL, debehaber_eco VARCHAR(1) DEFAULT NULL, aplica_eco VARCHAR(1) DEFAULT NULL, estado_eco VARCHAR(1) DEFAULT NULL, INDEX IDX_45996642C5D8D2D (idpresu_eco_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE economicpresu ADD CONSTRAINT FK_45996642C5D8D2D FOREIGN KEY (idpresu_eco_id) REFERENCES presupuestos (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE economicpresu DROP FOREIGN KEY FK_45996642C5D8D2D');
        $this->addSql('DROP TABLE economicpresu');

    }
}
