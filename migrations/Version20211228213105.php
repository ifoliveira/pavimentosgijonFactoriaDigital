<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211228213105 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE texto_mano_obra (id INT AUTO_INCREMENT NOT NULL, tipo_xo_id INT NOT NULL, descripcion_xo LONGTEXT NOT NULL, resumen_xo VARCHAR(50) NOT NULL, INDEX IDX_39AA516FF3793DAC (tipo_xo_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tipo_mano_obra (id INT AUTO_INCREMENT NOT NULL, tipo_tm VARCHAR(50) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE texto_mano_obra ADD CONSTRAINT FK_39AA516FF3793DAC FOREIGN KEY (tipo_xo_id) REFERENCES tipo_mano_obra (id)');
        $this->addSql('ALTER TABLE mano_obra ADD categoria_mo_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE mano_obra ADD CONSTRAINT FK_C0A6B2F45CF27655 FOREIGN KEY (categoria_mo_id) REFERENCES tipo_mano_obra (id)');
        $this->addSql('CREATE INDEX IDX_C0A6B2F45CF27655 ON mano_obra (categoria_mo_id)');

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE texto_mano_obra');
        $this->addSql('DROP TABLE tipo_mano_obra');
        $this->addSql('ALTER TABLE mano_obra DROP categoria_mo_id');
       
    }
}
