<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260919130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tracked web budget accesses and communication access tokens.';
    }

    public function up(Schema $schema): void
    {
        $this->connection->executeStatement(
            'ALTER TABLE presupuesto_web_comunicacion ADD token_acceso VARCHAR(64) DEFAULT NULL'
        );

        $tokens = [];

        $rows = $this->connection->fetchAllAssociative(
            'SELECT id FROM presupuesto_web_comunicacion WHERE token_acceso IS NULL'
        );

        foreach ($rows as $row) {
            do {
                $token = bin2hex(random_bytes(32));
            } while (isset($tokens[$token]));

            $tokens[$token] = true;

            $this->connection->update(
                'presupuesto_web_comunicacion',
                [
                    'token_acceso' => $token,
                ],
                [
                    'id' => $row['id'],
                ]
            );
        }

        $this->connection->executeStatement(
            'ALTER TABLE presupuesto_web_comunicacion CHANGE token_acceso token_acceso VARCHAR(64) NOT NULL'
        );

        $this->connection->executeStatement(
            'CREATE UNIQUE INDEX UNIQ_PRESUPUESTO_WEB_COMUNICACION_TOKEN_ACCESO
            ON presupuesto_web_comunicacion (token_acceso)'
        );

        $this->connection->executeStatement(
            'CREATE TABLE presupuesto_web_acceso (
                id INT AUTO_INCREMENT NOT NULL,
                presupuesto_web_id INT NOT NULL,
                comunicacion_origen_id INT DEFAULT NULL,
                fecha_acceso DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                INDEX IDX_2531742B6CF9DD50 (presupuesto_web_id),
                INDEX IDX_2531742B366A2741 (comunicacion_origen_id),
                INDEX IDX_PRESUPUESTO_WEB_ACCESO_FECHA (fecha_acceso),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4
            COLLATE `utf8mb4_unicode_ci`
            ENGINE = InnoDB'
        );

        $this->connection->executeStatement(
            'ALTER TABLE presupuesto_web_acceso
            ADD CONSTRAINT FK_2531742B6CF9DD50
            FOREIGN KEY (presupuesto_web_id)
            REFERENCES presupuesto_web (id)'
        );

        $this->connection->executeStatement(
            'ALTER TABLE presupuesto_web_acceso
            ADD CONSTRAINT FK_2531742B366A2741
            FOREIGN KEY (comunicacion_origen_id)
            REFERENCES presupuesto_web_comunicacion (id)'
        );
    }
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE presupuesto_web_acceso DROP FOREIGN KEY FK_2531742B6CF9DD50');
        $this->addSql('ALTER TABLE presupuesto_web_acceso DROP FOREIGN KEY FK_2531742B366A2741');
        $this->addSql('DROP TABLE presupuesto_web_acceso');
        $this->addSql('DROP INDEX UNIQ_PRESUPUESTO_WEB_COMUNICACION_TOKEN_ACCESO ON presupuesto_web_comunicacion');
        $this->addSql('ALTER TABLE presupuesto_web_comunicacion DROP token_acceso');
    }
}
