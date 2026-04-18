<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260412072251 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mano_obra ADD banco_id INT DEFAULT NULL, ADD metodo_pago VARCHAR(20) DEFAULT NULL, ADD fecha_pago DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE mano_obra ADD CONSTRAINT FK_C0A6B2F4CC04A73E FOREIGN KEY (banco_id) REFERENCES banco (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_C0A6B2F4CC04A73E ON mano_obra (banco_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mano_obra DROP FOREIGN KEY FK_C0A6B2F4CC04A73E');
        $this->addSql('DROP INDEX IDX_C0A6B2F4CC04A73E ON mano_obra');
        $this->addSql('ALTER TABLE mano_obra DROP banco_id, DROP metodo_pago, DROP fecha_pago');
    }
}
