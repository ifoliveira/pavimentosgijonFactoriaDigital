<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Configuración y ajustes de la bolsa global de gastos; fecha de inicio sin configurar.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE bolsa_gastos_configuracion (id INT NOT NULL, fecha_inicio DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)', iva_estimado NUMERIC(5, 2) NOT NULL, plazo_pago_dias INT NOT NULL, plazo_obra_dias INT NOT NULL, proveedores_excluidos JSON NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("INSERT INTO bolsa_gastos_configuracion (id, fecha_inicio, iva_estimado, plazo_pago_dias, plazo_obra_dias, proveedores_excluidos) VALUES (1, NULL, '21.00', 30, 60, '[]')");
        $this->addSql("CREATE TABLE bolsa_gastos_ajuste (id INT AUTO_INCREMENT NOT NULL, fecha DATE NOT NULL COMMENT '(DC2Type:date_immutable)', importe_base NUMERIC(12, 2) NOT NULL, motivo VARCHAR(255) NOT NULL, creado_en DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_BOLSA_GASTOS_AJUSTE_FECHA (fecha), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE bolsa_gastos_ajuste');
        $this->addSql('DROP TABLE bolsa_gastos_configuracion');
    }
}
