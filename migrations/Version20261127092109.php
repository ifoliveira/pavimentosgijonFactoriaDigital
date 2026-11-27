<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127092109 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Implantacion ERP 2027: proyectos, anticipos, bolsa de gastos y control de caja';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        // 1. Bolsa de gastos: ajustes manuales.

        $this->addSql("
            CREATE TABLE bolsa_gastos_ajuste (
                id INT AUTO_INCREMENT NOT NULL,
                fecha DATE NOT NULL
                    COMMENT '(DC2Type:date_immutable)',
                importe_base NUMERIC(12, 2) NOT NULL,
                motivo VARCHAR(255) NOT NULL,
                creado_en DATETIME NOT NULL
                    COMMENT '(DC2Type:datetime_immutable)',
                INDEX IDX_BOLSA_GASTOS_AJUSTE_FECHA (fecha),
                PRIMARY KEY(id)
            )
            DEFAULT CHARACTER SET utf8mb4
            COLLATE utf8mb4_unicode_ci
            ENGINE = InnoDB
        ");

        // 2. Bolsa de gastos: configuracion global.

        $this->addSql("
            CREATE TABLE bolsa_gastos_configuracion (
                id INT NOT NULL,
                fecha_inicio DATE DEFAULT NULL
                    COMMENT '(DC2Type:date_immutable)',
                iva_estimado NUMERIC(5, 2) NOT NULL,
                plazo_pago_dias INT NOT NULL,
                plazo_obra_dias INT NOT NULL,
                proveedores_excluidos LONGTEXT NOT NULL
                    COLLATE utf8mb4_bin
                    COMMENT '(DC2Type:json)'
                    CHECK (JSON_VALID(proveedores_excluidos)),
                PRIMARY KEY(id)
            )
            DEFAULT CHARACTER SET utf8mb4
            COLLATE utf8mb4_unicode_ci
            ENGINE = InnoDB
        ");

        // 3. Historial de recuentos de caja.

        $this->addSql("
            CREATE TABLE caja_recuento (
                id INT AUTO_INCREMENT NOT NULL,
                fecha DATETIME NOT NULL
                    COMMENT '(DC2Type:datetime_immutable)',
                esperado_centimos INT NOT NULL,
                contado_centimos INT NOT NULL,
                registrado_por VARCHAR(180) NOT NULL,
                PRIMARY KEY(id)
            )
            DEFAULT CHARACTER SET utf8mb4
            COLLATE utf8mb4_unicode_ci
            ENGINE = InnoDB
        ");

        // 4. Identificacion del autor de movimientos de efectivo.

        $this->addSql("
            ALTER TABLE efectivo
            ADD registrado_por VARCHAR(180) DEFAULT NULL
        ");

        // 5. Proyectos pendientes de cliente y tipo de obra.

        $this->addSql("
            ALTER TABLE proyecto
            ADD tipo_obra VARCHAR(50) DEFAULT NULL,
            CHANGE cliente_id cliente_id INT DEFAULT NULL
        ");

        // 6. Relacion entre cobro y factura de anticipo.

        $this->addSql("
            ALTER TABLE proyecto_cobro
            ADD factura_anticipo_id INT DEFAULT NULL
        ");

        $this->addSql("
            CREATE UNIQUE INDEX UNIQ_F249656861447AC
            ON proyecto_cobro (factura_anticipo_id)
        ");

        $this->addSql("
            ALTER TABLE proyecto_cobro
            ADD CONSTRAINT FK_F249656861447AC
            FOREIGN KEY (factura_anticipo_id)
            REFERENCES documento (id)
            ON DELETE RESTRICT
        ");

        // 7. Configuracion inicial de la bolsa de gastos.

        $this->addSql("
            INSERT INTO bolsa_gastos_configuracion (
                id,
                fecha_inicio,
                iva_estimado,
                plazo_pago_dias,
                plazo_obra_dias,
                proveedores_excluidos
            )
            VALUES (1, NULL, '21.00', 30, 60, '[]')
        ");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'La implantacion del ERP 2027 no admite una reversion automatica sin riesgo de perdida de datos.'
        );
    }
}