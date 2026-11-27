<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260919120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create web budget lead, budget and communication tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE presupuesto_web_lead (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', privacidad_informada_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', version_privacidad VARCHAR(32) NOT NULL, seguimiento_activo TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE presupuesto_web (id INT AUTO_INCREMENT NOT NULL, lead_id INT NOT NULL, tipo_presupuesto VARCHAR(32) NOT NULL, token VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', total NUMERIC(10, 2) DEFAULT NULL, json_solicitud_budget_flow JSON NOT NULL, json_presupuesto JSON NOT NULL, numero_visualizaciones INT NOT NULL, primera_visualizacion_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ultima_visualizacion_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_PRESUPUESTO_WEB_TOKEN (token), INDEX IDX_8B65D0A855458D (lead_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE presupuesto_web_comunicacion (id INT AUTO_INCREMENT NOT NULL, presupuesto_web_id INT NOT NULL, tipo VARCHAR(32) NOT NULL, estado VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', fecha_programada DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', fecha_envio DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', asunto VARCHAR(255) DEFAULT NULL, error LONGTEXT DEFAULT NULL, metadata JSON DEFAULT NULL, INDEX IDX_A53C8D326CF9DD50 (presupuesto_web_id), INDEX IDX_PRESUPUESTO_WEB_COMUNICACION_ESTADO (estado), INDEX IDX_PRESUPUESTO_WEB_COMUNICACION_FECHA_PROGRAMADA (fecha_programada), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE presupuesto_web ADD CONSTRAINT FK_8B65D0A855458D FOREIGN KEY (lead_id) REFERENCES presupuesto_web_lead (id)');
        $this->addSql('ALTER TABLE presupuesto_web_comunicacion ADD CONSTRAINT FK_A53C8D326CF9DD50 FOREIGN KEY (presupuesto_web_id) REFERENCES presupuesto_web (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE presupuesto_web_comunicacion DROP FOREIGN KEY FK_A53C8D326CF9DD50');
        $this->addSql('ALTER TABLE presupuesto_web DROP FOREIGN KEY FK_8B65D0A855458D');
        $this->addSql('DROP TABLE presupuesto_web_comunicacion');
        $this->addSql('DROP TABLE presupuesto_web');
        $this->addSql('DROP TABLE presupuesto_web_lead');
    }
}
