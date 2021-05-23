<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210329163002 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $this->addSql('ALTER TABLE presupuestos ADD CONSTRAINT FK_4CF2F0D798551F2 FOREIGN KEY (cliente_pe_id) REFERENCES clientes (id)');
        $this->addSql('CREATE INDEX IDX_4CF2F0D798551F2 ON presupuestos (cliente_pe_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE presupuestos DROP FOREIGN KEY FK_4CF2F0D798551F2');
        $this->addSql('DROP INDEX IDX_4CF2F0D798551F2 ON presupuestos');
        $this->addSql('ALTER TABLE presupuestos DROP cliente_pe_id, CHANGE ticket_id ticket_id INT DEFAULT NULL, CHANGE tipopagotot_pe tipopagotot_pe VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, CHANGE tipopagosnal_pe tipopagosnal_pe VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`');
    }
}
