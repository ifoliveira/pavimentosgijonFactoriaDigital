<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230511180543 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE economicpresu ADD banco_eco_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE economicpresu ADD CONSTRAINT FK_45996642249EB7E8 FOREIGN KEY (banco_eco_id) REFERENCES banco (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_45996642249EB7E8 ON economicpresu (banco_eco_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE economicpresu DROP FOREIGN KEY FK_45996642249EB7E8');
        $this->addSql('DROP INDEX UNIQ_45996642249EB7E8 ON economicpresu');
        $this->addSql('ALTER TABLE economicpresu DROP banco_eco_id');

    }
}
