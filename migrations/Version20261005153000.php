<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261005153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Telegram photo budget reception persistence.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE telegram_presupuesto_recepcion (id INT AUTO_INCREMENT NOT NULL, proyecto_id INT DEFAULT NULL, documento_id INT DEFAULT NULL, bot_id VARCHAR(80) NOT NULL, update_id INT NOT NULL, chat_id BIGINT NOT NULL, message_id INT NOT NULL, from_user_id BIGINT DEFAULT NULL, from_username VARCHAR(120) DEFAULT NULL, estado VARCHAR(40) NOT NULL, imagen_path VARCHAR(255) DEFAULT NULL, datos_extraidos JSON DEFAULT NULL, adaptacion JSON DEFAULT NULL, ultimo_update_procesado INT DEFAULT NULL, creado_en DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', actualizado_en DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', notificado_en DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', error_resumen VARCHAR(255) DEFAULT NULL, INDEX IDX_7D5E7D7F8F4703B (proyecto_id), INDEX IDX_7D5E7D7FE3A2B1A (documento_id), UNIQUE INDEX uniq_telegram_presupuesto_bot_update (bot_id, update_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE telegram_presupuesto_recepcion ADD CONSTRAINT FK_7D5E7D7F8F4703B FOREIGN KEY (proyecto_id) REFERENCES proyecto (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE telegram_presupuesto_recepcion ADD CONSTRAINT FK_7D5E7D7FE3A2B1A FOREIGN KEY (documento_id) REFERENCES documento (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE telegram_presupuesto_recepcion DROP FOREIGN KEY FK_7D5E7D7F8F4703B');
        $this->addSql('ALTER TABLE telegram_presupuesto_recepcion DROP FOREIGN KEY FK_7D5E7D7FE3A2B1A');
        $this->addSql('DROP TABLE telegram_presupuesto_recepcion');
    }
}
