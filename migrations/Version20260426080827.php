<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260426080827 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, role VARCHAR(20) NOT NULL, borrow_limit INT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE author_book ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL, ADD book_id INT NOT NULL, ADD author_id INT NOT NULL, ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE author_book ADD CONSTRAINT FK_2F0A2BEE16A2B381 FOREIGN KEY (book_id) REFERENCES books (id)');
        $this->addSql('ALTER TABLE author_book ADD CONSTRAINT FK_2F0A2BEEF675F31B FOREIGN KEY (author_id) REFERENCES authors (id)');
        $this->addSql('ALTER TABLE author_book ADD CONSTRAINT FK_2F0A2BEE896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id)');
        $this->addSql('CREATE INDEX IDX_2F0A2BEE16A2B381 ON author_book (book_id)');
        $this->addSql('CREATE INDEX IDX_2F0A2BEEF675F31B ON author_book (author_id)');
        $this->addSql('CREATE INDEX IDX_2F0A2BEE896DBBDE ON author_book (updated_by_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2F0A2BEE16A2B381F675F31B ON author_book (book_id, author_id)');
        $this->addSql('ALTER TABLE authors ADD first_name VARCHAR(100) NOT NULL, ADD last_name VARCHAR(100) NOT NULL, ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE book_category ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL, ADD book_id INT NOT NULL, ADD category_id INT NOT NULL, ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE book_category ADD CONSTRAINT FK_1FB30F9816A2B381 FOREIGN KEY (book_id) REFERENCES books (id)');
        $this->addSql('ALTER TABLE book_category ADD CONSTRAINT FK_1FB30F9812469DE2 FOREIGN KEY (category_id) REFERENCES categories (id)');
        $this->addSql('ALTER TABLE book_category ADD CONSTRAINT FK_1FB30F98896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id)');
        $this->addSql('CREATE INDEX IDX_1FB30F9816A2B381 ON book_category (book_id)');
        $this->addSql('CREATE INDEX IDX_1FB30F9812469DE2 ON book_category (category_id)');
        $this->addSql('CREATE INDEX IDX_1FB30F98896DBBDE ON book_category (updated_by_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1FB30F9816A2B38112469DE2 ON book_category (book_id, category_id)');
        $this->addSql('ALTER TABLE borrows ADD borrowed_at DATETIME NOT NULL, ADD due_date DATETIME NOT NULL, ADD returned_at DATETIME DEFAULT NULL, ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL, ADD book_id INT NOT NULL, ADD member_id INT NOT NULL');
        $this->addSql('ALTER TABLE borrows ADD CONSTRAINT FK_D03AA72F16A2B381 FOREIGN KEY (book_id) REFERENCES books (id)');
        $this->addSql('ALTER TABLE borrows ADD CONSTRAINT FK_D03AA72F7597D3FE FOREIGN KEY (member_id) REFERENCES users (id)');
        $this->addSql('CREATE INDEX IDX_D03AA72F16A2B381 ON borrows (book_id)');
        $this->addSql('CREATE INDEX IDX_D03AA72F7597D3FE ON borrows (member_id)');
        $this->addSql('ALTER TABLE categories ADD name VARCHAR(100) NOT NULL, ADD slug VARCHAR(120) NOT NULL, ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3AF34668989D9B62 ON categories (slug)');
        $this->addSql('ALTER TABLE settings ADD `key` VARCHAR(100) NOT NULL, ADD value LONGTEXT NOT NULL, ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL, ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE settings ADD CONSTRAINT FK_E545A0C5896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E545A0C58A90ABA9 ON settings (`key`)');
        $this->addSql('CREATE INDEX IDX_E545A0C5896DBBDE ON settings (updated_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE users');
        $this->addSql('ALTER TABLE author_book DROP FOREIGN KEY FK_2F0A2BEE16A2B381');
        $this->addSql('ALTER TABLE author_book DROP FOREIGN KEY FK_2F0A2BEEF675F31B');
        $this->addSql('ALTER TABLE author_book DROP FOREIGN KEY FK_2F0A2BEE896DBBDE');
        $this->addSql('DROP INDEX IDX_2F0A2BEE16A2B381 ON author_book');
        $this->addSql('DROP INDEX IDX_2F0A2BEEF675F31B ON author_book');
        $this->addSql('DROP INDEX IDX_2F0A2BEE896DBBDE ON author_book');
        $this->addSql('DROP INDEX UNIQ_2F0A2BEE16A2B381F675F31B ON author_book');
        $this->addSql('ALTER TABLE author_book DROP created_at, DROP updated_at, DROP book_id, DROP author_id, DROP updated_by_id');
        $this->addSql('ALTER TABLE authors DROP first_name, DROP last_name, DROP created_at, DROP updated_at');
        $this->addSql('ALTER TABLE book_category DROP FOREIGN KEY FK_1FB30F9816A2B381');
        $this->addSql('ALTER TABLE book_category DROP FOREIGN KEY FK_1FB30F9812469DE2');
        $this->addSql('ALTER TABLE book_category DROP FOREIGN KEY FK_1FB30F98896DBBDE');
        $this->addSql('DROP INDEX IDX_1FB30F9816A2B381 ON book_category');
        $this->addSql('DROP INDEX IDX_1FB30F9812469DE2 ON book_category');
        $this->addSql('DROP INDEX IDX_1FB30F98896DBBDE ON book_category');
        $this->addSql('DROP INDEX UNIQ_1FB30F9816A2B38112469DE2 ON book_category');
        $this->addSql('ALTER TABLE book_category DROP created_at, DROP updated_at, DROP book_id, DROP category_id, DROP updated_by_id');
        $this->addSql('ALTER TABLE borrows DROP FOREIGN KEY FK_D03AA72F16A2B381');
        $this->addSql('ALTER TABLE borrows DROP FOREIGN KEY FK_D03AA72F7597D3FE');
        $this->addSql('DROP INDEX IDX_D03AA72F16A2B381 ON borrows');
        $this->addSql('DROP INDEX IDX_D03AA72F7597D3FE ON borrows');
        $this->addSql('ALTER TABLE borrows DROP borrowed_at, DROP due_date, DROP returned_at, DROP created_at, DROP updated_at, DROP book_id, DROP member_id');
        $this->addSql('DROP INDEX UNIQ_3AF34668989D9B62 ON categories');
        $this->addSql('ALTER TABLE categories DROP name, DROP slug, DROP created_at, DROP updated_at');
        $this->addSql('ALTER TABLE settings DROP FOREIGN KEY FK_E545A0C5896DBBDE');
        $this->addSql('DROP INDEX UNIQ_E545A0C58A90ABA9 ON settings');
        $this->addSql('DROP INDEX IDX_E545A0C5896DBBDE ON settings');
        $this->addSql('ALTER TABLE settings DROP `key`, DROP value, DROP created_at, DROP updated_at, DROP updated_by_id');
    }
}
