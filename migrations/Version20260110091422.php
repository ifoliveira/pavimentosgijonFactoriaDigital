<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260110091422 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE presupuestos_lead (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) DEFAULT NULL, nombre VARCHAR(255) DEFAULT NULL, tipo_reforma VARCHAR(50) DEFAULT NULL, fecha_pdf DATETIME NOT NULL, email1_enviado TINYINT(1) DEFAULT NULL, email2_enviado TINYINT(1) DEFAULT NULL, seguimiento_activo TINYINT(1) DEFAULT NULL, pdf_descargas INT DEFAULT NULL, ultimo_evento DATETIME DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE presupuestos_lead');
    }
}
