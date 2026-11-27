<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260603210438 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE proyecto_gasto ADD banco_movimiento_id INT DEFAULT NULL, ADD efectivo_movimiento_id INT DEFAULT NULL, ADD fecha_confirmado DATETIME DEFAULT NULL, ADD fecha_pagado DATETIME DEFAULT NULL, ADD origen VARCHAR(30) DEFAULT NULL, ADD afecta_caja TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE proyecto_gasto ADD CONSTRAINT FK_ACA8DA5ADF5BD7CB FOREIGN KEY (banco_movimiento_id) REFERENCES banco (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE proyecto_gasto ADD CONSTRAINT FK_ACA8DA5A94EF8609 FOREIGN KEY (efectivo_movimiento_id) REFERENCES efectivo (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_ACA8DA5ADF5BD7CB ON proyecto_gasto (banco_movimiento_id)');
        $this->addSql('CREATE INDEX IDX_ACA8DA5A94EF8609 ON proyecto_gasto (efectivo_movimiento_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE proyecto_gasto DROP FOREIGN KEY FK_ACA8DA5ADF5BD7CB');
        $this->addSql('ALTER TABLE proyecto_gasto DROP FOREIGN KEY FK_ACA8DA5A94EF8609');
        $this->addSql('DROP INDEX IDX_ACA8DA5ADF5BD7CB ON proyecto_gasto');
        $this->addSql('DROP INDEX IDX_ACA8DA5A94EF8609 ON proyecto_gasto');
        $this->addSql('ALTER TABLE proyecto_gasto DROP banco_movimiento_id, DROP efectivo_movimiento_id, DROP fecha_confirmado, DROP fecha_pagado, DROP origen, DROP afecta_caja');
    }
}
