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
- Formulaire du slider vérifié en session administrateur : les aperçus sont chargés, un déplacement du curseur ordinateur modifie son cadrage sans changer celui du mobile.
- Sélection temporaire d’une image de 147 × 160 px : avertissement immédiat confirmé. Formulaire rechargé sans enregistrer les données de test.
- Absence de débordement horizontal vérifiée à 390 px et 1440 px. La limite effective de 2 Mo et les dimensions des médias s’affichent dans le sélecteur.

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
- Poursuivre la recette des autres rubriques et choisir les images définitives en haute résolution. Le formulaire du slider et ses cadrages ont été contrôlés sur mobile et ordinateur.
- Branche refonte-symfony poussée vers origin (GitHub : Adjanouhoun/gmcmauritanie). Commit fonctionnel : 08468e9. Aucun déploiement effectué.
