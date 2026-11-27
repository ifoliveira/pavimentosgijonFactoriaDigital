<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Permite crear proyectos pendientes de cliente y guarda su tipo de obra.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE proyecto CHANGE cliente_id cliente_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE proyecto ADD tipo_obra VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE proyecto DROP tipo_obra');
        $this->addSql('ALTER TABLE proyecto CHANGE cliente_id cliente_id INT NOT NULL');
    }
}
