<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261004120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add initial generation Telegram notification marker to web budgets.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->getTable('presupuesto_web')->hasColumn('telegram_generacion_inicial_enviado_at')) {
            return;
        }

        $this->addSql('ALTER TABLE presupuesto_web ADD telegram_generacion_inicial_enviado_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->getTable('presupuesto_web')->hasColumn('telegram_generacion_inicial_enviado_at')) {
            return;
        }

        $this->addSql('ALTER TABLE presupuesto_web DROP telegram_generacion_inicial_enviado_at');
    }
}
