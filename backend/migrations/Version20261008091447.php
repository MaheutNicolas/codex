<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261008091447 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'One API key per book, with its token stored as is';
    }

    public function up(Schema $schema): void
    {
        // The old keys only had a hash of their token and several could exist per book: they cannot be kept.
        $this->addSql('DELETE FROM api_key');
        $this->addSql('ALTER TABLE api_key DROP INDEX IDX_C912ED9D16A2B381, ADD UNIQUE INDEX uniq_api_key_book (book_id)');
        $this->addSql('DROP INDEX uniq_api_key_token_hash ON api_key');
        $this->addSql('ALTER TABLE api_key DROP name, DROP prefix, CHANGE token_hash token VARCHAR(64) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_api_key_token ON api_key (token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE api_key DROP INDEX uniq_api_key_book, ADD INDEX IDX_C912ED9D16A2B381 (book_id)');
        $this->addSql('DROP INDEX uniq_api_key_token ON api_key');
        $this->addSql('ALTER TABLE api_key ADD name VARCHAR(100) NOT NULL, ADD prefix VARCHAR(16) NOT NULL, CHANGE token token_hash VARCHAR(64) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_api_key_token_hash ON api_key (token_hash)');
    }
}
