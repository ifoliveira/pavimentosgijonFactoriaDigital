<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210221155953 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE tipoproducto (id INT AUTO_INCREMENT NOT NULL, decripcion_tp VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE productos ADD tipo_pd_id INT NOT NULL');
        $this->addSql('ALTER TABLE productos ADD CONSTRAINT FK_767490E61408F16C FOREIGN KEY (tipo_pd_id) REFERENCES tipoproducto (id)');
        $this->addSql('CREATE INDEX IDX_767490E61408F16C ON productos (tipo_pd_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE productos DROP FOREIGN KEY FK_767490E61408F16C');
        $this->addSql('DROP TABLE tipoproducto');
        $this->addSql('DROP INDEX IDX_767490E61408F16C ON productos');
        $this->addSql('ALTER TABLE productos DROP tipo_pd_id');
    }
}
