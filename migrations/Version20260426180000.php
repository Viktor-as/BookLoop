<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260426180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique books.slug (backfill from title + id for uniqueness).';
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;
        $conn->executeStatement('ALTER TABLE books ADD slug VARCHAR(255) DEFAULT NULL');

        $rows = $conn->fetchAllAssociative('SELECT id, title FROM books ORDER BY id ASC');
        foreach ($rows as $row) {
            $slug = $this->slugFromTitleAndId((string) $row['title'], (int) $row['id']);
            $conn->executeStatement(
                'UPDATE books SET slug = ? WHERE id = ?',
                [$slug, $row['id']],
            );
        }

        $conn->executeStatement('CREATE UNIQUE INDEX uniq_books_slug ON books (slug)');
        $conn->executeStatement('ALTER TABLE books CHANGE slug slug VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $conn = $this->connection;
        $conn->executeStatement('DROP INDEX uniq_books_slug ON books');
        $conn->executeStatement('ALTER TABLE books DROP slug');
    }

    private function slugFromTitleAndId(string $title, int $id): string
    {
        $s = strtolower($title);
        $s = (string) preg_replace('/[^a-z0-9]+/', '-', $s);
        $s = trim($s, '-');
        if ($s === '') {
            $s = 'book';
        }

        return $s . '-' . $id;
    }
}
