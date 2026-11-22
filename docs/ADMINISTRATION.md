# Administration du site GMC

Ouvrir `/admin` et se connecter avec le compte administrateur existant.
Pour créer un compte supplémentaire, lancer `php bin/console app:create-admin` dans le projet. Le mot de passe est demandé en saisie masquée.

## Gérer les contenus

- **Accueil** : présentation, deuxième colonne, texte mis en valeur, bouton, titres des sections et visibilité des blocs.
- **Slider** : ajouter des diapositives, choisir une image principale et éventuellement une image mobile, modifier titre, texte, bouton et destination.
- **Services / Formations** : cliquer sur **Ajouter**, renseigner les champs et choisir une icône dans la médiathèque. Cocher **Afficher sur l’accueil** pour faire apparaître une carte. La page détaillée est créée automatiquement.
- **À propos** : image et texte principal. **Blocs À propos** contient les sections additionnelles (historique, valeurs, chiffres clés…).
- **Réalisations / Partenaires** : création de projets et galeries, gestion des logos et liens.
- **Contact** : adresse, téléphones, e-mail public, WhatsApp, horaires, emplacement de la carte et présentation du formulaire.
- **Identité du site** : logo, favicon, pied de page et réseaux sociaux.
- **Navigation** : liens du menu et du pied de page, ordre et publication.
- **Bandeaux et référencement** : titres de pages, introductions, images et métadonnées.

## Enregistrer, prévisualiser, publier

Un nouveau contenu est un **Brouillon**. Après enregistrement, **Voir l’aperçu enregistré** permet de le consulter dans une page privée accessible uniquement aux administrateurs. Choisir **Publié**, puis enregistrer pour le rendre visible.

Modifier un contenu déjà publié le met immédiatement à jour sur le site. L’aperçu montre la dernière version enregistrée, pas les modifications en cours de saisie.

Choisir **Archivé** pour retirer un élément sans l’effacer. Choisir à nouveau **Publié** pour le restaurer. Les pages générales (Accueil, Contact, Identité…) restent publiées ; les cases de visibilité de l’accueil permettent de masquer ses sections.

Le champ **Ordre d’affichage** contrôle la position : les nombres les plus petits apparaissent d’abord. À égalité, les contenus les plus anciens passent d’abord. Une adresse existante reste stable pour préserver les liens entrants.

## Médiathèque

Importer des fichiers JPG, PNG, WebP ou PDF. La limite affichée tient compte de la configuration PHP du serveur. Les images sont limitées à 30 millions de pixels. Les SVG existants du thème Porto sont disponibles dans la bibliothèque ; les nouveaux imports SVG ne sont pas acceptés.

Ajouter une description utile à chaque image. Dans un formulaire de contenu, utiliser **Choisir dans la médiathèque**, rechercher puis sélectionner une vignette. Plusieurs images peuvent être ajoutées aux galeries. Les programmes PDF sont proposés en téléchargement.

**Masquer du sélecteur** retire un média du choix de nouveaux contenus sans casser les images déjà affichées. Décocher cette option pour le rendre à nouveau disponible.

Le sélecteur propose un import direct : choisir un fichier, saisir sa description et cliquer sur **Importer**. Le média est ajouté et sélectionné sans perdre les textes du formulaire. La bibliothèque est actualisée à chaque ouverture, y compris après un import dans un autre onglet. Les fichiers trop volumineux sont signalés avant l’envoi lorsque leur taille dépasse la limite annoncée.

## Maintenance technique

Le site public utilise les templates `templates/cms` et les données des tables `cms_entry` et `media`. Les anciennes tables sont conservées pour la migration et la sauvegarde, mais ne pilotent plus le site public. Les anciens templates sont conservés comme référence.

Déploiement après installation des dépendances :

```sh
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:cms:import
php bin/console cache:clear
```

L’import initial n’écrase pas les contenus déjà présents dans le CMS. Il reprend les images existantes dans `public/img` et les contenus précédemment administrés dans SQLite. Les fichiers importés ultérieurement sont stockés dans `var/media` et servis par `/media/{id}`. Sauvegarder **la base SQLite et `var/media` ensemble**, en plus des ressources `public/img`.

Le serveur web doit avoir `public/` comme racine. Les secrets SMTP restent dans la configuration d’environnement. L’e-mail affiché dans Contact est une coordonnée publique ; l’expéditeur et le destinataire du traitement mail existant sont toujours configurés dans `ContactController`. Le transport SMTP est actuellement désactivé et doit être configuré avant l’envoi réel.

Vérifications : `php bin/phpunit`, `php bin/console doctrine:schema:validate`, `php bin/console lint:container`.

La sauvegarde antérieure à cette refonte est dans `var/backups/before-complete-cms-20261121/` (code et base SQLite). Restaurer le code et la base correspondante ensemble si un retour arrière est nécessaire.


### Dimensions et optimisation des images

Les originaux importés sont conservés sans modification. Le site génère des copies WebP en cache : logos limités à 480 × 320 px, icônes centrées dans un cadre transparent de 256 × 256 px, photos limitées à 1920 px et versions mobiles de 768 px. Les proportions et la transparence sont conservées ; les petites images ne sont pas agrandies lors de la génération. Les SVG existants restent vectoriels. Si le traitement dépasse la mémoire disponible, l’original est servi.

Pour les slides, privilégiez au moins 1920 × 700 px sur ordinateur et 780 × 940 px sur mobile. Une résolution insuffisante déclenche un avertissement, sans empêcher l’enregistrement. Les curseurs de cadrage horizontal et vertical (0 à 100 %) permettent de déplacer le sujet séparément sur ordinateur et mobile. L’aperçu est indicatif : la découpe dépend de l’écran. Sans image mobile, l’image principale est utilisée. Le navigateur peut agrandir une petite image pour remplir le slider : elle pourra donc paraître floue.

L’import reste limité aux JPG, PNG, WebP et PDF, à 30 millions de pixels pour les images et à la limite de poids affichée (selon PHP, au maximum 12 Mo).

Le serveur doit disposer de GD avec prise en charge WebP pour générer les variantes ; EXIF permet de redresser les photos JPEG. En l’absence de GD/WebP, le site sert les originaux.


### Portfolio des réalisations

La rubrique Réalisations propose une couverture avec cadrage, une activité, un lieu, une année, un client (uniquement si cette information peut être rendue publique), un résumé, le contexte, les travaux et les résultats. Les sections non renseignées sont masquées. Le bandeau de la liste (titre, introduction et image) se modifie dans Bandeaux et référencement → Réalisations.

Les activités se créent et se renomment dans Catégories de réalisations. Le renommage s’applique aux projets associés. Une catégorie archivée est masquée dans les filtres et les fiches, sans dépublier ses projets. Seules les activités publiées utilisées par au moins un projet publié apparaissent dans les filtres.

Cochez « Mettre ce projet à la une » puis publiez-le pour remplacer la sélection précédente. Un brouillon ne remplace pas le projet à la une. Sans sélection explicite, le premier projet publié dans l’ordre d’affichage est présenté.

Ajoutez les photos du projet dans la galerie et utilisez Avancer/Reculer pour organiser leur ordre ; enregistrez ensuite le formulaire. Les visiteurs peuvent agrandir les photos, naviguer avec les boutons ou les flèches du clavier et fermer avec Échap. Les projets similaires sont choisis parmi les projets publiés de la même activité.

Pour une installation à partir des anciens contenus, après `app:cms:import`, exécutez `php bin/console app:cms:organize-projects`. Cette commande peut être rejouée : elle rattache uniquement les projets sans catégorie structurée et conserve les textes, lieux et médias. Faites une sauvegarde de la base avant la première exécution.
