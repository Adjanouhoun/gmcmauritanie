<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261121162228 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create and populate the six GMC service presentations';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE service_offering (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, slug VARCHAR(80) NOT NULL, title VARCHAR(120) NOT NULL, list_title VARCHAR(200) NOT NULL, summary VARCHAR(500) NOT NULL, description CLOB NOT NULL, tagline VARCHAR(120) NOT NULL, version INTEGER DEFAULT 1 NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5C1E718C989D9B62 ON service_offering (slug)');
        $this->addSql('INSERT INTO service_offering (slug, title, list_title, summary, description, tagline, version) VALUES (?, ?, ?, ?, ?, ?, 1)', ['construction-metallique', 'Construction métallique', 'Fabrication & installation – Construction métallique', 'Hangars, plateformes, passerelles, réservoirs, charpentes.', 'Charpentes, passerelles, plateformes, garde-corps, réservoirs, silos. Études, préfabrication atelier, montage sur site et contrôles QA/QC.', 'Fabrication & Installation']);
        $this->addSql('INSERT INTO service_offering (slug, title, list_title, summary, description, tagline, version) VALUES (?, ?, ?, ?, ?, ?, 1)', ['tuyauterie', 'Tuyauterie industrielle', 'Tuyauterie industrielle', 'Fabrication, montage, essais pression – acier & inox, QA/QC.', 'Réseaux process acier/inox, isométriques, supportage, essais pression, traitement anticorrosion, calorifuge.', 'Atelier & Site']);
        $this->addSql('INSERT INTO service_offering (slug, title, list_title, summary, description, tagline, version) VALUES (?, ?, ?, ?, ?, ?, 1)', ['maintenance', 'Maintenance industrielle', 'Maintenance industrielle', 'Sablage, peinture, rubber lining, échafaudage.', 'Arrêts programmés, interventions mécano-soudure, sablage/peinture, revamping d’installations, assistance 24/7.', 'Préventive & Curative']);
        $this->addSql('INSERT INTO service_offering (slug, title, list_title, summary, description, tagline, version) VALUES (?, ?, ?, ?, ?, ?, 1)', ['genie-civil', 'Bâtiment – Génie civil', 'Bâtiment – Génie civil', 'Gros œuvre, dalles industrielles, VRD, fondations.', 'Gros œuvre et second œuvre, dalles industrielles, VRD, fondations et génie civil pour équipements.', 'Construction & Rénovation']);
        $this->addSql('INSERT INTO service_offering (slug, title, list_title, summary, description, tagline, version) VALUES (?, ?, ?, ?, ?, ?, 1)', ['mecanique', 'Mécanique automobile', 'Mécanique automobile', 'Diagnostic, géométrie 3D, FAP/EGR, injecteurs diesel.', 'Parallélisme, géometrie, sécurité routière ....', 'Équipements modernes']);
        $this->addSql('INSERT INTO service_offering (slug, title, list_title, summary, description, tagline, version) VALUES (?, ?, ?, ?, ?, ?, 1)', ['location-hebergement', 'Location & Hébergement', 'Location & Hébergement', 'Appartements et studios meublés, sécurisés à Nouakchott.', 'GMC vous propose également des hébergements dans ses résidences situées à Nouakchott .', 'Résidences équipées']);

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE service_offering');
    }
}
