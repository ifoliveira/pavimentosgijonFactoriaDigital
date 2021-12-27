<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211227221747 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE mano_obra (id INT AUTO_INCREMENT NOT NULL, presupuesto_mo_id INT NOT NULL, tipo_mo VARCHAR(50) NOT NULL, texto_mo LONGTEXT NOT NULL, INDEX IDX_C0A6B2F4D3F5157B (presupuesto_mo_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE mano_obra ADD CONSTRAINT FK_C0A6B2F4D3F5157B FOREIGN KEY (presupuesto_mo_id) REFERENCES presupuestos (id)');
        
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE mano_obra');
        
    }
}
