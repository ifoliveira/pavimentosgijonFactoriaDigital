<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260919140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create web budget interaction table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE presupuesto_web_interaccion (id INT AUTO_INCREMENT NOT NULL, presupuesto_web_id INT NOT NULL, comunicacion_origen_id INT DEFAULT NULL, tipo VARCHAR(32) NOT NULL, datos JSON DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_E0421CE36CF9DD50 (presupuesto_web_id), INDEX IDX_E0421CE3366A2741 (comunicacion_origen_id), INDEX IDX_PRESUPUESTO_WEB_INTERACCION_TIPO (tipo), INDEX IDX_PRESUPUESTO_WEB_INTERACCION_CREATED_AT (created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE presupuesto_web_interaccion ADD CONSTRAINT FK_E0421CE36CF9DD50 FOREIGN KEY (presupuesto_web_id) REFERENCES presupuesto_web (id)');
        $this->addSql('ALTER TABLE presupuesto_web_interaccion ADD CONSTRAINT FK_E0421CE3366A2741 FOREIGN KEY (comunicacion_origen_id) REFERENCES presupuesto_web_comunicacion (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE presupuesto_web_interaccion DROP FOREIGN KEY FK_E0421CE36CF9DD50');
        $this->addSql('ALTER TABLE presupuesto_web_interaccion DROP FOREIGN KEY FK_E0421CE3366A2741');
        $this->addSql('DROP TABLE presupuesto_web_interaccion');
    }
}
