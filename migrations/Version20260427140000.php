<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260427140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add books.description (nullable long text for detail page and admin CRUD).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE books ADD description LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE books DROP description');
    }
}
