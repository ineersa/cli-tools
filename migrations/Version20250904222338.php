<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250904222338 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__chat_turn AS SELECT id, chat_id, idx, context, summary_snapshot, finish_reason, prompt_tokens, completion_tokens, total_tokens, type, created_at, updated_at FROM chat_turn');
        $this->addSql('DROP TABLE chat_turn');
        $this->addSql('CREATE TABLE chat_turn (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, chat_id INTEGER NOT NULL, idx INTEGER NOT NULL, context CLOB NOT NULL, summary_snapshot CLOB DEFAULT NULL, finish_reason VARCHAR(255) DEFAULT NULL, prompt_tokens INTEGER DEFAULT NULL, completion_tokens INTEGER DEFAULT NULL, total_tokens INTEGER DEFAULT NULL, type VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, request_id VARCHAR(255) DEFAULT NULL, CONSTRAINT FK_86BC88061A9A7125 FOREIGN KEY (chat_id) REFERENCES chat (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO chat_turn (id, chat_id, idx, context, summary_snapshot, finish_reason, prompt_tokens, completion_tokens, total_tokens, type, created_at, updated_at) SELECT id, chat_id, idx, context, summary_snapshot, finish_reason, prompt_tokens, completion_tokens, total_tokens, type, created_at, updated_at FROM __temp__chat_turn');
        $this->addSql('DROP TABLE __temp__chat_turn');
        $this->addSql('CREATE INDEX chat_turn_idx_idx ON chat_turn (idx)');
        $this->addSql('CREATE INDEX IDX_86BC88061A9A7125 ON chat_turn (chat_id)');
        $this->addSql('CREATE INDEX chat_turn_request_id_idx ON chat_turn (request_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__chat_turn AS SELECT id, chat_id, idx, context, summary_snapshot, finish_reason, prompt_tokens, completion_tokens, total_tokens, type, created_at, updated_at FROM chat_turn');
        $this->addSql('DROP TABLE chat_turn');
        $this->addSql('CREATE TABLE chat_turn (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, chat_id INTEGER NOT NULL, idx INTEGER NOT NULL, context CLOB NOT NULL, summary_snapshot CLOB DEFAULT NULL, finish_reason VARCHAR(255) DEFAULT NULL, prompt_tokens INTEGER DEFAULT NULL, completion_tokens INTEGER DEFAULT NULL, total_tokens INTEGER DEFAULT NULL, type VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_86BC88061A9A7125 FOREIGN KEY (chat_id) REFERENCES chat (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO chat_turn (id, chat_id, idx, context, summary_snapshot, finish_reason, prompt_tokens, completion_tokens, total_tokens, type, created_at, updated_at) SELECT id, chat_id, idx, context, summary_snapshot, finish_reason, prompt_tokens, completion_tokens, total_tokens, type, created_at, updated_at FROM __temp__chat_turn');
        $this->addSql('DROP TABLE __temp__chat_turn');
        $this->addSql('CREATE INDEX IDX_86BC88061A9A7125 ON chat_turn (chat_id)');
        $this->addSql('CREATE INDEX chat_turn_idx_idx ON chat_turn (idx)');
    }
}
