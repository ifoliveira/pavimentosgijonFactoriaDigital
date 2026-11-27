<?php

declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115150000 extends AbstractMigration
{
    public function getDescription(): string { return 'Autor de movimientos de efectivo e historial de recuentos de caja.'; }
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE efectivo ADD registrado_por VARCHAR(180) DEFAULT NULL');
        $this->addSql("CREATE TABLE caja_recuento (id INT AUTO_INCREMENT NOT NULL, fecha DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', esperado_centimos INT NOT NULL, contado_centimos INT NOT NULL, registrado_por VARCHAR(180) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE caja_recuento');
        $this->addSql('ALTER TABLE efectivo DROP registrado_por');
    }
}
