<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260428120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add borrows(book_id, returned_at) and books(updated_at, id) indexes for catalog availability and sort.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_borrows_book_returned ON borrows (book_id, returned_at)');
        $this->addSql('DROP INDEX IDX_D03AA72F16A2B381 ON borrows');
        $this->addSql('CREATE INDEX idx_books_catalog_sort ON books (updated_at, id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_books_catalog_sort ON books');
        $this->addSql('CREATE INDEX IDX_D03AA72F16A2B381 ON borrows (book_id)');
        $this->addSql('DROP INDEX idx_borrows_book_returned ON borrows');
    }
}
