<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260427120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop books.copies_total (single active borrow per book; no copy count).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE books DROP copies_total');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE books ADD copies_total INT NOT NULL DEFAULT 1');
    }
}
