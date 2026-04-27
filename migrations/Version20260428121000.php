<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Align books.slug unique index name with Doctrine auto-naming so schema:validate passes.
 */
final class Version20260428121000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename books uniq_books_slug to Doctrine default name if present.';
    }

    public function up(Schema $schema): void
    {
        $table = $this->connection->createSchemaManager()->introspectTable('books');
        if ($table->hasIndex('uniq_books_slug')) {
            $this->addSql('ALTER TABLE books RENAME INDEX uniq_books_slug TO UNIQ_4A1B2A92989D9B62');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $this->connection->createSchemaManager()->introspectTable('books');
        foreach ($table->getIndexes() as $idx) {
            if (strtoupper($idx->getName()) === 'UNIQ_4A1B2A92989D9B62') {
                $this->addSql('ALTER TABLE books RENAME INDEX '.$idx->getQuotedName($this->connection->getDatabasePlatform()).' TO uniq_books_slug');

                return;
            }
        }
    }
}
