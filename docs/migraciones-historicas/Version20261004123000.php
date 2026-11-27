<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261004123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keep evento.sesion_id nullable and add the session foreign key only when existing values are valid.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evento MODIFY sesion_id VARCHAR(36) DEFAULT NULL');

        $orphanCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM evento e LEFT JOIN sesion s ON s.id = e.sesion_id WHERE e.sesion_id IS NOT NULL AND s.id IS NULL'
        );

        if ($orphanCount > 0) {
            throw new \RuntimeException(sprintf(
                'No se puede añadir la clave foránea evento.sesion_id: hay %d eventos con sesion_id no nulo sin sesión asociada.',
                $orphanCount
            ));
        }

        $table = $schema->getTable('evento');

        if (!$table->hasIndex('IDX_47860B051CCCADCB')) {
            $this->addSql('CREATE INDEX IDX_47860B051CCCADCB ON evento (sesion_id)');
        }

        if (!$table->hasForeignKey('FK_47860B051CCCADCB')) {
            $this->addSql('ALTER TABLE evento ADD CONSTRAINT FK_47860B051CCCADCB FOREIGN KEY (sesion_id) REFERENCES sesion (id)');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('evento');

        if ($table->hasForeignKey('FK_47860B051CCCADCB')) {
            $this->addSql('ALTER TABLE evento DROP FOREIGN KEY FK_47860B051CCCADCB');
        }
    }
}
