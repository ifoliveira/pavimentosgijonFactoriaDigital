<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250207165941 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE consultas (id INT AUTO_INCREMENT NOT NULL, nombre VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, telefono VARCHAR(20) DEFAULT NULL, pregunta VARCHAR(2500) DEFAULT NULL, timestamp DATETIME DEFAULT NULL, atencion TINYINT(1) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE email (id INT AUTO_INCREMENT NOT NULL, subject VARCHAR(255) NOT NULL, body LONGTEXT NOT NULL, from_email VARCHAR(255) NOT NULL, is_read TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE image (id INT AUTO_INCREMENT NOT NULL, post_id INT NOT NULL, file_path VARCHAR(255) DEFAULT NULL, image_type VARCHAR(255) DEFAULT NULL, alt VARCHAR(255) DEFAULT NULL, INDEX IDX_C53D045F4B89032C (post_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE post (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) DEFAULT NULL, slug VARCHAR(255) NOT NULL, meta_description VARCHAR(255) DEFAULT NULL, header_h1 VARCHAR(255) DEFAULT NULL, header_h2 VARCHAR(255) DEFAULT NULL, header_h3 VARCHAR(255) DEFAULT NULL, header_h4 VARCHAR(255) DEFAULT NULL, content LONGTEXT DEFAULT NULL, published_at DATETIME DEFAULT NULL, is_published TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE image ADD CONSTRAINT FK_C53D045F4B89032C FOREIGN KEY (post_id) REFERENCES post (id)');
        $this->addSql('ALTER TABLE cestas ADD user_admin_id INT DEFAULT NULL, DROP user_cs');
        $this->addSql('ALTER TABLE cestas ADD CONSTRAINT FK_F1E891F484A66610 FOREIGN KEY (user_admin_id) REFERENCES `admin` (id)');
        $this->addSql('CREATE INDEX IDX_F1E891F484A66610 ON cestas (user_admin_id)');
        $this->addSql('ALTER TABLE encuesta CHANGE id id INT AUTO_INCREMENT NOT NULL');
        $this->addSql('ALTER TABLE presupuestos DROP FOREIGN KEY FK_4CF2F0D798551F2');
        $this->addSql('ALTER TABLE presupuestos CHANGE cliente_pe_id cliente_pe_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE presupuestos ADD CONSTRAINT FK_4CF2F0D798551F2 FOREIGN KEY (cliente_pe_id) REFERENCES clientes (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE tiposmovimiento ADD patron_busqueda VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE image DROP FOREIGN KEY FK_C53D045F4B89032C');
        $this->addSql('DROP TABLE consultas');
        $this->addSql('DROP TABLE email');
        $this->addSql('DROP TABLE image');
        $this->addSql('DROP TABLE post');
        $this->addSql('ALTER TABLE cestas DROP FOREIGN KEY FK_F1E891F484A66610');
        $this->addSql('DROP INDEX IDX_F1E891F484A66610 ON cestas');
        $this->addSql('ALTER TABLE cestas ADD user_cs INT NOT NULL, DROP user_admin_id');
        $this->addSql('ALTER TABLE encuesta CHANGE id id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE presupuestos DROP FOREIGN KEY FK_4CF2F0D798551F2');
        $this->addSql('ALTER TABLE presupuestos CHANGE cliente_pe_id cliente_pe_id INT NOT NULL');
        $this->addSql('ALTER TABLE presupuestos ADD CONSTRAINT FK_4CF2F0D798551F2 FOREIGN KEY (cliente_pe_id) REFERENCES clientes (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE tiposmovimiento DROP patron_busqueda');
    }
}
