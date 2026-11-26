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
- **Menu du site** : liens du menu et du pied de page, ordre et publication.
- Dans **Services, Formations, Réalisations, À propos et Contact**, utilisez l’onglet **Présentation de la page** pour les titres, introductions et images. **Accueil → Titre et options** gère le titre de l’onglet du navigateur. Les champs de référencement sont dans **Options avancées**, fermées par défaut.

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

Le serveur web doit avoir `public/` comme racine. Les secrets SMTP restent dans la configuration d’environnement. L’e-mail affiché dans Contact est une coordonnée publique ; l’expéditeur et le destinataire du formulaire se règlent avec `MAILER_FROM` et `MAILER_TO` dans la configuration protégée. Le transport est désactivé par défaut ; voir la procédure LWS ci-dessous.

Vérifications : `php bin/phpunit`, `php bin/console doctrine:schema:validate`, `php bin/console lint:container`.

La sauvegarde antérieure à cette refonte est dans `var/backups/before-complete-cms-20261121/` (code et base SQLite). Restaurer le code et la base correspondante ensemble si un retour arrière est nécessaire.


### Dimensions et optimisation des images

Les originaux importés sont conservés sans modification. Le site génère des copies WebP en cache : logos limités à 480 × 320 px, icônes centrées dans un cadre transparent de 256 × 256 px, photos limitées à 1920 px et versions mobiles de 768 px. Les proportions et la transparence sont conservées ; les petites images ne sont pas agrandies lors de la génération. Les SVG existants restent vectoriels. Si le traitement dépasse la mémoire disponible, l’original est servi.

Pour les slides, privilégiez au moins 1920 × 700 px sur ordinateur et 780 × 940 px sur mobile. Une résolution insuffisante déclenche un avertissement, sans empêcher l’enregistrement. Les curseurs de cadrage horizontal et vertical (0 à 100 %) permettent de déplacer le sujet séparément sur ordinateur et mobile. L’aperçu est indicatif : la découpe dépend de l’écran. Sans image mobile, l’image principale est utilisée. Le navigateur peut agrandir une petite image pour remplir le slider : elle pourra donc paraître floue.

L’import reste limité aux JPG, PNG, WebP et PDF, à 30 millions de pixels pour les images et à la limite de poids affichée (selon PHP, au maximum 12 Mo).

Le serveur doit disposer de GD avec prise en charge WebP pour générer les variantes ; EXIF permet de redresser les photos JPEG. En l’absence de GD/WebP, le site sert les originaux.


### Portfolio des réalisations

La rubrique Réalisations propose une couverture avec cadrage, une activité, un lieu, une année, un client (uniquement si cette information peut être rendue publique), un résumé, le contexte, les travaux et les résultats. Les sections non renseignées sont masquées. Le bandeau de la liste (titre, introduction et image) se modifie dans Réalisations → Présentation de la page.

Les activités se créent et se renomment dans Catégories de réalisations. Le renommage s’applique aux projets associés. Une catégorie archivée est masquée dans les filtres et les fiches, sans dépublier ses projets. Seules les activités publiées utilisées par au moins un projet publié apparaissent dans les filtres.

Cochez « Mettre ce projet à la une » puis publiez-le pour remplacer la sélection précédente. Un brouillon ne remplace pas le projet à la une. Sans sélection explicite, le premier projet publié dans l’ordre d’affichage est présenté.

Ajoutez les photos du projet dans la galerie et utilisez Avancer/Reculer pour organiser leur ordre ; enregistrez ensuite le formulaire. Les visiteurs peuvent agrandir les photos, naviguer avec les boutons ou les flèches du clavier et fermer avec Échap. Les projets similaires sont choisis parmi les projets publiés de la même activité.

Pour une installation à partir des anciens contenus, après `app:cms:import`, exécutez `php bin/console app:cms:organize-projects`. Cette commande peut être rejouée : elle rattache uniquement les projets sans catégorie structurée et conserve les textes, lieux et médias. Faites une sauvegarde de la base avant la première exécution.


## Navigation — 25 novembre 2026

En-tête compact et fixe, bouton Contactez-nous, navigation mobile dépliable avec fermeture Échap, lien d’évitement et repères actifs conservés sur les fiches. Services propose les prestations publiées ; Formations reste un accès direct au catalogue.

Dans Administration → Menu du site, le titre sert notamment au pied de page ; le nouveau champ « Libellé dans le menu principal » permet un intitulé différent dans l’en-tête. L’ordre reste modifiable. Le sous-menu est associé à la destination /services et le bouton de contact à /contact.

Validation : 26 tests, 189 assertions ; menu mobile et sous-menu contrôlés avec Échap, navigation vers une fiche et repère Services vérifiés. Sauvegarde des réglages avant modification dans var/backups/before-navigation-*.db. Le premier écran conserve encore des titres de test et devra être finalisé avec les vrais contenus.


## Pied de page — 25 novembre 2026

Pied de page harmonisé avec la navigation : appel à contact, identité, navigation, services publiés et coordonnées. Sur mobile, les colonnes se superposent sans débordement. Les titres, la présentation, le bouton et sa destination sont modifiables dans Identité du site. L’appel à contact peut être masqué. Dans Menu du site, les liens conservent leur option « Afficher aussi dans le pied de page ».

Téléphones, adresse, e-mail, WhatsApp et horaires proviennent de Contact ; réseaux sociaux et liens légaux de Identité du site. Les éléments facultatifs non renseignés sont masqués. Les liens légaux ne créent pas de pages : renseigner uniquement des destinations existantes.

Validation : 27 tests réussis, 202 assertions ; aperçu ordinateur et mobile à 390 px vérifié.


## Guide utilisateur intégré

Après connexion, ouvrez **Guide d’utilisation** dans le menu ou **Besoin d’aide ?** sur le tableau de bord. Adresse : `/admin/guide`. Le sommaire conduit aux procédures et les liens ouvrent les rubriques correspondantes. Le bouton **Imprimer / enregistrer en PDF** ouvre la boîte d’impression du navigateur ; choisissez une imprimante ou l’option PDF disponible sur votre appareil.


### Organisation simplifiée des pages

Le menu ne propose plus une rubrique séparée de bandeaux. Chaque rubrique concernée regroupe ses contenus et sa présentation dans deux onglets. Les anciens accès techniques restent compatibles pour préserver les favoris. Les réglages existants sont conservés.

Les champs de référencement sont facultatifs : pour retrouver les valeurs automatiques, effacez les valeurs personnalisées puis enregistrez. Le titre et l’introduction disponibles sont utilisés par défaut. En cas d’erreur de validation, les options avancées s’ouvrent pour faciliter la correction.


## Pages libres et destinations du menu — 26 novembre 2026

La rubrique Pages libres permet de créer des pages supplémentaires à l’adresse `/pages/{slug}` : titre, introduction, image facultative, texte mis en forme et référencement facultatif. Brouillon, aperçu privé, publication et archivage sont disponibles. L’adresse reste stable après création. Aucun lien de menu n’est créé automatiquement.

Dans Menu du site, choisir une destination publiée dans la liste (rubrique fixe, service, formation, réalisation ou page libre), ou Site externe avec une URL HTTP(S) complète. L’affichage dans le menu principal et le pied de page est indépendant. Les liens vers un contenu non publié sont masqués automatiquement. Les destinations existantes reconnues restent compatibles sans migration de données. Les adresses internes arbitraires ne sont plus acceptées.

Validation : 35 tests, 657 assertions ; 45 modèles Twig et conteneur Symfony valides. Le parcours création, aperçu, publication, pied de page, archivage et validation des destinations est couvert avec une base isolée. Aucune modification des contenus réels et aucun déploiement. Vérification visuelle de l’administration connectée non effectuée.


## Configuration du formulaire avec LWS

Dans le dossier du projet, lancer `php bin/console app:configure-lws-mail` dans un terminal interactif. Serveur confirmé : `mail28.lwspanel.com`, SMTP SSL/TLS port 465. La commande demande l’adresse de la boîte (identifiant et expéditeur), l’adresse destinataire, puis le mot de passe avec saisie masquée obligatoire. Elle encode les caractères spéciaux, conserve les autres variables et écrit `.env.local` avec permissions 600. Elle n’envoie aucun message et ne doit pas être exécutée avec un mot de passe en argument.

Le fichier `.env.mailer.example` documente les trois variables. Ne jamais y écrire de secret réel. `.env.local` et les sauvegardes privées sont exclus de Git. Une variable d’environnement définie par l’hébergeur reste prioritaire : régler également l’environnement de production lors du déploiement. Le fichier local n’est pas transmis par Git.

L’adresse publique de Contact reste une coordonnée éditoriale ; elle ne modifie ni l’authentification SMTP ni le destinataire technique. Le visiteur est placé dans Reply-To, jamais utilisé comme expéditeur SMTP. Après configuration, soumettre un message identifiable dans le formulaire et vérifier sa présence dans le webmail LWS (y compris les indésirables). Un succès indique l’acceptation par le transport, pas à lui seul la livraison en boîte de réception.

Paramètres vérifiés dans la [documentation officielle LWS](https://aide.lws.fr/base/Hebergement-web-mutualise/Gestion-des-emails/Quels-ports-utiliser-pour-la-configuration-dune-adresse-email). Les anciens identifiants du script historique doivent être remplacés ; ce script reste exclu de Git.
