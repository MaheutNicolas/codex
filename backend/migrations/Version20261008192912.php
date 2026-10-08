<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261008192912 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Relations between entries: one state per pair and chapter';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE knowledge_relation (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(100) NOT NULL, type VARCHAR(20) NOT NULL, chapter INT DEFAULT 0 NOT NULL, revealed TINYINT DEFAULT 1 NOT NULL, note VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, book_id INT NOT NULL, source_id INT NOT NULL, target_id INT NOT NULL, INDEX idx_relation_target (target_id), INDEX idx_relation_book_chapter (book_id, chapter), UNIQUE INDEX uniq_relation_book_slug (book_id, slug), UNIQUE INDEX uniq_relation_pair_chapter (source_id, target_id, chapter), INDEX IDX_1FF19ED616A2B381 (book_id), INDEX IDX_1FF19ED6953C1C61 (source_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE knowledge_relation ADD CONSTRAINT FK_1FF19ED616A2B381 FOREIGN KEY (book_id) REFERENCES book (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE knowledge_relation ADD CONSTRAINT FK_1FF19ED6953C1C61 FOREIGN KEY (source_id) REFERENCES knowledge (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE knowledge_relation ADD CONSTRAINT FK_1FF19ED6158E0B66 FOREIGN KEY (target_id) REFERENCES knowledge (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE knowledge_relation DROP FOREIGN KEY FK_1FF19ED616A2B381');
        $this->addSql('ALTER TABLE knowledge_relation DROP FOREIGN KEY FK_1FF19ED6953C1C61');
        $this->addSql('ALTER TABLE knowledge_relation DROP FOREIGN KEY FK_1FF19ED6158E0B66');
        $this->addSql('DROP TABLE knowledge_relation');
    }
}
