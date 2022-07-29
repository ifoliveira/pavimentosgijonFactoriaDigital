<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220726201236 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cuentas (id INT AUTO_INCREMENT NOT NULL, banco_id INT NOT NULL, referencia VARCHAR(255) NOT NULL, iban VARCHAR(255) NOT NULL, INDEX IDX_10E4D795CC04A73E (banco_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE cuentas ADD CONSTRAINT FK_10E4D795CC04A73E FOREIGN KEY (banco_id) REFERENCES banco_referencias (id)');
     }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE cuentas');
     
    }
}
