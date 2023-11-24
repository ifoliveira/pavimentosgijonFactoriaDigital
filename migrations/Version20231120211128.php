<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231120211128 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cestas DROP FOREIGN KEY FK_F1E891F42D65116B');
        $this->addSql('DROP INDEX UNIQ_F1E891F42D65116B ON cestas');
        $this->addSql('ALTER TABLE cestas DROP banco_cs_id_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cestas ADD banco_cs_id_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE cestas ADD CONSTRAINT FK_F1E891F42D65116B FOREIGN KEY (banco_cs_id_id) REFERENCES banco (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F1E891F42D65116B ON cestas (banco_cs_id_id)');
    }
}
