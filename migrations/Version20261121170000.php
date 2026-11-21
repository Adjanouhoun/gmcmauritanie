<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261121170000 extends AbstractMigration
{
    public function getDescription(): string {return 'Add full website CMS and media library; preserve legacy content tables';}
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cms_entry (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(30) NOT NULL, slug VARCHAR(160) NOT NULL, title VARCHAR(200) NOT NULL, status VARCHAR(20) NOT NULL, position INTEGER NOT NULL, data CLOB NOT NULL, version INTEGER DEFAULT 1 NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX uniq_cms_type_slug ON cms_entry (type, slug)');
        $this->addSql('CREATE TABLE media (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, path VARCHAR(255) NOT NULL, mime VARCHAR(100) NOT NULL, alt VARCHAR(300) NOT NULL, uploaded BOOLEAN NOT NULL, archived BOOLEAN NOT NULL)');
    }
    public function down(Schema $schema): void {$this->addSql('DROP TABLE cms_entry');$this->addSql('DROP TABLE media');}
}
