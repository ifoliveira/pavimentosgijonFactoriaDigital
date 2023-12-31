<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231208204258 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE encuesta (id INT AUTO_INCREMENT NOT NULL, fecha DATE DEFAULT NULL, cliente VARCHAR(255) DEFAULT NULL, p1 VARCHAR(10) DEFAULT NULL, p2 VARCHAR(10) DEFAULT NULL, p3 VARCHAR(10) DEFAULT NULL, p4 VARCHAR(10) DEFAULT NULL, p5 VARCHAR(10) DEFAULT NULL, p6 VARCHAR(10) DEFAULT NULL, p7 VARCHAR(10) DEFAULT NULL, p8 VARCHAR(10) DEFAULT NULL, p9 VARCHAR(10) DEFAULT NULL, p10 VARCHAR(10) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE encuesta');
    }
}
