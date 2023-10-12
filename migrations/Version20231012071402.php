<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231012071402 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE presupuestos ADD impmanoobra_pagado DOUBLE PRECISION DEFAULT NULL, DROP ticketsnal_id');
        $this->addSql('ALTER TABLE productos CHANGE descripcion_pd descripcion_pd LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE presupuestos ADD ticketsnal_id INT DEFAULT NULL, DROP impmanoobra_pagado');
        $this->addSql('ALTER TABLE productos CHANGE descripcion_pd descripcion_pd LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`');
    }
}
