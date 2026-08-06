<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260804072542 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE proyecto_gasto ADD base_prevista NUMERIC(10, 2) DEFAULT NULL, ADD tipo_iva_previsto NUMERIC(5, 2) DEFAULT NULL, ADD iva_previsto NUMERIC(10, 2) DEFAULT NULL, ADD recargo_previsto NUMERIC(10, 2) DEFAULT NULL, ADD base_real NUMERIC(10, 2) DEFAULT NULL, ADD tipo_iva_real NUMERIC(5, 2) DEFAULT NULL, ADD iva_real NUMERIC(10, 2) DEFAULT NULL, ADD recargo_real NUMERIC(10, 2) DEFAULT NULL, ADD iva_deducible TINYINT(1) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE proyecto_gasto DROP base_prevista, DROP tipo_iva_previsto, DROP iva_previsto, DROP recargo_previsto, DROP base_real, DROP tipo_iva_real, DROP iva_real, DROP recargo_real, DROP iva_deducible');
    }
}
