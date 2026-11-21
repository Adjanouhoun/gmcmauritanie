# État d’avancement — 21 novembre 2026

## Réalisé

- Migration du site Porto vers Symfony 7.4, avec conservation des ressources du thème et des anciennes adresses utiles.
- Administration authentifiée de l’accueil, du slider, des services, des formations, de la présentation, des réalisations, des partenaires, des contacts, des menus et du référencement.
- Création, publication, archivage et aperçu des contenus ; protection CSRF et détection des modifications concurrentes.
- Médiathèque avec import direct depuis les formulaires, images et programmes PDF.
- Conservation des originaux et génération de variantes WebP : logos, icônes, photos et slides. Avertissements de résolution et cadrage séparé ordinateur/mobile.
- Documentation d’utilisation : docs/ADMINISTRATION.md.

## Validation

- Dernière suite : 23 tests réussis, 147 assertions.
- Validation Twig et injection de dépendances réussies ; syntaxe JavaScript vérifiée.
- Affichage public et chargement des images vérifiés dans le navigateur.
- L’administration a été vérifiée par les tests automatisés ; la dernière session navigateur était déconnectée.

## Reprise du travail

- Dépôt : /Users/amadouadjanouhoun/Projects/gmcmauritanie ; branche refonte-symfony.
- Site local : http://127.0.0.1:8000 ; administration : /admin.
- Base SQLite : var/data/gmc.db ; originaux importés : var/media ; variantes : var/media/variants.
- Tests : php bin/phpunit. Vérifications : php bin/console lint:twig templates et php bin/console lint:container.
- Les données et médias importés ne sont pas versionnés : les sauvegardes locales se trouvent dans var/backups.

## Points restant avant une mise en production

- Configurer et vérifier le transport SMTP et les destinataires du formulaire de contact. Le transport local est désactivé.
- Remplacer les anciens identifiants SMTP présents dans le script historique send-mail.php, exclu de Git.
- Configurer les secrets de production, HTTPS, les permissions, les sauvegardes et GD/WebP (EXIF recommandé).
- Vérifier visuellement les formulaires connectés et le cadrage sur plusieurs tailles d’écran avec les contenus définitifs.
- Aucun déploiement ni envoi vers un dépôt distant effectué à cette étape.
