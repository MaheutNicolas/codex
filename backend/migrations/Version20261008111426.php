<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261008111426 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Full-text indexes for the search of the AI';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE FULLTEXT INDEX ft_event_search ON event (title, summary, detail)');
        $this->addSql('CREATE FULLTEXT INDEX ft_knowledge_search ON knowledge (name, summary, description)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX ft_event_search ON event');
        $this->addSql('DROP INDEX ft_knowledge_search ON knowledge');
    }
}
