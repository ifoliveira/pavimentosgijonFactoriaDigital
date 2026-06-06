<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260605224052 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_movimiento DROP FOREIGN KEY FK_66B6E7D67645698E');
        $this->addSql('ALTER TABLE stock_movimiento ADD CONSTRAINT FK_66B6E7D67645698E FOREIGN KEY (producto_id) REFERENCES productos (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_movimiento DROP FOREIGN KEY FK_66B6E7D67645698E');
        $this->addSql('ALTER TABLE stock_movimiento ADD CONSTRAINT FK_66B6E7D67645698E FOREIGN KEY (producto_id) REFERENCES catalogo_producto (id) ON UPDATE NO ACTION ON DELETE SET NULL');
    }
}
