<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260501150514 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE documento_configuracion (id INT AUTO_INCREMENT NOT NULL, documento_id INT NOT NULL, configurador_id INT NOT NULL, datos JSON NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_7196DE0245C0CF75 (documento_id), INDEX IDX_7196DE02DCB031D3 (configurador_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE presupuesto_configurador (id INT AUTO_INCREMENT NOT NULL, codigo VARCHAR(50) NOT NULL, nombre VARCHAR(150) NOT NULL, activo TINYINT(1) NOT NULL, orden INT NOT NULL, UNIQUE INDEX UNIQ_B857289120332D99 (codigo), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE presupuesto_configurador_campo (id INT AUTO_INCREMENT NOT NULL, configurador_id INT NOT NULL, codigo VARCHAR(80) NOT NULL, etiqueta VARCHAR(150) NOT NULL, tipo_campo VARCHAR(50) NOT NULL, opciones JSON DEFAULT NULL, obligatorio TINYINT(1) NOT NULL, orden INT NOT NULL, activo TINYINT(1) NOT NULL, INDEX IDX_1933E2A6DCB031D3 (configurador_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE documento_configuracion ADD CONSTRAINT FK_7196DE0245C0CF75 FOREIGN KEY (documento_id) REFERENCES documento (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE documento_configuracion ADD CONSTRAINT FK_7196DE02DCB031D3 FOREIGN KEY (configurador_id) REFERENCES presupuesto_configurador (id)');
        $this->addSql('ALTER TABLE presupuesto_configurador_campo ADD CONSTRAINT FK_1933E2A6DCB031D3 FOREIGN KEY (configurador_id) REFERENCES presupuesto_configurador (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE documento_configuracion DROP FOREIGN KEY FK_7196DE0245C0CF75');
        $this->addSql('ALTER TABLE documento_configuracion DROP FOREIGN KEY FK_7196DE02DCB031D3');
        $this->addSql('ALTER TABLE presupuesto_configurador_campo DROP FOREIGN KEY FK_1933E2A6DCB031D3');
        $this->addSql('DROP TABLE documento_configuracion');
        $this->addSql('DROP TABLE presupuesto_configurador');
        $this->addSql('DROP TABLE presupuesto_configurador_campo');
    }
}
