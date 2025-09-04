<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250904212142 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE chat (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, project_id INTEGER NOT NULL, last_turn_id INTEGER DEFAULT NULL, title VARCHAR(255) NOT NULL, summary CLOB DEFAULT NULL, mode VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_659DF2AA166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_659DF2AA79C3A4C1 FOREIGN KEY (last_turn_id) REFERENCES chat_turn (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_659DF2AA166D1F9C ON chat (project_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_659DF2AA79C3A4C1 ON chat (last_turn_id)');
        $this->addSql('CREATE INDEX chat_status_mode_idx ON chat (status, mode)');
        $this->addSql('CREATE TABLE chat_turn (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, chat_id INTEGER NOT NULL, idx INTEGER NOT NULL, context CLOB NOT NULL, summary_snapshot CLOB DEFAULT NULL, finish_reason VARCHAR(255) DEFAULT NULL, prompt_tokens INTEGER DEFAULT NULL, completion_tokens INTEGER DEFAULT NULL, total_tokens INTEGER DEFAULT NULL, type VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_86BC88061A9A7125 FOREIGN KEY (chat_id) REFERENCES chat (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_86BC88061A9A7125 ON chat_turn (chat_id)');
        $this->addSql('CREATE INDEX chat_turn_idx_idx ON chat_turn (idx)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE chat');
        $this->addSql('DROP TABLE chat_turn');
    }
}
