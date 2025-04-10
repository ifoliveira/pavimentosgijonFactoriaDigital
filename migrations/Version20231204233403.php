<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231204233403 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE forecast ADD banco_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE forecast ADD CONSTRAINT FK_2A9C7844CC04A73E FOREIGN KEY (banco_id) REFERENCES banco (id)');
        $this->addSql('CREATE INDEX IDX_2A9C7844CC04A73E ON forecast (banco_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE forecast DROP FOREIGN KEY FK_2A9C7844CC04A73E');
        $this->addSql('DROP INDEX IDX_2A9C7844CC04A73E ON forecast');
        $this->addSql('ALTER TABLE forecast DROP banco_id');
    }
}
