<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261123202223 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the version column for optimistic locking of task lists';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_task_lists ADD version INT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_task_lists DROP version');
    }
}
