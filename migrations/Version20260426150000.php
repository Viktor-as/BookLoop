<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260426150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename settings.key to setting_key (MySQL reserved word; Doctrine INSERTs were invalid).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_E545A0C58A90ABA9 ON settings');
        $this->addSql('ALTER TABLE settings CHANGE `key` setting_key VARCHAR(100) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_settings_setting_key ON settings (setting_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_settings_setting_key ON settings');
        $this->addSql('ALTER TABLE settings CHANGE setting_key `key` VARCHAR(100) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E545A0C58A90ABA9 ON settings (`key`)');
    }
}
