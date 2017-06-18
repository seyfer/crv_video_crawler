<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170618070643 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('ALTER TABLE video_links ADD COLUMN position INTEGER DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE video_links ADD COLUMN duration DATETIME DEFAULT NULL');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('CREATE TEMPORARY TABLE __temp__video_links AS SELECT id, course_name, video_name, link, downloaded FROM video_links');
        $this->addSql('DROP TABLE video_links');
        $this->addSql('CREATE TABLE video_links (id INTEGER NOT NULL, course_name VARCHAR(255) NOT NULL, video_name VARCHAR(255) NOT NULL, link VARCHAR(255) NOT NULL, downloaded BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('INSERT INTO video_links (id, course_name, video_name, link, downloaded) SELECT id, course_name, video_name, link, downloaded FROM __temp__video_links');
        $this->addSql('DROP TABLE __temp__video_links');
    }
}
