<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210327090139 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE presupuestos (id INT AUTO_INCREMENT NOT NULL, estado_pe_id INT NOT NULL, user_pe_id INT NOT NULL, ticket_id INT NOT NULL, fechaini_pe DATE NOT NULL, costetot_pe DOUBLE PRECISION NOT NULL, importetot_pe DOUBLE PRECISION NOT NULL, descuaeto_pe DOUBLE PRECISION NOT NULL, tipopagotot_pe VARCHAR(20) NOT NULL, importesnal_pe DOUBLE PRECISION NOT NULL, tipopagosnal_pe VARCHAR(20) NOT NULL, manoobra_pe LONGTEXT DEFAULT NULL, INDEX IDX_4CF2F0DA0F38A33 (estado_pe_id), INDEX IDX_4CF2F0D48CAF799 (user_pe_id), UNIQUE INDEX UNIQ_4CF2F0D700047D2 (ticket_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE presupuestos ADD CONSTRAINT FK_4CF2F0DA0F38A33 FOREIGN KEY (estado_pe_id) REFERENCES estadocestas (id)');
        $this->addSql('ALTER TABLE presupuestos ADD CONSTRAINT FK_4CF2F0D48CAF799 FOREIGN KEY (user_pe_id) REFERENCES admin (id)');
        $this->addSql('ALTER TABLE presupuestos ADD CONSTRAINT FK_4CF2F0D700047D2 FOREIGN KEY (ticket_id) REFERENCES cestas (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE presupuestos');
      
    }
}
