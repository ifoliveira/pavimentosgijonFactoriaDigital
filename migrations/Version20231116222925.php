<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231116222925 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE pagos (id INT AUTO_INCREMENT NOT NULL, cesta_id INT NOT NULL, banco_pg_id INT DEFAULT NULL, efectivo_pg_id INT DEFAULT NULL, fecha_pg DATETIME DEFAULT NULL, importe_pg DOUBLE PRECISION DEFAULT NULL, tipo_pg VARCHAR(25) DEFAULT NULL, INDEX IDX_DA9B0DFF79B6AE57 (cesta_id), INDEX IDX_DA9B0DFFD1DAB94C (banco_pg_id), INDEX IDX_DA9B0DFF2D4C4952 (efectivo_pg_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE pagos ADD CONSTRAINT FK_DA9B0DFF79B6AE57 FOREIGN KEY (cesta_id) REFERENCES cestas (id)');
        $this->addSql('ALTER TABLE pagos ADD CONSTRAINT FK_DA9B0DFFD1DAB94C FOREIGN KEY (banco_pg_id) REFERENCES banco (id)');
        $this->addSql('ALTER TABLE pagos ADD CONSTRAINT FK_DA9B0DFF2D4C4952 FOREIGN KEY (efectivo_pg_id) REFERENCES efectivo (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pagos DROP FOREIGN KEY FK_DA9B0DFF79B6AE57');
        $this->addSql('ALTER TABLE pagos DROP FOREIGN KEY FK_DA9B0DFFD1DAB94C');
        $this->addSql('ALTER TABLE pagos DROP FOREIGN KEY FK_DA9B0DFF2D4C4952');
        $this->addSql('DROP TABLE pagos');
    }
}
