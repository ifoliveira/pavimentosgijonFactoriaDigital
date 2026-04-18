<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260406162717 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mano_obra ADD documento_mo_id INT DEFAULT NULL, CHANGE presupuesto_mo_id presupuesto_mo_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE mano_obra ADD CONSTRAINT FK_C0A6B2F4C1FC473E FOREIGN KEY (documento_mo_id) REFERENCES documento (id)');
        $this->addSql('CREATE INDEX IDX_C0A6B2F4C1FC473E ON mano_obra (documento_mo_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mano_obra DROP FOREIGN KEY FK_C0A6B2F4C1FC473E');
        $this->addSql('DROP INDEX IDX_C0A6B2F4C1FC473E ON mano_obra');
        $this->addSql('ALTER TABLE mano_obra DROP documento_mo_id, CHANGE presupuesto_mo_id presupuesto_mo_id INT NOT NULL');
    }
}
