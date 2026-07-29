# Tutoriel : Mise en place du Dashboard Coach Front-End

**Projet :** RideMaster
**Date :** 11 Février 2026
**Stack :** WordPress + Elementor + JetEngine + JetFormBuilder

---

## Table des matières

1. [Prérequis - Plugins nécessaires](#1-prérequis---plugins-nécessaires)
2. [Créer les rôles WordPress "Coach" et "Rider"](#2-créer-les-rôles-wordpress-coach-et-rider)
3. [Ajouter les meta fields manquants](#3-ajouter-les-meta-fields-manquants)
4. [Activer et configurer JetEngine Profile Builder](#4-activer-et-configurer-jetengine-profile-builder)
5. [Créer le formulaire de profil coach](#5-créer-le-formulaire-de-profil-coach-jetformbuilder)
6. [Créer le formulaire de création de camp](#6-créer-le-formulaire-de-création-de-camp-jetformbuilder)
7. [Designer les pages du dashboard avec Elementor](#7-designer-les-pages-du-dashboard-avec-elementor)
8. [Créer la page d'inscription et connexion coach](#8-créer-la-page-dinscription-et-connexion-coach)
9. [Configurer la validation admin du coach](#9-configurer-la-validation-admin-du-coach)
10. [Bloquer l'accès wp-admin et redirections](#10-bloquer-laccès-wp-admin-et-redirections)
11. [Configurer les restrictions de médias](#11-configurer-les-restrictions-de-médias)
12. [Tests et vérification](#12-tests-et-vérification)

---

## 1. Prérequis - Plugins nécessaires

### Plugins déjà installés (à vérifier)
- **Elementor Pro** - Page builder
- **JetEngine** - CPT, meta fields, relations, taxonomies
- **WooCommerce** - Déjà présent (page My Account)

### Plugins à installer / activer
- **JetFormBuilder** (Crocoblock) - Formulaires front-end pour soumettre/éditer des posts
  - Aller dans **Plugins > Ajouter** et chercher "JetFormBuilder" ou l'installer depuis le dashboard Crocoblock
  - C'est un plugin gratuit disponible sur wordpress.org
- **JetEngine Profile Builder** - Module intégré à JetEngine (pas un plugin séparé)
  - Aller dans **JetEngine > Modules** et activer le module **"Profile Builder"**

### Vérifier les modules JetEngine activés
Aller dans **JetEngine > JetEngine > Modules** et s'assurer que ces modules sont activés :
- [x] Profile Builder
- [x] Custom Content Types (si pas déjà fait)
- [x] Relations
- [x] Forms (JetEngine Forms - optionnel si on utilise JetFormBuilder à la place)

---

## 2. Créer les rôles WordPress "Coach" et "Rider"

> **Déjà fait** : Les rôles ont déjà été créés via le plugin **Members**.
>
> **Slugs des rôles (important pour le PHP) :** Le slug du rôle Coach est `coach_role` (pas `coach`). Tous les snippets PHP de ce tutoriel utilisent ce slug. Vérifier dans **Members > Rôles** si besoin.

### Vérifier les capacités du rôle Coach

Aller dans **Members > Rôles > Coach** et s'assurer que les capacités suivantes sont cochées :
- `read`
- `upload_files` (pour uploader des images)
- `edit_posts` (nécessaire pour créer des camps via JetEngine)
- `delete_posts` (pour supprimer ses propres camps)
- `publish_posts` (pour publier ses camps - sera contrôlé par le statut coach)

### Architecture des deux dashboards

Les deux rôles ont des dashboards séparés, chacun utilisant l'outil le plus adapté :

| | Coach | Rider |
|---|---|---|
| **Dashboard** | JetEngine Profile Builder | WooCommerce My Account |
| **URL** | `/coach-dashboard/` | `/my-account/` |
| **Fonctions** | Profil public, gestion des camps, spots | Réservations, paiements, infos compte |
| **Pourquoi** | Contenu custom (CPT) → JetEngine est fait pour ça | E-commerce (commandes) → WooCommerce est fait pour ça |

> **Pourquoi cette séparation ?** JetEngine Profile Builder n'a qu'une seule "Account Page" globale. Plutôt que de tout mélanger avec des sous-pages filtrées par rôle, on utilise chaque outil pour ce qu'il fait le mieux. C'est le même principe qu'Airbnb : l'espace "hôte" (coach) est séparé de l'espace "voyageur" (rider).

---

## 3. Ajouter les meta fields manquants

La page de profil coach affiche des informations qui ne sont pas encore dans les meta fields JetEngine. Il faut les ajouter.

### 3.1 Ajouter les meta fields au CPT Coach

Aller dans **JetEngine > Meta Boxes** (ou **Post Types > Coach > Meta Fields** selon votre configuration).

#### Meta fields à ajouter (en plus des existants)

| Nom du champ | Slug | Type | Notes |
|---|---|---|---|
| Photo de profil | `coach_profile_photo` | Media | Image unique |
| Photo de couverture | `coach_cover_photo` | Media | Image unique |
| Lien Instagram | `coach_instagram` | Text | URL complète |
| Lien YouTube | `coach_youtube` | Text | URL complète |
| Lien Website | `coach_website` | Text | URL complète |
| Localisation | `coach_location` | Text | Ex: "Tarifa, Spain" |
| Spécialités | `coach_specialties` | Textarea | Ex: "Big Air, Freestyle, Wave riding" |

#### Comment ajouter un meta field dans JetEngine :

1. Aller dans **JetEngine > Post Types**
2. Cliquer sur **"Coach"** pour l'éditer
3. Dans la section **"Meta Fields"**, cliquer sur **"+ New Meta Field"**
4. Pour chaque champ ci-dessus :
   - **Label :** Le nom du champ (ex: "Photo de profil")
   - **Name/ID :** Le slug (ex: `coach_profile_photo`)
   - **Object type :** Le type (ex: `Media` pour les photos, `Text` pour les URLs)
5. Cliquer sur **"Update Post Type"** pour sauvegarder

### 3.2 Comprendre la structure Camp = Produit WooCommerce

> **Changement important :** Les camps ne sont PAS un Custom Post Type JetEngine dédié. Un camp est un **produit WooCommerce** (`post_type: product`) avec des meta fields JetEngine supplémentaires. Il n'y a pas de CPT `camp`.

#### Champs gérés nativement par WooCommerce (pas de meta custom)

| Donnée | Meta key WooCommerce | Comment c'est géré |
|---|---|---|
| Prix | `_regular_price` + `_price` | Le formulaire mappe vers `_regular_price` ; un hook PHP copie vers `_price` |
| Places max (stock) | `_stock` | Le formulaire mappe directement ; hook PHP active `_manage_stock` = yes |
| Statut du stock | `_stock_status` | Hook PHP met `instock` |
| Image principale | `_thumbnail_id` | Champ média du formulaire → Featured Image |
| Galerie | `_product_image_gallery` | Champ média du formulaire → IDs séparés par des virgules |
| Devise | _(réglage global WooCommerce)_ | Pas de champ par produit. Configurer dans **WooCommerce > Réglages > Général > Devise** |

#### Meta Box JetEngine "Camp Fields" sur les Products

Aller dans **JetEngine > Meta Boxes** et vérifier (ou créer) un meta box avec ces réglages :
- **Meta Box Title :** `Camp Fields`
- **Meta Box for :** Post
- **Enable For Post Types :** `Products`

Le meta box contient **4 champs** :

| Label | Slug | Type | Sous-champs | Notes |
|---|---|---|---|---|
| Full date (from/to) | `full_date` | Advanced Date | _(from/to intégrés)_ | Stocke date de début et fin au format JetEngine |
| Included | `camp_included` | Repeater | `included_in_the_camp` (text) | Chaque item = un texte (ex: "Coaching", "Équipement") |
| Not Included | `camp_not_included` | Repeater | `not_included_in_the_camp` (text) | Chaque item = un texte (ex: "Vols", "Repas") |
| Schedule | `camp_schedule` | Textarea | — | Programme / planning du camp |

> **Note sur `camp_schedule` :** Ce champ doit être ajouté au meta box "Camp Fields" s'il n'existe pas encore. Aller dans le meta box, cliquer sur **"+ New Meta Field"**, Label : "Schedule", Name : `camp_schedule`, Type : Textarea.

> **Note sur les dates :** Le formulaire front-end utilise deux champs date séparés (start/end) pour une meilleure UX. Un hook PHP (voir Section 6.3) fusionne les deux valeurs dans le champ `full_date` au format JetEngine après soumission.

### 3.3 Vérifier les taxonomies existantes

Les taxonomies suivantes doivent exister :
- **Sport** (`sport`) - liée à Coach, **Products** (camps), et Spot
- **Level** (`level`) - liée à **Products** (camps) et Spot
- **Language** (`language`) - liée à Coach
- **Coach Status** (`coach-status`) - liée à Coach
  - Termes : `pending` (En attente), `verified` (Vérifié), `suspended` (Suspendu)
- **Product Category** (`product_cat`) - WooCommerce natif. Créer un terme **"Camp"** (slug: `camp`) dans **Products > Categories**. Ce terme permet de distinguer les produits-camps des autres produits WooCommerce.

> **`camp-status` n'est plus nécessaire.** WooCommerce utilise les statuts natifs WordPress (`draft`/`publish`) pour contrôler la visibilité des camps. Le contrôle se fait via le statut du coach (voir Section 9). Ignorer ou supprimer l'ancienne taxonomie `camp-status` si elle existe.

> **Vérifier que les taxonomies sport et level sont assignées aux Products :** Aller dans **JetEngine > Taxonomies > Sport** (et **Level**), et dans le réglage "Post Type", ajouter `product` (en plus de `coach` et `spot`).

> **Si la taxonomie `coach-status` n'existe pas :** Créer une nouvelle taxonomie dans **JetEngine > Taxonomies > Add New**, avec le slug `coach-status` et l'associer au CPT Coach. Ajouter les termes : "Pending", "Verified", "Suspended".

---

## 4. Activer et configurer JetEngine Profile Builder

Le Profile Builder de JetEngine permet de créer un espace utilisateur front-end avec des sous-pages, exactement comme un dashboard e-commerce.

> **Important : Profile Builder = Coach uniquement.** JetEngine Profile Builder n'a qu'une seule "Account Page" globale. On l'utilise exclusivement pour le dashboard Coach. Le dashboard Rider utilisera la page WooCommerce My Account (`/my-account/`) qui est déjà en place et gère nativement les commandes, paiements et infos du compte client. Les deux systèmes coexistent de manière indépendante.

### 4.1 Activer le module

1. Aller dans **JetEngine > JetEngine > Modules**
2. Trouver **"Profile Builder"** dans la liste
3. L'activer (toggle ON)
4. Sauvegarder

### 4.2 Configurer le Profile Builder

1. Aller dans **JetEngine > Profile Builder** (nouveau menu qui apparaît après activation)
2. Configurer les onglets suivants :

#### Onglet "Pages" (General Settings)

| Réglage | Valeur | Notes |
|---|---|---|
| **Account Page** | `Coach Dashboard` | Page créée au préalable (slug: `coach-dashboard`) |
| **Users page** | OFF | Pas besoin d'une page listant tous les utilisateurs |
| **Single user page** | OFF | La page publique du coach utilise déjà le single template du CPT Coach |
| **Template mode** | `Rewrite` | Le contenu de la page Account est remplacé par le template de la sous-page active |
| **Use page content** | OFF | On utilise les templates Elementor, pas le contenu par défaut de la page |
| **Hide admin bar** | ON | Masque la barre d'admin WordPress pour les non-admins (coachs, riders) |
| **Restrict admin area access** | ON | Bloque l'accès à `/wp-admin/` pour les rôles non autorisés |
| **Select Roles** (accès admin) | `Shop manager` | Seuls Administrator (toujours) et Shop manager peuvent accéder à wp-admin. Les coachs et riders sont bloqués. |

> **Hide admin bar + Restrict admin area access** remplacent les snippets PHP qu'on aurait dû écrire manuellement. Le Profile Builder gère ça directement. On n'aura donc PAS besoin des snippets de l'étape 10.1 (bloquer wp-admin) et 10.2 (masquer barre admin).

> **Et les Riders ?** Les riders continuent d'utiliser la page `/my-account/` de WooCommerce pour se connecter, voir leurs réservations (commandes) et gérer leur compte. Pas besoin de toucher à cette page ici. On pourra la customiser plus tard avec Elementor si nécessaire.

#### Onglet "Account Page" - Configuration globale + sous-pages

**Réglages globaux de l'onglet Account Page :**

| Réglage | Valeur | Notes |
|---|---|---|
| **Account Page Title** | `%pagetitle% %sep% %sitename%` | Format SEO par défaut, OK |
| **Account Page Description** | _(vide)_ | Optionnel |
| **For not authorized users** | `Redirect to page` (si disponible) | Si cette option n'est pas dispo, laisser "Redirect to default WordPress login page" et le snippet PHP de l'étape 10 gérera la redirection vers `/coach-login/` |
| **For users with restricted access** | `Redirect to page` | Pour les utilisateurs connectés qui n'ont pas le bon rôle |
| **Redirect URL** | `/coach-login/` | URL de la page de connexion coach (créée à l'étape 8) |

**Sous-pages du dashboard coach :**

Cliquer sur **"+ Add New Subpage"** pour créer les sous-pages suivantes. Toutes sont réservées au rôle `coach` :

**Sous-page 1 : My profile**
- **Title :** `My profile`
- **Slug :** `profile`
- **Template :** Laisser vide pour l'instant (on créera le template Elementor à l'étape 7)
- **Hide from menu :** OFF
- **Available for the user role :** `Coach Role`

**Sous-page 2 : My Camps**
- **Title :** `My Camps`
- **Slug :** `my-camps`
- **Template :** Laisser vide (à créer à l'étape 7)
- **Hide from menu :** OFF
- **Available for the user role :** `Coach Role`

**Sous-page 3 : Create a Camp**
- **Title :** `Create a Camp`
- **Slug :** `create-camp`
- **Template :** Laisser vide (à créer à l'étape 7)
- **Hide from menu :** OFF
- **Available for the user role :** `Coach Role`

3. Cliquer sur **"Save"** pour sauvegarder tous les réglages

---

## 5. Créer le formulaire de profil coach (JetFormBuilder)

Ce formulaire permet au coach de modifier ses informations de profil depuis le dashboard front-end. Il doit :
- **Mettre à jour** le post Coach existant (pas en créer un nouveau)
- **Pré-remplir** les champs avec les données actuelles
- **Générer le titre** du post automatiquement (Prénom + Nom)

### 5.1 Créer le formulaire (import JSON)

> **Import rapide :** Un fichier JSON prêt à l'import est disponible dans `jetformbuilder-imports/coach-profile-form.json`. Aller dans **JetFormBuilder > Import**, sélectionner ce fichier et importer. Le formulaire sera créé avec tous les champs pré-configurés.

Si tu préfères créer manuellement, aller dans **JetFormBuilder > Add New** avec le titre **"Coach Profile Form"** et ajouter les champs décrits en 5.2.

### 5.2 Champs du formulaire

Le formulaire contient les champs suivants (déjà présents si importé via JSON) :

#### Section : Informations personnelles

1. **Text Field** - Prénom
   - Label : `First Name`
   - Name : `coach_first_name`
   - Required : Oui

2. **Text Field** - Nom
   - Label : `Last Name`
   - Name : `coach_last_name`
   - Required : Oui

3. **Textarea Field** - Bio
   - Label : `About You`
   - Name : `coach_bio`

4. **Text Field** - Localisation
   - Label : `Location`
   - Name : `coach_location`

5. **Number Field** - Années d'expérience
   - Label : `Years of Experience`
   - Name : `coach_years_experience`
   - Min : 0, Max : 50

6. **Textarea Field** - Certifications
   - Label : `Certifications`
   - Name : `coach_certifications`

7. **Textarea Field** - Spécialités
   - Label : `Specialties`
   - Name : `coach_specialties`

#### Section : Photos

8. **Media Field** - Photo de profil
   - Label : `Profile Photo`
   - Name : `coach_profile_photo`
   - Max files : 1, Max size : 2 MB
   - Allowed types : image/jpeg, image/png, image/webp

9. **Media Field** - Photo de couverture
   - Label : `Cover Photo`
   - Name : `coach_cover_photo`
   - Max files : 1, Max size : 5 MB
   - Allowed types : image/jpeg, image/png, image/webp

#### Section : Liens sociaux

10. **Text Field** (URL) - Instagram → Name : `coach_instagram`
11. **Text Field** (URL) - YouTube → Name : `coach_youtube`
12. **Text Field** (URL) - Website → Name : `coach_website`

#### Section : Sports et langues

13. **Checkbox Field** - Sports You Teach → Name : `coach_sports`
    - Fill Options From : **Taxonomy Terms** → `sport`

14. **Checkbox Field** - Languages Spoken → Name : `coach_languages`
    - Fill Options From : **Taxonomy Terms** → `language`

#### Champ caché (IMPORTANT)

15. **Hidden Field** - ID du post Coach
    - Name : `coach_post_id`
    - Field Value : `Current User Meta`
    - Meta Key : `coach_post_id`
    - La valeur est lue depuis les user meta de l'utilisateur connecté (voir snippet PHP section 5.5)

#### Bouton de soumission

16. **Action Button** - Label : `Save My Profile`

### 5.3 Configurer l'action post-soumission (Insert/Update Post)

> **Point critique :** L'action doit **mettre à jour** le post Coach existant, PAS en créer un nouveau. C'est le mapping du champ `coach_post_id` vers `Post ID (will update the post)` qui fait la différence.

Dans l'onglet **"JetForm"** (sidebar droite) > **"Post Submit Actions"** :

1. Cliquer sur **"+ New Action"**
2. Choisir **"Insert/Update Post"**
3. Configurer :
   - **Post Type :** `Coaches`
   - **Post Status :** `Keep current status` (ne pas changer le statut)

4. Dans le **mapping des champs** (la liste des champs du formulaire avec leurs rôles), configurer chaque champ :

| Champ du formulaire | Mapping (dropdown à droite) | Détail |
|---|---|---|
| `coach_first_name` | `Post Meta` | `coach_first_name` |
| `coach_last_name` | `Post Meta` | `coach_last_name` |
| `coach_bio` | `Post Meta` | `coach_bio` |
| `coach_location` | `Post Meta` | `coach_location` |
| `coach_years_experience` | `Post Meta` | `coach_years_experience` |
| `coach_certifications` | `Post Meta` | `coach_certifications` |
| `coach_specialties` | `Post Meta` | `coach_specialties` |
| `coach_profile_photo` | `Post Meta` | `coach_profile_photo` |
| `coach_cover_photo` | `Post Meta` | `coach_cover_photo` |
| `coach_instagram` | `Post Meta` | `coach_instagram` |
| `coach_youtube` | `Post Meta` | `coach_youtube` |
| `coach_website` | `Post Meta` | `coach_website` |
| `coach_sports` | `Post Terms` | `Sports` |
| `coach_languages` | `Post Terms` | `Languages` |
| **`coach_post_id`** | **`Post ID (will update the post)`** | **C'est le réglage clé !** |

> **Pourquoi "Post ID (will update the post)" ?** Quand JetFormBuilder reçoit un Post ID valide via ce mapping, il **met à jour** le post existant au lieu d'en créer un nouveau. Le champ `coach_post_id` est rempli automatiquement par le filtre PHP (voir 5.5) avec l'ID du post Coach de l'utilisateur connecté.

> **Post Title :** Ne PAS mapper un champ vers Post Title ici. Le titre sera généré automatiquement par un hook PHP (Prénom + Nom). Voir section 5.6.

### 5.4 Configurer le Preset (pré-remplissage des champs)

Le **Preset** est la fonctionnalité de JetFormBuilder qui pré-remplit les champs du formulaire avec les données existantes. Sans preset, le coach verrait des champs vides à chaque visite, même s'il a déjà rempli son profil.

#### Comment configurer le Preset :

1. Ouvrir le formulaire dans JetFormBuilder
2. Dans la **sidebar droite**, aller dans l'onglet **"JetForm"**
3. Trouver la section **"Preset"** (ou **"Form Preset"**)
4. Activer le preset : **ON**
5. Configurer les réglages globaux du preset :

| Réglage | Valeur |
|---|---|
| **Source** (ou Preset From) | `Post` |
| **Get Post ID From** | `URL Query Variable` |
| **Query Variable Name** | `coach_id` |

> **Pourquoi URL Query Variable ?** JetFormBuilder ne propose pas de lire le Post ID depuis un champ du formulaire dans le preset. On utilise donc un paramètre dans l'URL (`?coach_id=456`), ajouté automatiquement par un snippet PHP (voir section 5.5b). Le coach ne voit rien de différent — la redirection est transparente.

6. Ensuite, pour **chaque champ** du formulaire, mapper la source de pré-remplissage :

| Champ du formulaire | Preset Source | Preset Key |
|---|---|---|
| `coach_first_name` | Post Meta | `coach_first_name` |
| `coach_last_name` | Post Meta | `coach_last_name` |
| `coach_bio` | Post Meta | `coach_bio` |
| `coach_location` | Post Meta | `coach_location` |
| `coach_years_experience` | Post Meta | `coach_years_experience` |
| `coach_certifications` | Post Meta | `coach_certifications` |
| `coach_specialties` | Post Meta | `coach_specialties` |
| `coach_profile_photo` | Post Meta | `coach_profile_photo` |
| `coach_cover_photo` | Post Meta | `coach_cover_photo` |
| `coach_instagram` | Post Meta | `coach_instagram` |
| `coach_youtube` | Post Meta | `coach_youtube` |
| `coach_website` | Post Meta | `coach_website` |
| `coach_sports` | Post Terms | `sport` |
| `coach_languages` | Post Terms | `language` |
| `coach_post_id` | *(pas de mapping)* | *(laisser vide — sa valeur vient du Current User Meta)* |

> **Note sur les taxonomies (sports, langues) :** Si l'option "Post Terms" n'apparaît pas comme source dans le preset, les checkboxes liées aux taxonomies via "Fill Options From > Taxonomy Terms" devraient se pré-remplir automatiquement quand le Preset récupère le post. Si ce n'est pas le cas, essayer de mapper manuellement avec la source "Post Terms" et le slug de la taxonomie (`sport` ou `language`).

### 5.5 Snippet PHP : S'assurer que chaque coach a son coach_post_id en user meta

Le champ caché `coach_post_id` est configuré en **"Current User Meta"** avec la clé `coach_post_id`. JetFormBuilder lit directement les user meta de WordPress — c'est une option native, pas de hack.

Le snippet PHP ci-dessous s'assure que chaque utilisateur avec le rôle `coach` a bien un user meta `coach_post_id` contenant l'ID de son post Coach. S'il n'existe pas encore de post Coach pour cet utilisateur, il en crée un automatiquement.

**Ajouter ce code dans `functions.php` (ou via un plugin de snippets comme WPCode) :**

```php
/**
 * RideMaster - S'assurer que chaque coach a son coach_post_id en user meta.
 *
 * Tourne sur 'init' (très tôt dans le cycle WordPress).
 * Pour les utilisateurs coach :
 * - Si le user meta 'coach_post_id' existe et pointe vers un post valide → rien à faire
 * - Sinon → cherche le post Coach par post_author, ou en crée un en brouillon
 * - Sauvegarde l'ID dans le user meta
 *
 * La query DB ne s'exécute qu'une seule fois. Ensuite le user meta est en cache.
 */
add_action( 'init', function() {
    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return;
    }

    $user = get_userdata( $user_id );
    if ( ! $user || ! in_array( 'coach_role', (array) $user->roles, true ) ) {
        return;
    }

    // Vérifier si le user meta est déjà défini et pointe vers un post valide
    $coach_post_id = get_user_meta( $user_id, 'coach_post_id', true );
    if ( $coach_post_id && get_post_status( $coach_post_id ) !== false ) {
        return; // Déjà bon
    }

    // Chercher le post Coach existant par post_author
    $coach_posts = get_posts( [
        'post_type'   => 'coach',
        'author'      => $user_id,
        'numberposts' => 1,
        'post_status' => 'any',
        'fields'      => 'ids',
    ] );

    if ( ! empty( $coach_posts ) ) {
        $coach_id = $coach_posts[0];
    } else {
        // Aucun post Coach → en créer un automatiquement en brouillon
        $coach_id = wp_insert_post( [
            'post_type'   => 'coach',
            'post_title'  => trim( $user->first_name . ' ' . $user->last_name ),
            'post_status' => 'draft',
            'post_author' => $user_id,
        ] );

        if ( $coach_id && ! is_wp_error( $coach_id ) ) {
            wp_set_object_terms( $coach_id, 'pending', 'coach-status' );
        }
    }

    // Sauvegarder l'ID du post Coach dans les user meta
    if ( $coach_id && ! is_wp_error( $coach_id ) ) {
        update_user_meta( $user_id, 'coach_post_id', $coach_id );
    }
} );
```

> **Comment ça marche ?**
> 1. Le coach se connecte et navigue n'importe où sur le site
> 2. WordPress déclenche `init` → le snippet PHP vérifie si `coach_post_id` est dans les user meta
> 3. Si non : cherche le post Coach par `post_author` = utilisateur connecté, ou en crée un
> 4. Sauvegarde l'ID dans `user_meta('coach_post_id')`
> 5. Le formulaire se charge → le champ caché "Current User Meta: coach_post_id" récupère la valeur nativement
> 6. Le Preset utilise cet ID pour pré-remplir tous les champs
> 7. À la soumission, "Post ID (will update the post)" utilise cet ID pour **mettre à jour** le post existant
>
> Après la première exécution, le user meta est en cache — aucune query supplémentaire.

### 5.5b Snippet PHP : Redirection automatique avec coach_id dans l'URL

Le Preset (section 5.4) est configuré pour lire le Post ID depuis un paramètre URL `coach_id`. Ce snippet redirige automatiquement le coach vers `/coach-dashboard/profile/?coach_id=XXX` quand il visite la page profil. La redirection est instantanée et transparente.

**Ajouter ce code dans Code Snippets :**

```php
/**
 * RideMaster - Ajouter ?coach_id=XXX dans l'URL de la page profil.
 *
 * Nécessaire pour que le Preset JetFormBuilder puisse lire le Post ID
 * depuis l'URL et pré-remplir les champs du formulaire.
 *
 * SÉCURITÉ : Force toujours le coach_id du coach connecté.
 * Si quelqu'un modifie l'URL manuellement avec un autre ID,
 * il sera redirigé vers son propre coach_id.
 *
 * Note : On vérifie l'URL directement car /coach-dashboard/profile/ est
 * une sous-page virtuelle gérée par JetEngine Profile Builder.
 */
add_action( 'template_redirect', function() {
    if ( strpos( $_SERVER['REQUEST_URI'], '/coach-dashboard/profile' ) === false ) {
        return;
    }

    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return;
    }

    $coach_post_id = get_user_meta( $user_id, 'coach_post_id', true );
    if ( ! $coach_post_id ) {
        return;
    }

    // Sécurité : si coach_id est absent OU ne correspond pas au coach connecté → rediriger
    if ( empty( $_GET['coach_id'] ) || intval( $_GET['coach_id'] ) !== intval( $coach_post_id ) ) {
        wp_redirect( add_query_arg( 'coach_id', $coach_post_id, strtok( $_SERVER['REQUEST_URI'], '?' ) ) );
        exit;
    }
} );
```

> **Sécurité :** Ce snippet force toujours le `coach_id` du coach connecté. Si un coach tente de modifier l'URL pour accéder aux données d'un autre coach (ex: `?coach_id=123`), il sera automatiquement redirigé vers son propre ID. Combiné avec le champ caché `coach_post_id` (Current User Meta), il est impossible de modifier les données d'un autre coach.

### 5.6 Snippet PHP : Titre du post = Prénom + Nom (automatique)

Le titre du post Coach doit être "Prénom Nom" (ex: "Xavier Kain"). Comme JetFormBuilder ne permet pas de concaténer deux champs pour le titre, on utilise les hooks WordPress natifs `added_post_meta` et `updated_post_meta`. Ces hooks se déclenchent **à chaque fois** qu'un meta field est sauvegardé, que ce soit par JetFormBuilder, wp-admin, ou n'importe quel plugin. Ils fonctionnent aussi bien à la création (insert) qu'à la mise à jour (update).

**Ajouter ce code dans `functions.php` :**

```php
/**
 * RideMaster - Mettre à jour automatiquement le titre du post Coach
 * avec Prénom + Nom à chaque fois que coach_first_name ou coach_last_name est modifié.
 *
 * Utilise les hooks WordPress natifs (pas de dépendance à JetFormBuilder).
 * Fonctionne sur insert ET update.
 */
function ridemaster_auto_coach_title( $meta_id, $post_id, $meta_key, $meta_value ) {
    // Ne réagir qu'aux meta fields du nom
    if ( ! in_array( $meta_key, [ 'coach_first_name', 'coach_last_name' ], true ) ) {
        return;
    }

    // Vérifier que c'est bien un post Coach
    $post = get_post( $post_id );
    if ( ! $post || $post->post_type !== 'coach' ) {
        return;
    }

    $first = get_post_meta( $post_id, 'coach_first_name', true );
    $last  = get_post_meta( $post_id, 'coach_last_name', true );

    if ( $first && $last ) {
        $new_title = trim( $first . ' ' . $last );
        // Éviter une mise à jour inutile (et potentielle boucle)
        if ( $post->post_title !== $new_title ) {
            wp_update_post( [
                'ID'         => $post_id,
                'post_title' => $new_title,
                'post_name'  => sanitize_title( $first . '-' . $last ),
            ] );
        }
    }
}
add_action( 'added_post_meta', 'ridemaster_auto_coach_title', 10, 4 );
add_action( 'updated_post_meta', 'ridemaster_auto_coach_title', 10, 4 );
```

> **Résultat :** Chaque fois que `coach_first_name` ou `coach_last_name` est sauvegardé (par le formulaire front-end OU par wp-admin), le titre du post est automatiquement recalculé. Le slug aussi (`xavier-kain`), ce qui donne une URL publique propre : `/coaches/xavier-kain/`.

> **Pourquoi pas un hook JetFormBuilder ?** Les hooks comme `jet-fb/action/after-post-update` ne se déclenchent que sur les updates, pas les inserts, et les noms/paramètres varient selon les versions. Les hooks WordPress `added_post_meta` / `updated_post_meta` sont universels et fiables.

### 5.7 Résumé du fonctionnement complet

```
COACH SE CONNECTE
    │
    ▼
PHP (init) — Snippet 5.5
    │
    ├─ Vérifie user meta 'coach_post_id'
    ├─ Si absent → cherche post Coach par post_author
    ├─ Si aucun post → en crée un en brouillon
    ├─ Sauvegarde l'ID dans user_meta (ex: 456)
    │
    ▼
COACH OUVRE /coach-dashboard/profile/
    │
    ▼
PHP (template_redirect) — Snippet 5.5b
    │
    ├─ Lit user_meta('coach_post_id') → 456
    ├─ Redirige vers /coach-dashboard/profile/?coach_id=456
    │
    ▼
FORMULAIRE SE CHARGE (avec ?coach_id=456 dans l'URL)
    │
    ├─ Champ caché "coach_post_id" (Current User Meta)
    │   └─ Lit user_meta('coach_post_id') → valeur = 456
    │
    ├─ Preset activé
    │   └─ Source: Post, ID From: URL Query Variable 'coach_id' (= 456)
    │   └─ Pré-remplit tous les champs avec les meta du post 456
    │
    ▼
COACH MODIFIE SES INFOS ET CLIQUE "SAVE"
    │
    ├─ Action "Insert/Update Post"
    │   └─ coach_post_id → "Post ID (will update the post)"
    │   └─ MET À JOUR le post 456 (pas de nouveau post créé)
    │   └─ Sauvegarde tous les meta fields + taxonomies
    │
    ├─ WordPress hooks (added/updated_post_meta)
    │   └─ Détecte changement de coach_first_name ou coach_last_name
    │   └─ Met à jour post_title = "Xavier Kain"
    │   └─ Met à jour post_name = "xavier-kain"
    │
    ▼
PROFIL MIS À JOUR ✓
```

### 5.8 Plugin Inline Edit (édition visuelle du profil)

Le plugin **RideMaster Inline Edit** permet au coach d'éditer son profil directement sur la page de profil stylée, sans passer par un formulaire classique.

#### Comment ça marche :

1. Le coach voit sa page profil avec le design Elementor (mode lecture)
2. Un bouton flottant **"Edit Profile"** (en bas à droite) permet d'entrer en mode édition
3. En mode édition :
   - **Textes** : cliquer sur un texte pour le modifier directement (contenteditable)
   - **Images** : un overlay "Change" apparaît au survol des photos → ouvre la médiathèque WordPress
   - **Taxonomies** (sports, langues) : une icône crayon ouvre un popup avec des checkboxes
4. Une barre en bas affiche les boutons **"Save Changes"** et **"Cancel"**
5. Les modifications sont sauvegardées via AJAX (pas de rechargement de page pendant l'édition)
6. Après la sauvegarde, la page se recharge pour afficher les données mises à jour

#### Installation :

1. Le plugin est dans le dossier `plugins/ridemaster-inline-edit/`
2. Zipper le dossier `ridemaster-inline-edit/` et l'uploader via **Plugins > Ajouter > Uploader**
3. Activer le plugin

#### Configuration dans Elementor :

Le plugin détecte les éléments éditables grâce à des **classes CSS** ajoutées aux widgets Elementor. Pour chaque widget qui affiche une donnée du coach, ajouter la classe CSS correspondante dans **Advanced > CSS Classes** :

| Widget Elementor | Classe CSS à ajouter | Type d'édition |
|---|---|---|
| Heading (prénom) | `rm-edit-coach_first_name` | Texte inline |
| Heading (nom) | `rm-edit-coach_last_name` | Texte inline |
| Text Editor (bio) | `rm-edit-coach_bio` | Texte multiline |
| Text Editor (localisation) | `rm-edit-coach_location` | Texte inline |
| Text Editor (expérience) | `rm-edit-coach_years_experience` | Nombre |
| Text Editor (certifications) | `rm-edit-coach_certifications` | Texte multiline |
| Text Editor (expérience/parcours) | `rm-edit-coach_experience` | Texte multiline |
| Text Editor (Instagram) | `rm-edit-coach_instagram` | URL |
| Text Editor (YouTube) | `rm-edit-coach_youtube` | URL |
| Text Editor (Website) | `rm-edit-coach_website` | URL |
| Image (photo profil) | `rm-edit-coach_profile_photo` | Médiathèque WP |
| Image (photo couverture) | `rm-edit-coach_cover_photo` | Médiathèque WP |
| Text Editor (sports) | `rm-edit-coach_sports` | Popup checkboxes |
| Text Editor (langues) | `rm-edit-coach_languages` | Popup checkboxes |

> **Exemple :** Pour rendre la bio du coach éditable, sélectionner le widget Text Editor qui affiche le dynamic tag `coach_bio`, aller dans **Advanced > CSS Classes**, et ajouter `rm-edit-coach_bio`.

> **Sécurité :** Le bouton "Edit Profile" n'apparaît que pour les utilisateurs avec le rôle `coach_role`, uniquement sur la page `/coach-dashboard/profile/`. Les sauvegardes AJAX vérifient le nonce, le rôle, et que le post appartient bien au coach connecté. Il est impossible de modifier le profil d'un autre coach.

> **Note :** Le formulaire JetFormBuilder (section 5.1-5.4) reste disponible comme fallback. Les deux systèmes mettent à jour les mêmes meta fields — il n'y a pas de conflit.

---

## 6. Créer le formulaire de création de camp (JetFormBuilder)

Ce formulaire permet au coach de créer un nouveau camp. Un camp est un **produit WooCommerce** avec des meta fields JetEngine supplémentaires.

### 6.1 Créer le formulaire (import JSON)

> **Import rapide :** Un fichier JSON prêt à l'import est disponible dans `jetformbuilder-imports/camp-creation-form.json`. Aller dans **JetFormBuilder > Import**, sélectionner ce fichier et importer. Le formulaire sera créé avec tous les champs pré-configurés.

Si tu préfères créer manuellement, aller dans **JetFormBuilder > Add New** avec le titre **"Camp Creation Form"** et ajouter les champs décrits en 6.2.

### 6.2 Champs du formulaire

Le formulaire contient les champs suivants (déjà présents si importé via JSON) :

#### Section : Informations générales

1. **Text Field** - Titre du camp
   - Label : `Camp Name`
   - Name : `camp_title`
   - Required : Oui
   - Placeholder : `e.g. Kite Week Tarifa - Beginner Friendly`

2. **Textarea Field** - Description
   - Label : `Camp Description`
   - Name : `camp_description`
   - Required : Oui
   - Placeholder : `Describe your camp, what makes it unique, what participants will experience...`

#### Section : Dates et tarifs

3. **Date Field** - Date de début
   - Label : `Start Date`
   - Name : `camp_start_date`
   - Required : Oui

4. **Date Field** - Date de fin
   - Label : `End Date`
   - Name : `camp_end_date`
   - Required : Oui

> **Note :** Le formulaire utilise 2 champs date séparés pour une UX plus claire. Un hook PHP (section 6.3) fusionne automatiquement les valeurs dans le champ JetEngine `full_date` (advanced-date) après soumission.

5. **Number Field** - Prix par personne
   - Label : `Price Per Person`
   - Name : `camp_price`
   - Required : Oui
   - Min : 0, Step : 1
   - Mappe vers `_regular_price` WooCommerce. La devise est un réglage global WooCommerce (pas de champ devise par produit).

6. **Number Field** - Nombre de places maximum
   - Label : `Maximum Participants`
   - Name : `camp_max_spots`
   - Required : Oui
   - Min : 1, Max : 50
   - Mappe vers `_stock` WooCommerce. Un hook PHP active automatiquement la gestion de stock.

#### Section : Détails du camp

7. **Repeater Field** - Ce qui est inclus
   - Label : `What's Included`
   - Name : `camp_included`
   - Sous-champ : **Text Field**, name `included_in_the_camp`, label "Included in the camp"
   - Le coach clique sur "+ Add" pour ajouter des items (coaching, équipement, hébergement...)

8. **Repeater Field** - Ce qui n'est pas inclus
   - Label : `What's Not Included`
   - Name : `camp_not_included`
   - Sous-champ : **Text Field**, name `not_included_in_the_camp`, label "Not Included in the camp"

> **Important :** Les noms des sous-champs (`included_in_the_camp`, `not_included_in_the_camp`) doivent correspondre exactement à ceux configurés dans le Meta Box JetEngine "Camp Fields" (voir section 3.2). Si les noms ne correspondent pas, les données ne s'afficheront pas correctement dans les templates Elementor.

9. **Textarea Field** - Programme / Planning
   - Label : `Schedule / Program`
   - Name : `camp_schedule`
   - Placeholder : `Day 1: Welcome and briefing...`

#### Section : Catégorisation

10. **Checkbox Field** - Sport
    - Label : `Sport`
    - Name : `camp_sport`
    - Required : Oui
    - Fill Options From : **Taxonomy Terms** → `sport`

11. **Checkbox Field** - Niveaux acceptés
    - Label : `Levels Accepted`
    - Name : `camp_level`
    - Required : Oui
    - Fill Options From : **Taxonomy Terms** → `level`

#### Section : Photos

12. **Media Field** - Image principale
    - Label : `Main Camp Image (Featured Image)`
    - Name : `camp_thumbnail`
    - Max files : 1, Max size : 5 MB
    - Allowed types : image/jpeg, image/png, image/webp
    - `insert_attachment: true`, `value_format: id`
    - Required : Oui

13. **Media Field** - Galerie photos
    - Label : `Photo Gallery (max 10 images)`
    - Name : `camp_gallery`
    - Max files : 10, Max size : 5 MB par image
    - Allowed types : image/jpeg, image/png, image/webp
    - `insert_attachment: true`, `value_format: id`

#### Section : Spot / Destination

14. **Select Field** - Spot
    - Label : `Select a Spot`
    - Name : `camp_spot`
    - Fill Options From : **Posts** → Post Type : `spot`
    - Required : Oui
    - Le coach choisit un spot existant dans la dropdown

#### Bouton de soumission

15. **Submit Button** - Label : `Publish My Camp`

### 6.3 Configurer les actions post-soumission

Dans l'onglet **"JetForm"** (sidebar droite) > **"Post Submit Actions"** :

#### Action 1 : Insert/Update Post

1. Cliquer sur **"+ New Action"** > **"Insert Post"**
2. Configurer :
   - **Post Type :** `Products` (produit WooCommerce)
   - **Post Status :** `publish` (sera rétrogradé en `draft` par le hook PHP si le coach n'est pas vérifié)

**Mapping des champs (FIELDS MAP) :**

| Champ du formulaire | Maps to (dropdown) | Notes |
|---|---|---|
| Camp Name | **Post Title** | Titre du produit WooCommerce |
| Camp Description | **Post Content** | Description longue du produit |
| Start Date | **Post Meta** → `camp_start_date` | Temporaire, fusionné dans `full_date` par le hook PHP |
| End Date | **Post Meta** → `camp_end_date` | Temporaire, fusionné dans `full_date` par le hook PHP |
| Price Per Person | **Product Regular Price** | Mapping natif WooCommerce (gère `_regular_price`) |
| Maximum Participants | **Post Meta** → `camp_max_spots` | Géré par le hook PHP (pas de mapping WooCommerce natif car buggé dans JFB) |
| What's Included | **Post Meta** → `camp_included` | Repeater parent |
| Included in the camp | **Post Meta** → `included_in_the_camp` | Sous-champ du repeater |
| What's Not Included | **Post Meta** → `camp_not_included` | Repeater parent |
| Not Included in the camp | **Post Meta** → `not_included_in_the_camp` | Sous-champ du repeater |
| Schedule / Program | **Post Meta** → `camp_schedule` | Textarea |
| Sport | **Post Terms** → `Sports` | Taxonomie sport |
| Levels Accepted | **Post Terms** → `Levels` | Taxonomie level |
| Main Camp Image | **Post Thumbnail** | Image à la une WooCommerce |
| Photo Gallery | **Product Gallery** | Mapping natif WooCommerce (gère `_product_image_gallery`) |
| Select a Spot | **Post Meta** → `camp_spot` | ID du spot, utilisé par le hook PHP pour créer la relation |

> **Note : pas de DEFAULT FIELDS nécessaires.** Le stock (`_stock`, `_manage_stock`, `_stock_status`) est entièrement géré par le hook PHP (section 6.4, point B2). Le module WooCommerce de JFB 3.x a un bug (`dynamic property deprecated`) qui cause des écrasements de valeurs — on évite donc les mappings natifs WooCommerce pour le stock.

#### Action 2 : Redirect

- Type : **Redirect to Page**
- URL : `/coach-dashboard/my-camps/`

### 6.4 Hook PHP consolidé : Initialisation du produit WooCommerce après création

Ce hook complète ce que JetFormBuilder ne gère pas nativement : synchronisation `_price`, type de produit, fusion des dates, catégorisation, et relations JetEngine.

> **Note technique :** Le hook JetFormBuilder `jet-fb/action/after-post-insert` ne se déclenche pas dans JFB 3.x (module `actions-v2`). On utilise à la place le hook WordPress standard `save_post_product` qui se déclenche à chaque création de produit. On détecte que ça vient du formulaire camp en vérifiant la présence de `camp_title` dans `$_REQUEST`.

**Ajouter ce code dans Code Snippets (WPCode) :**

```php
/**
 * Helper : trouver une relation JetEngine par son label.
 * Dans JetEngine 3.x, le nom est dans $args['labels']['name'], pas $args['name'].
 */
function ridemaster_find_relation( $label ) {
    if ( ! function_exists( 'jet_engine' ) ) { return null; }
    $relations = jet_engine()->relations->get_active_relations();
    foreach ( $relations as $relation ) {
        $args = $relation->get_args();
        if ( isset( $args['labels']['name'] ) && $args['labels']['name'] === $label ) {
            return $relation;
        }
    }
    return null;
}

/**
 * RideMaster - Initialiser le produit WooCommerce après création d'un camp
 * via le formulaire JetFormBuilder.
 *
 * Champs gérés NATIVEMENT par JetFormBuilder (voir mapping section 6.3) :
 *   - Prix (_regular_price) via "Product Regular Price"
 *   - Stock (_stock) via "Product Stock Quantity"
 *   - _manage_stock / _stock_status via DEFAULT FIELDS
 *   - Gallery (_product_image_gallery) via "Product Gallery"
 *   - Thumbnail via "Post Thumbnail"
 *   - Taxonomies (sport, level) via "Post Terms"
 *
 * Ce hook gère le RESTE :
 * A. Synchronisation _price (WooCommerce a besoin de _price ET _regular_price)
 * B. Type de produit = simple
 * C. Fusion camp_start_date + camp_end_date → full_date (format JetEngine)
 * D. Assignation de la catégorie produit "Camp"
 * E. Création de la relation Coach → Camp (JetEngine)
 * F. Création de la relation Spot → Camp (JetEngine)
 * G. Contrôle draft/publish selon le statut du coach
 * H. Nettoyage du cache WooCommerce
 */
add_action( 'save_post_product', function( $post_id, $post, $update ) {
    // --- Gardes de sécurité ---
    // Ne pas exécuter sur les mises à jour, seulement les nouveaux produits
    if ( $update ) { return; }
    // Ne pas exécuter sur les auto-saves ou révisions
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) { return; }
    // Vérifier que ça vient du formulaire camp JetFormBuilder
    if ( ! isset( $_REQUEST['camp_title'] ) ) { return; }

    // Protection contre la ré-entrance (wp_update_post en point G déclenche save_post)
    static $running = false;
    if ( $running ) { return; }
    $running = true;

    error_log( 'RideMaster: Initialisation du camp (product #' . $post_id . ')' );

    // --- Récupérer les données du formulaire ---
    $form_data = $_REQUEST;

    // A. Écrire _price (WooCommerce utilise _price pour les queries et l'affichage)
    // _regular_price sera écrit par JetFormBuilder via le mapping natif "Product Regular Price"
    $price = isset( $form_data['camp_price'] ) ? sanitize_text_field( $form_data['camp_price'] ) : '';
    if ( $price !== '' ) {
        update_post_meta( $post_id, '_price', $price );
    }

    // B. Définir le type de produit = simple
    wp_set_object_terms( $post_id, 'simple', 'product_type' );

    // B2. Forcer le stock — exécution DIFFÉRÉE via shutdown
    // Le module WooCommerce de JFB (Wc_Product_Modifier) s'exécute APRÈS save_post_product
    // et écrase nos valeurs. On utilise shutdown pour écrire en tout dernier.
    $stock = isset( $form_data['camp_max_spots'] ) ? intval( $form_data['camp_max_spots'] ) : 0;
    if ( $stock > 0 ) {
        add_action( 'shutdown', function() use ( $post_id, $stock ) {
            update_post_meta( $post_id, '_stock', $stock );
            update_post_meta( $post_id, '_manage_stock', 'yes' );
            update_post_meta( $post_id, '_stock_status', 'instock' );
            wc_delete_product_transients( $post_id );
            error_log( 'RideMaster: Stock FINAL set to ' . $stock . ' for product #' . $post_id );
        } );
    }

    // C. Fusionner les dates dans full_date (format JetEngine advanced-date)
    $start = isset( $form_data['camp_start_date'] ) ? sanitize_text_field( $form_data['camp_start_date'] ) : '';
    $end   = isset( $form_data['camp_end_date'] )   ? sanitize_text_field( $form_data['camp_end_date'] )   : '';
    if ( $start && $end ) {
        // Sauver aussi les dates individuelles (utilisées par le hook d'édition)
        update_post_meta( $post_id, 'camp_start_date', $start );
        update_post_meta( $post_id, 'camp_end_date', $end );
        // Format JetEngine advanced-date : 3 meta séparés
        // full_date = timestamp start (valeur principale, utilisée par les queries/tri)
        // full_date__end_date = timestamp end (double underscore)
        // full_date__config = JSON config (double underscore)
        update_post_meta( $post_id, 'full_date', strtotime( $start ) );
        update_post_meta( $post_id, 'full_date__end_date', strtotime( $end ) );
        update_post_meta( $post_id, 'full_date__config', wp_json_encode( array(
            'dates' => array(
                array(
                    'date'        => $start,
                    'is_end_date' => '1',
                    'end_date'    => $end,
                ),
            ),
        ) ) );
        error_log( 'RideMaster: full_date set — start=' . $start . ' (' . strtotime( $start ) . '), end=' . $end . ' (' . strtotime( $end ) . ')' );
    }

    // D. Assigner la catégorie produit "Camp"
    // Pré-requis : le terme "Camp" (slug: camp) doit exister dans Products > Categories
    wp_set_object_terms( $post_id, 'camp', 'product_cat', true );

    // E. Relation Coach → Camp (JetEngine Relations)
    // Note : dans JetEngine, le nom est dans $args['labels']['name'], pas $args['name']
    $user_id       = get_current_user_id();
    $coach_post_id = intval( get_user_meta( $user_id, 'coach_post_id', true ) );
    if ( $coach_post_id && function_exists( 'jet_engine' ) ) {
        $relation = ridemaster_find_relation( 'Coach to Camps' );
        if ( $relation ) {
            $relation->update( $coach_post_id, $post_id );
            error_log( 'RideMaster: Relation coach→camp créée (coach #' . $coach_post_id . ' → product #' . $post_id . ')' );
        } else {
            error_log( 'RideMaster: ERREUR — relation "Coach to Camps" non trouvée' );
        }

        // E2. Stocker le coach_post_id directement sur le camp (meta dénormalisée pour le Query Builder)
        update_post_meta( $post_id, '_coach_post_id', $coach_post_id );
    }

    // F. Relation Spot → Camp (JetEngine Relations)
    $spot_id = isset( $form_data['camp_spot'] ) ? intval( $form_data['camp_spot'] ) : 0;
    if ( $spot_id && function_exists( 'jet_engine' ) ) {
        $relation = ridemaster_find_relation( 'Spot to Camps' );
        if ( $relation ) {
            $relation->update( $spot_id, $post_id );
            error_log( 'RideMaster: Relation spot→camp créée (spot #' . $spot_id . ' → product #' . $post_id . ')' );
        } else {
            error_log( 'RideMaster: ERREUR — relation "Spot to Camps" non trouvée' );
        }
    }

    // G. Contrôle draft/publish selon le statut du coach
    if ( $coach_post_id ) {
        $coach_status = wp_get_object_terms( $coach_post_id, 'coach-status', [ 'fields' => 'slugs' ] );
        if ( ! in_array( 'verified', (array) $coach_status, true ) ) {
            wp_update_post( [
                'ID'          => $post_id,
                'post_status' => 'draft',
            ] );
        }
    }

    // H. Nettoyer les transients WooCommerce
    wc_delete_product_transients( $post_id );

    error_log( 'RideMaster: Initialisation du camp terminée pour product #' . $post_id );
    $running = false;
}, 10, 3 );
```

> **Important — Noms des relations :** Le code cherche les relations par leur **label** dans `$args['labels']['name']` (pas `$args['name']` qui est toujours vide dans JetEngine 3.x). Les labels `"Coach to Camps"` et `"Spot to Camps"` doivent correspondre **exactement** à ceux définis dans **JetEngine > Relations**.
>
> **Format `full_date` :** JetEngine advanced-date stocke 3 meta séparés : `full_date` (timestamp start), `full_date__end_date` (timestamp end), `full_date__config` (JSON). Le hook reproduit exactement ce format. Si le champ supporte plusieurs plages de dates (via "+ ADD DATE"), seule la première est créée par le formulaire.
>
> **Debug :** Les messages `error_log()` écrivent dans `wp-content/debug.log`. Ajouter `define('WP_DEBUG_DISPLAY', false);` dans `wp-config.php` pour ne pas les afficher à l'écran (évite l'erreur "headers already sent"). Les supprimer une fois que tout fonctionne.

### 6.5 Formulaire d'édition d'un camp existant

Pour permettre au coach de modifier un camp qu'il a déjà créé :

1. **Dupliquer** le formulaire "Camp Creation Form"
2. Renommer en **"Camp Edit Form"**
3. Changer l'action post-soumission de **"Insert Post"** à **"Update Post"**
4. **Post Type :** `Products`
5. Configurer le **Post ID** : récupérer depuis l'URL (query variable `camp_id`)
6. Configurer le **Preset** (pré-remplissage) :
   - Source : `Post`
   - Get Post ID From : `URL Query Variable`
   - Query Variable Name : `camp_id`
   - Mapper chaque champ vers sa source (Post Meta pour les meta, Post Terms pour les taxonomies)
7. Le hook PHP doit aussi gérer les mises à jour. Utiliser `jet-fb/action/after-post-update` avec la même logique de synchronisation (_price, full_date, etc.)
8. **Sécurité :** Vérifier que le produit édité appartient au coach connecté (`post_author == get_current_user_id()`)

---

## 7. Designer les pages du dashboard avec Elementor

### 7.1 Créer les templates Elementor pour chaque sous-page

Pour chaque sous-page du Profile Builder, on crée un template Elementor.

#### Template 1 : Page "Mon Profil"

1. Aller dans **Templates > Ajouter** (ou **Elementor > Mes templates**)
2. Type : **Section** ou **Page**
3. Nom : `Coach Dashboard - Mon Profil`
4. Designer la page avec :
   - Un titre "Mon Profil"
   - Le formulaire JetFormBuilder **"Formulaire Profil Coach"** (utiliser le widget JetForm)
   - Widget à utiliser : **JetForm** > sélectionner le formulaire créé à l'étape 5
5. Styliser selon le design RideMaster (couleurs teal, typographie DM Sans, etc.)
6. Publier le template

#### Template 2 : Page "Mes Camps"

1. Créer un nouveau template : `Coach Dashboard - Mes Camps`
2. Designer avec :
   - Titre "Mes Camps"
   - Un bouton **"+ Créer un nouveau camp"** qui renvoie vers la sous-page `create-camp`
   - Un **JetEngine Listing Grid** qui affiche les camps (produits WooCommerce) du coach connecté :
     - Widget : **Listing Grid**
     - Utiliser une **Custom Query** JetEngine (voir ci-dessous)
     - Colonnes : 1 (liste) ou 2 (grille)
   - Chaque camp dans la liste affiche :
     - Image principale (featured image du produit)
     - Titre du produit
     - Dates (depuis `full_date`)
     - Prix (depuis `_regular_price`)
     - Statut (publié / brouillon)
     - Boutons : **"Modifier"** | **"Supprimer"**
     - Le bouton "Modifier" renvoie vers une page avec le formulaire d'édition pré-rempli

**Créer une Query dans JetEngine (pour afficher uniquement les camps du coach connecté) :**

1. Aller dans **JetEngine > Query Builder > Add New**
2. Nom : `Camps du coach connecté`
3. Type : **Relations Query**
4. Configuration :
   - **Relation :** `Coach to Camps`
   - **Items To Get :** `Get Children Items For Fixed Parent`
   - **Initial Object From :** `Query Variable`
   - **Query Variable Name :** `current_coach_post_id`
5. Sauvegarder
6. Utiliser cette query dans le Listing Grid : **Custom Query** > sélectionner "Camps du coach connecté"

**Snippet PHP requis** (à ajouter dans WPCode, voir section 7.3) : La Relation Query a besoin du coach POST ID du user connecté, mais le dropdown "Initial Object From" ne propose pas de macro user meta. Le snippet ci-dessous injecte le `coach_post_id` en query variable à chaque chargement de page :

```php
/**
 * RideMaster - Injecter le coach_post_id du coach connecté en query variable.
 * Utilisé par la Relation Query JetEngine "Camps du coach connecté".
 *
 * La relation coach-to-camps est post-to-post (Coach → Product).
 * Le Query Builder ne peut pas résoudre "user connecté → son coach post ID"
 * nativement. Ce snippet fait le pont entre les deux.
 */
add_action( 'wp', function() {
    if ( ! is_user_logged_in() ) return;

    $coach_post_id = get_user_meta( get_current_user_id(), 'coach_post_id', true );
    if ( $coach_post_id ) {
        $_REQUEST['current_coach_post_id'] = intval( $coach_post_id );
    }
} );
```

> **Pourquoi Relation Query et pas Author ou Meta Query ?** Le filtre `Author = Current User` lie les camps au user WordPress qui les a créés. Si un admin crée un camp pour un coach, le `post_author` = admin, et le coach ne verrait pas le camp dans son dashboard. La **Relation Query** utilise directement la relation JetEngine `coach-to-camps` : elle récupère les camps liés au coach du user connecté, quel que soit le créateur du post WordPress.
>
> **Pourquoi un snippet PHP pour la query variable ?** La relation JetEngine est post-to-post (Coach post → Product post), mais l'utilisateur connecté est identifié par son user ID WordPress. Il faut un pont pour traduire "user connecté" → "son coach post ID". Le snippet injecte cette valeur dans `$_REQUEST` pour que le Query Builder la lise via l'option "Query Variable".
>
> **Note :** La relation `coach-to-camps` doit exister dans **JetEngine > Relations** et être correctement configurée (Parent = Coaches, Child = Products, One to many). Le hook PHP (section 6.4, point G) crée automatiquement cette relation quand un camp est créé via le formulaire. Pour les camps créés manuellement dans wp-admin, la relation doit être assignée manuellement dans la meta box de relation du produit.

#### Template 3 : Page "Créer un Camp"

1. Créer un nouveau template : `Coach Dashboard - Créer Camp`
2. Designer avec :
   - Titre "Créer un nouveau camp"
   - Le formulaire JetFormBuilder **"Formulaire Création Camp"** (widget JetForm)
   - Un message d'info si le coach n'est pas encore vérifié : "Votre camp sera visible une fois votre profil vérifié par notre équipe."
3. Styliser et publier

#### Template 4 (optionnel) : Page "Éditer un Camp"

1. Créer : `Coach Dashboard - Éditer Camp`
2. Le formulaire **"Formulaire Édition Camp"** pré-rempli
3. Accessible via un lien du type : `/coach-dashboard/edit-camp/?camp_id=123`

### 7.2 Assigner les templates aux sous-pages du Profile Builder

1. Retourner dans **JetEngine > Profile Builder**
2. Pour chaque sous-page créée à l'étape 4.2, assigner le template Elementor correspondant :
   - Mon Profil → Template `Coach Dashboard - Mon Profil`
   - Mes Camps → Template `Coach Dashboard - Mes Camps`
   - Créer un Camp → Template `Coach Dashboard - Créer Camp`

### 7.3 Designer la page principale du dashboard

La page **"Coach Dashboard"** (créée à l'étape 4.2) sert de conteneur pour toutes les sous-pages.

1. Ouvrir la page **"Coach Dashboard"** dans Elementor
2. Designer le layout avec :
   - **Sidebar gauche** (menu de navigation du dashboard) - le Profile Builder de JetEngine peut générer automatiquement le menu des sous-pages via le widget **"Profile Menu"**
   - **Zone principale** qui affiche le contenu de la sous-page active
3. Widgets JetEngine à utiliser :
   - **Profile Menu** : affiche automatiquement les liens vers les sous-pages (Mon Profil, Mes Camps, Créer un Camp)
   - **Profile Subpage Content** : affiche le contenu du template de la sous-page active

**Structure de la page :**
```
┌─────────────────────────────────────────────────────┐
│  Header du site (déjà existant)                     │
├────────────┬────────────────────────────────────────┤
│            │                                        │
│  SIDEBAR   │  CONTENU DYNAMIQUE                     │
│            │  (Profile Subpage Content)              │
│  [Profile  │                                        │
│   Menu]    │  ← Change selon la sous-page active    │
│            │                                        │
│  Mon Profil│                                        │
│  Mes Camps │                                        │
│  + Camp    │                                        │
│            │                                        │
│  ──────    │                                        │
│  Déconnex. │                                        │
│            │                                        │
├────────────┴────────────────────────────────────────┤
│  Footer du site (déjà existant)                     │
└─────────────────────────────────────────────────────┘
```

---

## 8. Créer la page d'inscription et connexion coach

### 8.1 Page de connexion coach

1. Aller dans **Pages > Ajouter**
2. Titre : **"Connexion Coach"**
3. Slug : `coach-login`
4. Ouvrir dans Elementor et designer :

**Option A : Avec le widget JetEngine Login Form**
- Ajouter le widget **"Login Form"** de JetEngine
- Configurer :
  - Redirect after login : `/coach-dashboard/`
  - Style : selon le design RideMaster

**Option B : Avec JetFormBuilder**
- Créer un formulaire JetFormBuilder de type Login
- Action post-soumission : **"User Login"**
- Redirect : `/coach-dashboard/`

**Éléments de la page :**
```
┌─────────────────────────────────────────────┐
│  Logo RideMaster                            │
│                                             │
│  "Espace Coach"                             │
│  "Connectez-vous à votre dashboard"         │
│                                             │
│  ┌─────────────────────────────────────┐    │
│  │  Email            [_______________] │    │
│  │  Mot de passe     [_______________] │    │
│  │                                     │    │
│  │  □ Se souvenir de moi               │    │
│  │                                     │    │
│  │  [Se connecter]                     │    │
│  │                                     │    │
│  │  Mot de passe oublié ?              │    │
│  └─────────────────────────────────────┘    │
│                                             │
│  Pas encore de compte ?                     │
│  [Créer un compte coach]                    │
└─────────────────────────────────────────────┘
```

### 8.2 Page d'inscription coach

1. Aller dans **Pages > Ajouter**
2. Titre : **"Inscription Coach"**
3. Slug : `coach-register`

**Créer le formulaire d'inscription avec JetFormBuilder :**

1. **JetFormBuilder > Add New**
2. Titre : **"Formulaire Inscription Coach"**
3. Champs :
   - **Text Field** - Prénom (`first_name`) - Required
   - **Text Field** - Nom (`last_name`) - Required
   - **Text Field** - Email (`user_email`) - Required, type: email
   - **Text Field** - Mot de passe (`user_pass`) - Required, type: password
   - **Text Field** - Confirmer le mot de passe (`confirm_pass`) - Required, type: password
   - **Checkbox** - Conditions générales (`accept_terms`) - Required
   - **Action Button** - "Créer mon compte"

4. **Actions post-soumission :**

   **Action 1 : Register User**
   - Type : **"Register User"**
   - User Role : `coach`
   - User Login from : `user_email` (utiliser l'email comme identifiant)
   - User Email from : `user_email`
   - User Password from : `user_pass`
   - First Name from : `first_name`
   - Last Name from : `last_name`

   **Action 2 : Insert Post (créer le profil Coach)**
   - Post Type : `coach`
   - Post Status : `draft` (en attente de validation, on le passera en publish après)
   - Post Title from : Combiner prénom + nom
   - Post Author : l'utilisateur qui vient d'être créé (automatique)
   - Meta `coach_first_name` from : `first_name`
   - Meta `coach_last_name` from : `last_name`

   **Action 3 : Assigner le statut coach**
   - Assigner le terme `pending` de la taxonomie `coach-status` au post Coach créé

   **Action 4 : Redirect**
   - Rediriger vers : `/coach-dashboard/profile/` (pour compléter le profil)

   **Action 5 (optionnel) : Send Email**
   - Envoyer un email de bienvenue au coach
   - Envoyer une notification à l'admin qu'un nouveau coach s'est inscrit

### 8.3 Pouvoir désactiver l'inscription publique

Pour basculer entre le mode "inscription libre" et "invitation uniquement" :

**Option simple : masquer la page d'inscription**
1. Dans **Pages > Inscription Coach**, passer la page en **Brouillon**
2. Le lien "Créer un compte" sur la page de login mènera à une 404
3. Ou mieux : ajouter une condition d'affichage dans Elementor qui montre un message "Les inscriptions sont actuellement fermées" quand la page est en mode invitation

**Option plus propre : utiliser une option WordPress**
1. Ajouter dans **Réglages > Général** (ou via un plugin d'options) un toggle "Activer l'inscription coach"
2. Conditionner l'affichage du formulaire d'inscription via une condition Elementor ou un shortcode

### 8.4 Invitation par admin (Option B)

Quand un admin veut inviter un coach :

1. L'admin va dans **Utilisateurs > Ajouter** dans wp-admin
2. Remplit : email, prénom, nom
3. Sélectionne le rôle : **Coach**
4. Coche **"Envoyer une notification à l'utilisateur"**
5. Le coach reçoit un email avec un lien pour définir son mot de passe
6. Une fois connecté, il est redirigé vers son dashboard front-end

**Point important :** Quand l'admin crée un utilisateur coach, il faut aussi créer le post CPT Coach associé. On peut automatiser ça avec un hook :

```php
// Créer automatiquement un post Coach quand un utilisateur avec le rôle "coach" est créé
add_action('set_user_role', function($user_id, $role, $old_roles) {
    if ($role !== 'coach_role') return;

    // Vérifier qu'un post Coach n'existe pas déjà
    $existing = get_posts([
        'post_type'   => 'coach',
        'author'      => $user_id,
        'numberposts' => 1,
        'post_status' => 'any',
    ]);

    if (!empty($existing)) return;

    $user = get_userdata($user_id);

    // Créer le post Coach
    $coach_id = wp_insert_post([
        'post_type'   => 'coach',
        'post_title'  => $user->first_name . ' ' . $user->last_name,
        'post_status' => 'draft',
        'post_author' => $user_id,
    ]);

    // Assigner le statut "pending"
    if ($coach_id) {
        wp_set_object_terms($coach_id, 'pending', 'coach-status');
        update_post_meta($coach_id, 'coach_first_name', $user->first_name);
        update_post_meta($coach_id, 'coach_last_name', $user->last_name);
    }
}, 10, 3);
```

---

## 9. Configurer la validation admin du coach

### 9.1 Le principe

- Quand un coach s'inscrit, son post CPT Coach est créé en statut WordPress `draft` et avec le terme `pending` dans la taxonomie `coach-status`
- Rien n'est visible publiquement
- L'admin vérifie le profil dans wp-admin et change le statut
- Une fois validé, le post Coach passe en `publish` et tous ses camps (produits WooCommerce catégorie "Camp") aussi

### 9.2 Processus de validation par l'admin

1. L'admin va dans **wp-admin > Coaches** (CPT Coach)
2. Il voit la liste des coachs avec leur statut (`pending`, `verified`)
3. Il ouvre le profil du coach, vérifie les informations
4. Pour valider :
   - Changer le terme de la taxonomie `coach-status` de `pending` à `verified`
   - Changer le statut WordPress du post de `draft` à `publish`

> **Pour voir les camps d'un coach :** Aller dans **Products** et filtrer par catégorie "Camp" et par auteur.

### 9.3 Automatiser la publication des camps quand un coach est vérifié

Ajouter ce code dans Code Snippets (WPCode) :

```php
/**
 * RideMaster - Publier automatiquement les camps (produits WooCommerce)
 * quand le statut d'un coach passe à "verified".
 *
 * IMPORTANT : Le filtre tax_query sur product_cat = camp est essentiel.
 * Sans lui, vérifier un coach publierait TOUS ses produits WooCommerce,
 * pas seulement ses camps.
 */
add_action('set_object_terms', function($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids) {
    if ($taxonomy !== 'coach-status') return;

    // Vérifier si le nouveau statut contient "verified"
    $term_slugs = [];
    foreach ($terms as $term) {
        $term_obj = get_term($term, 'coach-status');
        if ($term_obj && !is_wp_error($term_obj)) {
            $term_slugs[] = $term_obj->slug;
        }
    }

    if (!in_array('verified', $term_slugs)) return;

    // Récupérer l'auteur du post Coach
    $coach_post = get_post($object_id);
    if (!$coach_post || $coach_post->post_type !== 'coach') return;

    $author_id = $coach_post->post_author;

    // Publier tous les camps (produits WooCommerce catégorie "Camp") de ce coach
    $camps = get_posts([
        'post_type'   => 'product',
        'author'      => $author_id,
        'post_status' => 'draft',
        'numberposts' => -1,
        'tax_query'   => [
            [
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => 'camp',
            ],
        ],
    ]);

    foreach ($camps as $camp) {
        wp_update_post([
            'ID'          => $camp->ID,
            'post_status' => 'publish',
        ]);
        // Nettoyer le cache WooCommerce pour chaque produit publié
        wc_delete_product_transients( $camp->ID );
    }
}, 10, 6);
```

### 9.4 Gérer la création de camps selon le statut du coach

> **Déjà géré par le hook de la section 6.4.** Le contrôle draft/publish des camps est intégré directement dans le hook `jet-fb/action/after-post-insert` (section 6.4, point I). Ce hook vérifie le statut du coach après la création du produit et rétrograde en `draft` si le coach n'est pas vérifié.
>
> L'ancien filtre `wp_insert_post_data` n'est plus utilisé car il est impossible de distinguer un produit-camp d'un autre produit WooCommerce avant que les taxonomy terms soient assignés (le filtre se déclenche avant la sauvegarde des termes).

### 9.5 Notification admin

Pour que l'admin soit notifié quand un nouveau coach s'inscrit :

```php
// Envoyer un email à l'admin quand un nouveau coach s'inscrit
add_action('wp_insert_post', function($post_id, $post, $update) {
    if ($update) return; // Pas pour les mises à jour
    if ($post->post_type !== 'coach') return;

    $admin_email = get_option('admin_email');
    $coach_name = $post->post_title;

    $subject = '[RideMaster] Nouveau coach inscrit : ' . $coach_name;
    $message = "Un nouveau coach s'est inscrit sur RideMaster.\n\n";
    $message .= "Nom : $coach_name\n";
    $message .= "Lien : " . admin_url("post.php?post=$post_id&action=edit") . "\n\n";
    $message .= "Veuillez vérifier son profil et le valider si approprié.";

    wp_mail($admin_email, $subject, $message);
}, 10, 3);
```

---

## 10. Bloquer l'accès wp-admin et redirections

### 10.1 Bloquer wp-admin et masquer la barre admin

> **Déjà géré par Profile Builder (étape 4.2) :** Les réglages "Hide admin bar" (ON) et "Restrict admin area access" (ON, seuls Administrator et Shop manager autorisés) font le travail. Pas besoin de snippet PHP pour ça.

### 10.2 Rediriger après connexion

Quand un coach se connecte (même via wp-login.php par erreur), le rediriger vers son dashboard. Ajouter dans `functions.php` :

```php
// Rediriger les coachs vers leur dashboard après connexion
add_filter('login_redirect', function($redirect_to, $request, $user) {
    if (isset($user->roles) && in_array('coach_role', $user->roles)) {
        return home_url('/coach-dashboard/');
    }
    return $redirect_to;
}, 10, 3);
```

### 10.3 Protéger les pages du dashboard

> **Déjà géré par Profile Builder (étape 4.2) :**
> - Le paramètre "Available for the user role: Coach Role" sur chaque sous-page filtre l'accès
> - "For not authorized users" redirige les non-connectés vers la page de login
> - "For users with restricted access" redirige vers `/coach-login/`

### 10.4 Ajouter un lien de déconnexion dans le dashboard

Dans le template Elementor du dashboard, ajouter un bouton/lien de déconnexion :
- URL : `<?php echo wp_logout_url(home_url('/coach-login/')); ?>`
- Ou en dynamique avec JetEngine : utiliser le dynamic tag **"Logout URL"**
- Redirect après déconnexion : `/coach-login/`

---

## 11. Configurer les restrictions de médias

### 11.1 Limiter les uploads par coach

Ajouter dans `functions.php` :

```php
// Limiter la taille des fichiers uploadés par les coachs
add_filter('upload_size_limit', function($size) {
    if (current_user_can('coach_role')) {
        return 5 * 1024 * 1024; // 5 MB max par fichier
    }
    return $size;
});

// Limiter les types de fichiers autorisés pour les coachs
add_filter('upload_mimes', function($mimes) {
    if (current_user_can('coach_role')) {
        return [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'webp'         => 'image/webp',
        ];
    }
    return $mimes;
});
```

### 11.2 Filtrer la médiathèque par auteur

Pour que chaque coach ne voie que ses propres fichiers dans la médiathèque (quand il uploade via les formulaires) :

```php
// Les coachs ne voient que leurs propres médias
add_filter('ajax_query_attachments_args', function($query) {
    if (current_user_can('coach_role')) {
        $query['author'] = get_current_user_id();
    }
    return $query;
});
```

> **Note :** Avec JetFormBuilder, les uploads passent directement par le formulaire et sont attachés au post créé. Le filtrage de la médiathèque est surtout utile si le coach accède à un sélecteur de médias.

---

## 12. Tests et vérification

### 12.1 Checklist de test

Effectuer les tests suivants dans l'ordre :

#### Test 1 : Inscription coach
- [ ] Aller sur `/coach-register/`
- [ ] Remplir le formulaire d'inscription
- [ ] Vérifier qu'un utilisateur WordPress est créé avec le rôle `coach`
- [ ] Vérifier qu'un post CPT Coach est créé en statut `draft` avec le terme `pending`
- [ ] Vérifier que l'admin reçoit un email de notification
- [ ] Vérifier la redirection vers `/coach-dashboard/profile/`

#### Test 2 : Connexion coach
- [ ] Aller sur `/coach-login/`
- [ ] Se connecter avec les identifiants du coach créé
- [ ] Vérifier la redirection vers `/coach-dashboard/`
- [ ] Vérifier que la barre d'admin WordPress n'apparaît pas
- [ ] Tenter d'accéder à `/wp-admin/` → doit rediriger vers `/coach-dashboard/`

#### Test 3 : Profil coach
- [ ] Aller sur `/coach-dashboard/profile/`
- [ ] Remplir tous les champs du formulaire de profil
- [ ] Uploader une photo de profil et une photo de couverture
- [ ] Sélectionner des sports et des langues
- [ ] Soumettre le formulaire
- [ ] Vérifier que les données sont sauvegardées dans le post Coach (dans wp-admin)
- [ ] Revenir sur la page → les champs doivent être pré-remplis avec les données sauvegardées

#### Test 4 : Création de camp (coach non vérifié)
- [ ] Aller sur `/coach-dashboard/create-camp/`
- [ ] Remplir tous les champs du formulaire (titre, description, dates, prix, participants, included/not included, schedule, sport, level, images, spot)
- [ ] Soumettre
- [ ] Vérifier que le produit WooCommerce est créé en statut `draft` (car coach non vérifié)
- [ ] Vérifier dans **wp-admin > Products** que le produit a la catégorie "Camp"
- [ ] Vérifier que la relation Coach → Camp est créée dans JetEngine
- [ ] Vérifier que la relation Spot → Camp est créée dans JetEngine
- [ ] Le camp ne doit PAS être visible sur le site public

#### Test 4bis : Intégrité du produit WooCommerce
- [ ] Dans wp-admin > Products, ouvrir le produit camp créé
- [ ] Vérifier que le type de produit est "Simple"
- [ ] Vérifier que `_regular_price` et `_price` sont définis et égaux
- [ ] Vérifier que `_manage_stock` = yes et `_stock` = nombre de places saisi
- [ ] Vérifier que `_stock_status` = instock
- [ ] Vérifier que `_product_image_gallery` contient des IDs séparés par des virgules
- [ ] Vérifier que le meta `full_date` contient les timestamps de début et fin
- [ ] Vérifier que les repeaters `camp_included` et `camp_not_included` contiennent les données saisies
- [ ] Vérifier que `camp_schedule` contient le programme saisi

#### Test 5 : Liste des camps du coach
- [ ] Aller sur `/coach-dashboard/my-camps/`
- [ ] Vérifier que le camp créé apparaît dans la liste (query filtrée par product_cat = camp)
- [ ] Vérifier que le statut (brouillon) est affiché
- [ ] Tester le bouton "Modifier" → doit ouvrir le formulaire d'édition pré-rempli
- [ ] Modifier un champ et sauvegarder → vérifier la mise à jour

#### Test 6 : Validation admin
- [ ] En tant qu'admin, aller dans wp-admin > Coaches
- [ ] Ouvrir le profil du coach
- [ ] Changer la taxonomie `coach-status` de `pending` à `verified`
- [ ] Changer le statut WordPress du post de `draft` à `publish`
- [ ] Vérifier que le profil du coach est maintenant visible sur le site (page `/coaches/nom-du-coach/`)
- [ ] Aller dans **wp-admin > Products**, filtrer par catégorie "Camp" et par auteur
- [ ] Vérifier que les camps (produits) du coach sont automatiquement passés en `publish`
- [ ] Vérifier que les camps sont maintenant visibles sur la boutique WooCommerce

#### Test 7 : Création de camp (coach vérifié)
- [ ] En tant que coach vérifié, créer un nouveau camp
- [ ] Vérifier que le produit est directement publié (statut `publish`)
- [ ] Vérifier qu'il apparaît immédiatement sur le site public

#### Test 8 : Restrictions médias
- [ ] En tant que coach, tenter d'uploader un fichier PDF → doit être refusé
- [ ] Uploader une image > 5 MB → doit être refusée
- [ ] Uploader une image JPEG/PNG/WebP < 5 MB → doit fonctionner
- [ ] Vérifier que le coach ne voit que ses propres médias

#### Test 9 : Désactivation de l'inscription
- [ ] Passer la page "Inscription Coach" en brouillon
- [ ] Vérifier que `/coach-register/` retourne une 404
- [ ] Créer un coach via wp-admin (invitation)
- [ ] Vérifier que le coach invité peut se connecter et accéder au dashboard

#### Test 10 : Sécurité
- [ ] Vérifier qu'un coach ne peut PAS modifier le profil/camp d'un autre coach
- [ ] Vérifier qu'un utilisateur non connecté ne peut PAS accéder au dashboard
- [ ] Vérifier qu'un utilisateur avec le rôle "Subscriber" ne peut PAS accéder aux pages coach

---

## Récapitulatif de l'architecture

```
DASHBOARDS (2 systèmes indépendants) :

COACH → JetEngine Profile Builder
├── /coach-login/          → Page de connexion coach (custom)
├── /coach-register/       → Page d'inscription coach (désactivable)
└── /coach-dashboard/      → Dashboard principal (Profile Builder)
    ├── /profile/          → Formulaire profil coach
    ├── /my-camps/         → Liste des camps (produits WooCommerce)
    └── /create-camp/      → Formulaire création camp (produit WooCommerce)

RIDER → WooCommerce My Account (déjà en place)
└── /my-account/           → Dashboard WooCommerce natif
    ├── /orders/           → Réservations (commandes de camps)
    ├── /edit-account/     → Infos du compte
    └── /edit-address/     → Adresse de facturation

NOTE : Les camps sont des produits WooCommerce (post_type: product)
avec la catégorie "Camp" (product_cat). Pas de CPT camp dédié.
WooCommerce gère nativement : prix, stock, images, achat.
JetEngine Meta Box "Camp Fields" gère : dates (full_date),
inclus/non-inclus (repeaters), programme (camp_schedule).

FORMULAIRES JETFORMBUILDER :
├── Formulaire Inscription Coach
├── Formulaire Profil Coach (édition)
├── Formulaire Création Camp  → insert product + meta WooCommerce
└── Formulaire Édition Camp   → update product + meta WooCommerce

SNIPPETS PHP (Code Snippets / WPCode) :
├── Stocker coach_post_id en user meta (init)            ← ÉTAPE 5.5
├── Redirect auto ?coach_id= sur page profil             ← ÉTAPE 5.5b
├── Auto-titre post Coach = Prénom + Nom                 ← ÉTAPE 5.6
├── Liaison auto utilisateur ↔ post Coach (inscription)
├── Init produit WooCommerce après création camp :       ← ÉTAPE 6.4
│   ├── _price = _regular_price
│   ├── _manage_stock = yes, _stock_status = instock
│   ├── product_type = simple
│   ├── full_date = merge(start_date, end_date)
│   ├── product_cat = camp
│   ├── Relation coach-to-camps
│   ├── Relation spot-to-camps
│   └── Contrôle draft/publish selon statut coach
├── Publication auto des camps quand coach vérifié       ← ÉTAPE 9.3
├── Redirection wp-admin → dashboard   ← GÉRÉ PAR PROFILE BUILDER
├── Masquer barre admin                ← GÉRÉ PAR PROFILE BUILDER
├── Redirection après connexion (login_redirect)
├── Restrictions upload médias
├── Filtrage médiathèque par auteur
└── Notification admin nouveau coach

TEMPLATES ELEMENTOR :
├── Coach Dashboard - Mon Profil
├── Coach Dashboard - Mes Camps
├── Coach Dashboard - Créer Camp
└── Coach Dashboard - Éditer Camp
```

---

## Ordre d'exécution recommandé

1. Installer JetFormBuilder + activer Profile Builder
2. Vérifier les rôles Coach et Rider (déjà créés via Members)
3. Ajouter les meta fields manquants
4. Ajouter les snippets PHP (functions.php)
5. Configurer le Profile Builder (pages + sous-pages) → Coach uniquement
6. Créer les formulaires JetFormBuilder
7. Créer les templates Elementor du dashboard
8. Créer les pages login/register coach
9. Tester le parcours complet
10. Styliser et affiner le design
11. (Plus tard) Customiser la page WooCommerce My Account pour les riders si besoin

---

**Document créé le 11 Février 2026**
**Projet RideMaster - Dashboard Coach Front-End MVP**
