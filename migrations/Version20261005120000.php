<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261005120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add private visit-card photo metadata to proyecto.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE proyecto ADD ficha_foto_path VARCHAR(255) DEFAULT NULL, ADD direccion_obra_extraida VARCHAR(255) DEFAULT NULL, ADD ficha_datos_extraidos JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE clientes CHANGE telefono1_cl telefono1_cl VARCHAR(20) DEFAULT NULL, CHANGE telefono2_cl telefono2_cl VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clientes CHANGE telefono1_cl telefono1_cl VARCHAR(9) DEFAULT NULL, CHANGE telefono2_cl telefono2_cl VARCHAR(9) DEFAULT NULL');
        $this->addSql('ALTER TABLE proyecto DROP ficha_foto_path, DROP direccion_obra_extraida, DROP ficha_datos_extraidos');
    }
}
