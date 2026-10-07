<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261007200556 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE book (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_CBE5A331A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE event (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(100) NOT NULL, title VARCHAR(255) NOT NULL, summary LONGTEXT NOT NULL, detail LONGTEXT DEFAULT NULL, world_order INT NOT NULL, world_date VARCHAR(100) DEFAULT NULL, chapter INT DEFAULT NULL, revealed TINYINT DEFAULT 1 NOT NULL, tags JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, book_id INT NOT NULL, INDEX idx_event_book_chapter_world_order (book_id, chapter, world_order), INDEX idx_event_book_world_order (book_id, world_order), UNIQUE INDEX uniq_event_book_slug (book_id, slug), INDEX IDX_3BAE0AA716A2B381 (book_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE event_participant (role VARCHAR(50) DEFAULT NULL, event_id INT NOT NULL, knowledge_id INT NOT NULL, INDEX idx_event_participant_knowledge_event (knowledge_id, event_id), INDEX IDX_7C16B89171F7E88B (event_id), INDEX IDX_7C16B891E7DC6902 (knowledge_id), PRIMARY KEY (event_id, knowledge_id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE knowledge (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(100) NOT NULL, type VARCHAR(50) NOT NULL, name VARCHAR(255) NOT NULL, summary LONGTEXT NOT NULL, description LONGTEXT DEFAULT NULL, aliases JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, book_id INT NOT NULL, INDEX idx_knowledge_book_type (book_id, type), UNIQUE INDEX uniq_knowledge_book_slug (book_id, slug), INDEX IDX_9E072E1D16A2B381 (book_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(50) NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX uniq_user_username (username), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE book ADD CONSTRAINT FK_CBE5A331A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA716A2B381 FOREIGN KEY (book_id) REFERENCES book (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_participant ADD CONSTRAINT FK_7C16B89171F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_participant ADD CONSTRAINT FK_7C16B891E7DC6902 FOREIGN KEY (knowledge_id) REFERENCES knowledge (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE knowledge ADD CONSTRAINT FK_9E072E1D16A2B381 FOREIGN KEY (book_id) REFERENCES book (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE book DROP FOREIGN KEY FK_CBE5A331A76ED395');
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA716A2B381');
        $this->addSql('ALTER TABLE event_participant DROP FOREIGN KEY FK_7C16B89171F7E88B');
        $this->addSql('ALTER TABLE event_participant DROP FOREIGN KEY FK_7C16B891E7DC6902');
        $this->addSql('ALTER TABLE knowledge DROP FOREIGN KEY FK_9E072E1D16A2B381');
        $this->addSql('DROP TABLE book');
        $this->addSql('DROP TABLE event');
        $this->addSql('DROP TABLE event_participant');
        $this->addSql('DROP TABLE knowledge');
        $this->addSql('DROP TABLE user');
    }
}
