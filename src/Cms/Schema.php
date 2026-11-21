<?php
namespace App\Cms;
final class Schema
{
    public const TYPES = ['home'=>'Accueil','slide'=>'Slider','service'=>'Services','training'=>'Formations','about'=>'À propos','about_block'=>'Blocs À propos','project'=>'Réalisations','partner'=>'Partenaires','contact'=>'Contact','settings'=>'Identité du site','menu'=>'Navigation','page'=>'Bandeaux et référencement'];
    public const SINGLE = ['home','about','contact','settings'];
    public static function fields(string $type): array
    {
        // Label, widget, required. Limits are checked server-side.
        return match ($type) {
            'home'=>['intro'=>['Présentation principale','rich',true],'secondary'=>['Deuxième colonne','rich',false],'highlight'=>['Texte mis en valeur','text',false],'button'=>['Bouton de présentation','text',false],'link'=>['Lien du bouton','url',false],'servicesTitle'=>['Titre des services','text',true],'servicesIntro'=>['Introduction des services','textarea',false],'trainingTitle'=>['Titre des formations','text',true],'trainingIntro'=>['Introduction des formations','textarea',false],'partnersTitle'=>['Titre des partenaires','text',false],'slidesVisible'=>['Afficher le slider','checkbox',false],'servicesVisible'=>['Afficher les services','checkbox',false],'trainingVisible'=>['Afficher les formations','checkbox',false],'partnersVisible'=>['Afficher les partenaires','checkbox',false]],
            'slide'=>['image'=>['Image principale','image',true],'mobile'=>['Image mobile (facultative)','image',false],'focusX'=>['Cadrage horizontal — ordinateur','range',false],'focusY'=>['Cadrage vertical — ordinateur','range',false],'mobileFocusX'=>['Cadrage horizontal — mobile','range',false],'mobileFocusY'=>['Cadrage vertical — mobile','range',false],'text'=>['Texte du slide','textarea',false],'button'=>['Texte du bouton','text',false],'link'=>['Lien du bouton','url',false]],
            'service'=>['icon'=>['Icône','image',true],'summary'=>['Résumé sur l’accueil','textarea',true],'tagline'=>['Mention courte','text',false],'body'=>['Contenu de la page détaillée','rich',true],'image'=>['Image principale','image',false],'gallery'=>['Galerie','gallery',false],'featured'=>['Afficher sur l’accueil','checkbox',false],'seoTitle'=>['Titre pour les moteurs de recherche','text',false],'seoDescription'=>['Description pour les moteurs de recherche','textarea',false]],
            'training'=>['shortTitle'=>['Titre court','text',true],'icon'=>['Icône','image',true],'summary'=>['Résumé sur l’accueil','textarea',false],'body'=>['Programme détaillé','rich',true],'objectives'=>['Objectifs (un par ligne)','textarea',true],'audience'=>['Public concerné','textarea',true],'duration'=>['Durée indicative','text',true],'note'=>['Mention complémentaire','textarea',false],'image'=>['Image principale','image',false],'gallery'=>['Galerie','gallery',false],'document'=>['Programme PDF','document',false],'featured'=>['Afficher sur l’accueil','checkbox',false],'seoTitle'=>['Titre pour les moteurs de recherche','text',false],'seoDescription'=>['Description pour les moteurs de recherche','textarea',false]],
            'about'=>['image'=>['Image principale','image',true],'body'=>['Présentation de l’entreprise','rich',true],'gallery'=>['Galerie','gallery',false]],
            'about_block'=>['body'=>['Texte (historique, valeurs, chiffres clés…)','rich',true],'image'=>['Image','image',false]],
            'project'=>['category'=>['Catégorie','text',false],'location'=>['Lieu','text',false],'image'=>['Photo principale','image',true],'body'=>['Description','rich',false],'gallery'=>['Galerie','gallery',false]],
            'partner'=>['image'=>['Logo','image',true],'link'=>['Site du partenaire','url',false]],
            'contact'=>['address'=>['Adresse','textarea',true],'phone'=>['Téléphone principal','phone',true],'phone2'=>['Téléphone secondaire','phone',false],'email'=>['Adresse e-mail publique','email',true],'whatsapp'=>['WhatsApp (format international)','phone',false],'hours'=>['Horaires','textarea',false],'mapQuery'=>['Adresse ou coordonnées de la carte','text',false],'formTitle'=>['Titre du formulaire','text',true],'formIntro'=>['Introduction du formulaire','textarea',false]],
            'settings'=>['logo'=>['Logo principal','image',true],'favicon'=>['Favicon','image',false],'footer'=>['Texte du pied de page','textarea',false],'facebook'=>['Facebook','url',false],'instagram'=>['Instagram','url',false],'linkedin'=>['LinkedIn','url',false],'x'=>['X','url',false]],
            'menu'=>['link'=>['Destination','url',true],'footer'=>['Afficher aussi dans le pied de page','checkbox',false]],
            'page'=>['intro'=>['Introduction','textarea',false],'image'=>['Image de bandeau','image',false],'seoTitle'=>['Titre pour les moteurs de recherche','text',false],'seoDescription'=>['Description pour les moteurs de recherche','textarea',false]],
            default=>throw new \InvalidArgumentException('Rubrique inconnue'),
        };
    }
}
