<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210406154801 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE presupuestos ADD timestamp_mod_pe DATETIME');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE presupuestos DROP timestamp_mod_pe, CHANGE ticket_id ticket_id INT DEFAULT NULL, CHANGE fechaini_pe fechaini_pe DATE DEFAULT NULL, CHANGE costetot_pe costetot_pe DOUBLE PRECISION DEFAULT NULL, CHANGE tipopagotot_pe tipopagotot_pe VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, CHANGE importesnal_pe importesnal_pe DOUBLE PRECISION DEFAULT NULL, CHANGE tipopagosnal_pe tipopagosnal_pe VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`');
    }
}
