<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127074554 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Saneamiento del esquema MariaDB: tipos JSON e indices de Telegram';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        // Normalizar los campos JSON conservando su validacion.

        $this->addSql("
            ALTER TABLE telegram_presupuesto_recepcion
            MODIFY COLUMN datos_extraidos
            LONGTEXT CHARACTER SET utf8mb4
            COLLATE utf8mb4_bin DEFAULT NULL
            COMMENT '(DC2Type:json)'
            CHECK (JSON_VALID(datos_extraidos))
        ");

        $this->addSql("
            ALTER TABLE telegram_presupuesto_recepcion
            MODIFY COLUMN adaptacion
            LONGTEXT CHARACTER SET utf8mb4
            COLLATE utf8mb4_bin DEFAULT NULL
            COMMENT '(DC2Type:json)'
            CHECK (JSON_VALID(adaptacion))
        ");

        $this->addSql("
            ALTER TABLE proyecto
            MODIFY COLUMN ficha_datos_extraidos
            LONGTEXT CHARACTER SET utf8mb4
            COLLATE utf8mb4_bin DEFAULT NULL
            COMMENT '(DC2Type:json)'
            CHECK (JSON_VALID(ficha_datos_extraidos))
        ");

        $this->addSql("
            ALTER TABLE presupuesto_web_interaccion
            MODIFY COLUMN datos
            LONGTEXT CHARACTER SET utf8mb4
            COLLATE utf8mb4_bin DEFAULT NULL
            COMMENT '(DC2Type:json)'
            CHECK (JSON_VALID(datos))
        ");

        $this->addSql("
            ALTER TABLE catalogo_producto_configuracion
            MODIFY COLUMN condiciones
            LONGTEXT CHARACTER SET utf8mb4
            COLLATE utf8mb4_bin DEFAULT NULL
            COMMENT '(DC2Type:json)'
            CHECK (JSON_VALID(condiciones))
        ");

        $this->addSql("
            ALTER TABLE evento
            MODIFY COLUMN datos
            LONGTEXT CHARACTER SET utf8mb4
            COLLATE utf8mb4_bin DEFAULT NULL
            COMMENT '(DC2Type:json)'
            CHECK (JSON_VALID(datos))
        ");

        // Renombrar indices sin eliminar las claves foraneas.

        $this->addSql("
            ALTER TABLE telegram_presupuesto_recepcion
            RENAME INDEX IDX_7D5E7D7F8F4703B
                TO IDX_7EDE6F34F625D1BA,
            RENAME INDEX IDX_7D5E7D7FE3A2B1A
                TO IDX_7EDE6F3445C0CF75
        ");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'El saneamiento establece la nueva base de referencia del ERP.'
        );
    }
}