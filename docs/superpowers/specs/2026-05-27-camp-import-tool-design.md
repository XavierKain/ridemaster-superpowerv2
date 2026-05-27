# Camp Import Tool — Design Specification

**Date :** 2026-05-27
**Auteur :** Xavier Kain (avec assistance Claude)
**Statut :** Design approuvé, prêt pour planification d'implémentation

---

## 1. Contexte et objectif

Aujourd'hui, l'ajout de camps "exemple" sur la plateforme Ridemaster (pour pré-remplir le site avant lancement) se fait à la main : copier-coller du texte, téléchargement et redimensionnement d'images, création manuelle du coach, du spot, de l'hôtel, puis remplissage du formulaire JetFormBuilder de création de camp. Très chronophage.

**Objectif :** un workflow conversationnel où l'utilisateur colle l'URL d'un camp depuis le site d'un coach et obtient, en une seule action, le camp créé sur Ridemaster avec :
- toutes les données métier (titre, description, dates, prix, capacité, inclus/non-inclus)
- les images en haute résolution, optimisées et renommées pour le SEO
- le coach lié (créé si absent)
- le spot lié (créé si absent)
- l'hôtel lié (créé si absent)
- toutes les relations JetEngine correctement établies

**Hors scope :**
- Page d'admin WordPress pour déclencher l'import (envisagée plus tard si l'API LLM est ajoutée — voir [section 12](#12-évolutions-futures))
- Imports en masse (batch CSV)
- Détection automatique de doublons par similarité de contenu (l'idempotence se base uniquement sur l'URL source)
- Synchronisation continue avec le site coach (one-shot uniquement)

---

## 2. Contexte projet utile

Ridemaster est actuellement en phase pré-lancement : le site (https://ridemaster.eu) est accessible sur son URL finale mais aucun coach réel ne l'utilise. Un backup complet existe. **Cela permet de travailler directement sur la "prod" pendant le développement de cet outil** — voir [section 10](#10-environnement-de-test-et-sécurité).

Timezone serveur : Europe/Paris.

Un camp dans Ridemaster est un produit WooCommerce (`post_type = product`) avec la taxonomie `product_cat = camp`. Voir [plugins/ridemaster/includes/class-camp.php](../../../plugins/ridemaster/includes/class-camp.php) pour le détail du modèle de données. Les liaisons coach↔camp (ID 20, one-to-many), coach↔spot (ID 19, many-to-many), spot↔camp (ID 18, one-to-many) sont gérées via **JetEngine relations** stockées dans la table `wp_jet_rel_default`.

Un audit live de l'install a été réalisé le 2026-05-27 (cf [Annexe B](#annexe-b--audit-live-2026-05-27)) qui confirme les slugs CPT, IDs de relations, et énumère exhaustivement les termes de taxonomie disponibles.

---

## 3. Architecture globale

Deux composants distincts :

### 3.1 Plugin séparé `ridemaster-importer`

Nouveau plugin WordPress, vivant à côté du plugin principal. **Désactivable et désinstallable** sans impact quand le seeding sera terminé.

```
plugins/ridemaster-importer/
├── ridemaster-importer.php          # Header + bootstrap + check dépendance ridemaster
├── readme.txt
└── includes/
    ├── class-importer-endpoint.php   # REST route POST /ridemaster/v1/import-camp
    ├── class-payload-validator.php   # Schéma + validation des inputs
    ├── class-image-handler.php       # media_sideload + fallback base64 + SEO meta
    ├── class-idempotency.php         # Lookup par _import_source_url
    └── class-rollback.php            # Cleanup en cas d'erreur partielle
```

Dépendance : `ridemaster` (plugin principal) doit être actif. Sinon, message d'admin et désactivation auto.

### 3.2 Refactor minimal du plugin principal `ridemaster`

On expose des méthodes statiques publiques pour la création des entités, **sans changer aucun comportement existant**. Les hooks JetFormBuilder actuels continuent d'appeler exactement le même code, juste extrait en méthode.

```
plugins/ridemaster/includes/
├── class-camp.php       # + Camp::create_from_payload(), + Camp::apply_camp_meta()
├── class-coach.php      # + Coach::create_from_payload()
├── class-spot.php       # + Spot::create_from_payload()    (nouveau fichier si inexistant)
└── class-hotel.php      # + Hotel::create_from_payload()
```

### 3.3 Workflow d'invocation

Conversationnel uniquement, dans Claude Code :

```
Utilisateur : "Importe ce camp : https://coachsite.com/tarifa-camp-june"
       │
       ▼
Claude (moi) :
  1. Playwright MCP → navigation + rendu JS + DOM snapshot
  2. Vision + raisonnement → extraction structurée (titre, dates, prix, etc.)
  3. Filtrage sémantique des images + classification par rôle
  4. Téléchargement local + optimisation (ImageMagick) + renommage SEO
  5. Construction du payload JSON
  6. POST /wp-json/ridemaster/v1/import-camp (Basic auth via Application Password)
       │
       ▼
Endpoint plugin importer :
  7. Validation, idempotency check
  8. Coach / Spot / Hotel : trouver ou créer
  9. Camp : appel Camp::create_from_payload()
  10. Images : media_sideload_image() + meta SEO
  11. JetEngine relations
  12. Retour JSON {camp_id, edit_url, public_url, warnings}
       │
       ▼
Claude (moi) :
  Affiche le résumé à l'utilisateur, signale les avertissements,
  donne le lien d'édition direct dans WP admin.
```

---

## 4. Contrat de l'endpoint `/ridemaster/v1/import-camp`

### 4.1 Authentification

`Basic auth` via WordPress Application Password. Permission requise : `manage_options` (admin) ou `edit_others_posts` (editor). Aucun mode public.

### 4.2 Méthode

`POST /wp-json/ridemaster/v1/import-camp`

`Content-Type: application/json`

### 4.3 Payload (request body)

```json
{
  "import_source_url": "https://coachsite.com/camp-tarifa-june",
  "force_overwrite": false,

  "coach": {
    "match_by": {"email": "john@coachsite.com", "name": "John Doe"},
    "create_if_missing": true,
    "data": {
      "first_name": "John",
      "last_name": "Doe",
      "email": "john@coachsite.com",
      "wp_role": "coach_role",
      "coach_status": "validated",
      "bio": "10 years of kitesurf coaching...",
      "location": "Tarifa, Spain",
      "years_experience": 10,
      "certifications": ["IKO Level 3", "VDWS Instructor"],
      "profile_photo": {"url": "https://...", "base64": null, "alt": "..."},
      "cover_image":   {"url": "https://...", "base64": null, "alt": "..."},
      "sport": ["kitesurf"],
      "languages": ["english", "french"],
      "instagram": "@johndoe",
      "youtube": "https://youtube.com/...",
      "website": "https://coachsite.com"
    }
  },

  "spot": {
    "match_by": {"name": "Tarifa", "country": "Spain"},
    "create_if_missing": true,
    "data": {
      "name": "Tarifa",
      "country": "Spain",
      "description": "...",
      "sport": ["kitesurf"],
      "level": ["beginner", "intermediate", "advanced"],
      "water_type": ["flat-water", "waves"],
      "images": [{"url": "...", "base64": null, "alt": "...", "filename": "..."}]
    }
  },

  "hotel": {
    "match_by": {"name": "Hotel Hurricane"},
    "create_if_missing": true,
    "data": {
      "name": "Hotel Hurricane",
      "description": "...",
      "images": [{"url": "...", "base64": null, "alt": "...", "filename": "..."}]
    }
  },

  "camp": {
    "title": "Tarifa Kite Camp — June 2026",
    "description_html": "<p>Join us for an unforgettable week of kitesurf...</p>",
    "sport": "kitesurf",
    "level": ["beginner", "intermediate"],
    "languages": ["english", "french"],
    "camp_status": "open",
    "price_eur": 890,
    "max_spots": 12,
    "start_date": "2026-06-15",
    "end_date":   "2026-06-22",
    "schedule": "Day 1: Arrival and welcome dinner\nDay 2-6: Morning theory...",
    "included":     ["Coaching 6h/day", "Board and kite rental", "Lunch on the beach"],
    "not_included": ["Flights", "Insurance", "Dinner"],
    "yoast": {
      "focus_keyword": "tarifa kite camp june",
      "meta_description": "Join a week of kitesurf coaching in Tarifa with..."
    },
    "featured_image": {"url": "...", "base64": null, "alt": "...", "filename": "...", "role": "camp_hero"},
    "gallery": [
      {"url": "...", "base64": null, "alt": "...", "filename": "...", "role": "camp_action"},
      {"url": "...", "base64": null, "alt": "...", "filename": "...", "role": "camp_group"}
    ]
  }
}
```

**Notes sur le shape :**

- Pour chaque image, **soit `url` soit `base64`** est fourni (jamais les deux). `base64` est utilisé en fallback quand `media_sideload_image()` côté serveur a échoué (hotlink protection).
- `filename` est le nom SEO-friendly généré par Claude côté client (voir [section 7](#7-gestion-des-images)).
- `alt` est la description sémantique générée par Claude.
- `role` aide le serveur à comprendre la sémantique (hero, gallery, portrait, cover…) — sans impact sur le post WC mais utile pour les logs.

### 4.4 Réponse (succès, HTTP 200)

```json
{
  "status": "success",
  "camp_id": 1234,
  "edit_url":   "https://ridemaster.eu/wp-admin/post.php?post=1234&action=edit",
  "public_url": "https://ridemaster.eu/camps/tarifa-kite-camp-june-2026/",
  "created": {
    "coach":  {"id": 567,  "post_id": 568,  "was_new": true},
    "spot":   {"id": 89,                     "was_new": false},
    "hotel":  {"id": 123,                    "was_new": true},
    "camp":   {"id": 1234, "images_imported": 8, "images_failed": 0}
  },
  "warnings": [
    "Image #3 was below 800x600 (was 640x480), included anyway",
    "Hotel description was very short (32 chars), you may want to enrich"
  ]
}
```

### 4.5 Réponse (erreurs)

| HTTP | `code`                  | Quand                                                                 |
|------|-------------------------|-----------------------------------------------------------------------|
| 400  | `INVALID_PAYLOAD`       | Champs requis manquants, types invalides                              |
| 401  | `UNAUTHORIZED`          | App password manquant ou invalide                                     |
| 403  | `INSUFFICIENT_PERMISSIONS` | Utilisateur sans `edit_others_posts`                               |
| 409  | `DUPLICATE_IMPORT`      | URL déjà importée, `force_overwrite=false`. Inclut `existing_camp_id` |
| 409  | `COACH_AMBIGUOUS`       | Plusieurs coachs matchent `match_by`. Inclut `candidates: [...]`      |
| 422  | `STRIPE_BLOCKER`        | Camp créé en draft car coach sans Stripe (warning, pas blocking)      |
| 500  | `IMPORT_FAILED`         | Erreur serveur. Inclut `step` et `rolled_back: [...]`                 |

Toutes les erreurs contiennent un objet `details` avec contexte spécifique.

---

## 5. Refactor sûr du plugin principal

### 5.1 Principe directeur (non-négociable)

> **Le plugin principal `ridemaster` doit avoir un comportement strictement identique avant et après le refactor pour tous les flux existants.**

### 5.2 Pattern « Extract Method »

Pour chaque entité (Camp, Coach, Spot, Hotel), on extrait la logique inline des hooks JetFormBuilder en méthodes publiques statiques. **Les hooks existants sont préservés et appellent simplement la nouvelle méthode.** Le chemin d'exécution du flux JFB est byte-identique.

Exemple pour Camp :

```php
// AVANT — class-camp.php (état actuel approximatif)
add_action('jet-form-builder/action/post-insert', function($post_id, $data) {
    // ~50 lignes : forçage stock, meta dates, repeaters, JetEngine relations,
    // blocker Stripe, etc.
});

// APRÈS — class-camp.php (post-refactor)
class Camp {
    public static function apply_camp_meta(int $post_id, array $data): void {
        // EXACTEMENT les mêmes 50 lignes, déplacées telles quelles
    }

    public static function create_from_payload(array $payload): int|WP_Error {
        $post_id = wp_insert_post([
            'post_type'    => 'product',
            'post_title'   => $payload['title'],
            'post_content' => $payload['description_html'],
            'post_status'  => 'publish',
        ]);
        if (is_wp_error($post_id) || !$post_id) {
            return new WP_Error('post_insert_failed', '...');
        }

        wp_set_object_terms($post_id, 'simple', 'product_type');
        wp_set_object_terms($post_id, 'camp', 'product_cat');

        self::apply_camp_meta($post_id, $payload);  // ← réutilise la même fonction

        return $post_id;
    }
}

// Le hook existant devient un wrapper trivial
add_action('jet-form-builder/action/post-insert', function($post_id, $data) {
    Camp::apply_camp_meta($post_id, $data);
});
```

Avantage : la méthode `apply_camp_meta` est **le même bloc de code, déplacé**, donc le risque de régression est minimal (limite : un point-virgule oublié dans le copier-coller). La méthode `create_from_payload` est purement additive.

### 5.3 Ordre de refactor (strangler pattern)

Une étape par PR/commit, chaque étape testée avant la suivante :

1. **Squelette du plugin importer** (endpoint vide qui retourne "pong"). Aucun refactor encore. Permet de tester l'auth, le routing, la dépendance plugin.
2. **Camp** : extract `apply_camp_meta`, expose `create_from_payload`. Test : créer un camp via JFB existant + créer un camp via la nouvelle méthode → diff postmeta = 0.
3. **Endpoint v1** : `/import-camp` accepte un payload minimal avec coach_id / spot_id / hotel_id existants. Crée seulement le camp.
4. **Coach** : extract + `create_from_payload`. Test : inscription coach via JFB existant + nouvelle méthode.
5. **Endpoint v2** : accepte un coach inline (avec création).
6. **Spot** : idem.
7. **Endpoint v3** : accepte spot inline.
8. **Hotel** : idem.
9. **Endpoint v4** : accepte hotel inline.
10. **Image handling** : intégration `media_sideload_image` + fallback base64 + meta SEO.
11. **Test bout-en-bout final** sur 1-2 URLs réelles fournies par l'utilisateur.

---

## 6. Idempotence et ré-import

À chaque import réussi, le serveur stocke sur le camp créé :

- `_import_source_url` (postmeta) = URL exacte fournie en input
- `_import_imported_at` (postmeta) = timestamp Unix

**Ré-import du même URL :**

```
POST /import-camp avec import_source_url déjà présent en BDD
   ├─ force_overwrite = false  → HTTP 409 DUPLICATE_IMPORT
   │                              + existing_camp_id + existing_edit_url
   │
   └─ force_overwrite = true   → met à jour le camp existant
                                  • met à jour méta + relations
                                  • images : compare par URL source ;
                                    importe uniquement les nouvelles
                                  • ne casse JAMAIS la slug WP (gardée)
                                  • met à jour _import_imported_at
```

Côté client (moi), si je reçois un 409, je signale clairement à l'utilisateur et lui propose de relancer avec `force_overwrite: true`.

---

## 7. Gestion des images

### 7.1 Découverte (Playwright, côté client)

Collecter toutes les sources d'image rencontrées sur la page rendue :

- `<img src>` et `<img srcset>` (prendre la plus grande résolution disponible)
- `<picture><source srcset>`
- `<meta property="og:image">` et `<meta name="twitter:image">`
- CSS `background-image` (via `getComputedStyle` exécuté dans Playwright)

### 7.2 Filtrage sémantique (LLM, côté client)

Pour chaque image candidate, décision basée sur :

- Dimensions natives
- Position dans le DOM (header / footer / sidebar / main)
- Classes CSS et IDs (`.logo`, `.icon`, `.social-share`, `.avatar-default`)
- Alt text et nom de fichier
- **Inspection visuelle** (capacité vision de Claude)

**Rejeté automatiquement :**

- Logos (header, footer, partenaires)
- Icônes sociales et favicons
- Pixels de tracking (<50×50)
- Drapeaux de langue
- Avatars Gravatar par défaut
- Patterns décoratifs / SVG icon sets
- Photos sans rapport avec le camp/coach/spot

**Classification par rôle :**

| Rôle               | Description                                              |
|--------------------|----------------------------------------------------------|
| `coach_portrait`   | Photo où le coach est sujet principal                    |
| `coach_action`     | Coach pratiquant le sport                                |
| `spot_landscape`   | Vue du spot, plage, conditions                           |
| `camp_hero`        | Photo principale du camp (utilisée comme featured image) |
| `camp_group`       | Photos de groupes de riders au camp                      |
| `camp_activity`    | Activités annexes (yoga, repas, sortie)                  |
| `hotel_exterior`   | Photo extérieure de l'hébergement                        |
| `hotel_room`       | Chambre / suite                                          |
| `hotel_amenity`    | Piscine, terrasse, restaurant                            |
| `unclassified`     | Relevant mais catégorie incertaine                       |

Si plus de 15 images candidates restent après filtrage, Claude présente la classification à l'utilisateur avant d'enchaîner (pour permettre une exclusion manuelle des cas limites).

### 7.3 Optimisation (ImageMagick, côté client)

Pour chaque image gardée :

1. Téléchargement local (`curl` avec User-Agent navigateur)
2. Redimensionnement : **2000px max** sur le côté le plus long (préserve aspect ratio)
3. Compression :
   - JPEG : qualité 85, `mozjpeg` si dispo
   - PNG avec transparence : garder PNG, optimiser via `pngquant`
   - PNG sans transparence : convertir en JPEG
4. Strip EXIF (garder uniquement l'orientation)
5. Cible de poids : <500 KB typique, <1 MB pour les images héros

### 7.4 Renommage SEO

**Pattern :** `{role}-{slug-context}-ridemaster-{descriptor}-{seq}.{ext}`

Exemples concrets pour un camp "Tarifa Kite Camp June 2026" :

| Rôle         | Filename                                                          |
|--------------|-------------------------------------------------------------------|
| camp_hero    | `camp-tarifa-kite-june-2026-ridemaster-hero.jpg`                 |
| camp_action  | `camp-tarifa-kite-june-2026-ridemaster-action-kitejump-01.jpg`   |
| camp_group   | `camp-tarifa-kite-june-2026-ridemaster-group-sunset-02.jpg`      |
| coach_portrait | `coach-john-doe-ridemaster-portrait.jpg`                       |
| coach_cover  | `coach-john-doe-ridemaster-cover.jpg`                            |
| spot_landscape | `spot-tarifa-spain-ridemaster-overview.jpg`                    |
| hotel_exterior | `hotel-hurricane-tarifa-ridemaster-exterior.jpg`               |
| hotel_room   | `hotel-hurricane-tarifa-ridemaster-room-01.jpg`                  |

**Règles du slug :**

- lowercase, ASCII (translittération si caractères accentués)
- tirets uniquement, jamais d'underscore
- suppression de stop words (the, a, in, of, and…)
- max 70 caractères avant l'extension
- en cas de conflit avec un fichier WP existant : suffixe `-2`, `-3`…

### 7.5 Métadonnées SEO (côté client, transmises au serveur)

Pour chaque image, Claude génère et transmet :

- `alt` : description factuelle de ce qui est visible + mot-clé contextuel
  - Exemple : "Kitesurfer doing a jump at Tarifa beach during sunset, June 2026"
- `title` : version courte et lisible
  - Exemple : "Kite jump — Tarifa Camp"

Stockés côté WP via :

- `_wp_attachment_image_alt` (postmeta) ← alt
- `post_title` ← title

### 7.6 Upload (serveur, dans l'endpoint)

Pour chaque image du payload :

```
SI item.url existe ET HEAD request retourne 200 :
   attachment_id = media_sideload_image(item.url, $camp_id, item.alt, 'id')
   SI WP_Error :
      ajouter à warnings, signaler au client pour fallback base64
SINON SI item.base64 existe :
   attachment_id = wp_upload_bits(item.filename, null, base64_decode(item.base64))
   créer attachment via wp_insert_attachment + wp_generate_attachment_metadata

SI succès :
   wp_update_post(attachment_id, ['post_title' => item.title])
   update_post_meta(attachment_id, '_wp_attachment_image_alt', item.alt)

   SI role == camp_hero : _thumbnail_id du camp = attachment_id
   SINON                : append à _product_image_gallery (CSV)
```

WordPress génère automatiquement les variantes (thumbnail, medium, large) à partir de l'original optimisé. Le rendu front utilise déjà srcset par défaut.

---

## 8. Gestion d'erreur et rollback

### 8.1 Pseudo-transaction

WordPress n'offre pas de vraie transaction sur tous les hooks (notamment JetEngine), mais on s'en approche en gardant en mémoire ce qui est créé À CETTE INVOCATION :

```php
$created = [
    'user_id'    => null,
    'coach_id'   => null,
    'spot_id'    => null,
    'hotel_id'   => null,
    'camp_id'    => null,
    'attachment_ids' => [],
    'relations'  => [],  // [['rel_id' => 20, 'parent' => x, 'child' => y], ...]
];
```

À chaque étape, on push dans `$created` ce qui est neuf (jamais ce qui pré-existait).

### 8.2 Rollback en cas d'erreur

Ordre inverse :

1. Supprimer relations JetEngine créées
2. `wp_delete_attachment($id, true)` pour chaque attachment_id créé
3. `wp_delete_post($camp_id, true)`
4. `wp_delete_post($hotel_id, true)` si créé
5. `wp_delete_post($spot_id, true)` si créé
6. `wp_delete_post($coach_id, true)` si créé
7. `wp_delete_user($user_id)` si créé

**Ne JAMAIS toucher** à un coach/spot/hotel qui pré-existait : on a juste tenté de le lier, donc on délie seulement la relation.

### 8.3 Cas spécial : `STRIPE_BLOCKER`

Ce n'est pas une erreur bloquante. Le plugin principal force le camp en `draft` avec meta `_rm_blocked_reason = 'stripe_not_connected'` quand le coach n'a pas Stripe configuré. L'import réussit, mais on remonte un warning explicite + le camp_id pour que l'utilisateur sache où aller.

---

## 9. Sécurité

### 9.1 Authentification

Application Password WordPress (Basic auth via HTTPS). Stocké côté Claude comme variable d'env, jamais en clair dans la conversation après usage initial.

### 9.2 Permissions

Endpoint refuse toute requête sans `manage_options` ou `edit_others_posts`.

### 9.3 Validation des inputs

- Tous les champs du payload passent par `sanitize_*()` et type-checking
- URLs validées via `wp_http_validate_url()` avant tout fetch côté serveur
- `media_sideload_image()` whitelist les types MIME via `wp_check_filetype_and_ext()`
- Pas d'exécution d'HTML brut : `wp_kses_post()` sur `description_html`, `bio`, etc.

### 9.4 Limites de taille

- Payload JSON max : 10 MB (gère le base64 fallback pour ~5-6 images)
- Si plus d'images en fallback base64 nécessaires : Claude split en plusieurs requêtes (créer le camp d'abord, puis appels suivants `/import-camp/{id}/attach-image`)

### 9.5 SSRF protection

`media_sideload_image()` côté serveur télécharge depuis n'importe quelle URL fournie par le client. Pour limiter le risque SSRF :

- Bloquer les schemes non-http(s)
- Bloquer les IPs privées (10.x, 192.168.x, 172.16-31.x, 127.x, ::1)
- Timeout strict (10s)
- User-Agent identifiable

---

## 10. Environnement de test et sécurité

Pendant la phase pré-lancement (cf [section 2](#2-contexte-projet-utile)), le développement et les tests se font directement sur le site live. Conditions :

- Tous les camps de test sont préfixés `[TEST]` dans le titre pour permettre un nettoyage facile
- Un backup complet existe et est restaurable rapidement
- Chaque étape du refactor (cf [section 5.3](#53-ordre-de-refactor-strangler-pattern)) est testée avant la suivante :
  - Création d'un camp via le formulaire JFB existant : doit fonctionner inchangé
  - Inline-edit : doit fonctionner inchangé
  - Hook `shutdown` de forçage stock : doit fonctionner inchangé
  - Blocker Stripe : doit fonctionner inchangé
  - Nouvelle méthode `create_from_payload` : produit les mêmes méta qu'un camp créé via JFB
- Comparaison méta-clés en BDD via REST API ou WP-CLI entre un camp créé via JFB et un camp créé via la nouvelle méthode — doivent être identiques (sauf timestamps évidents)
- En cas de doute ou de comportement inattendu : ne pas avancer, débugger, et si nécessaire restaurer le backup

**Avant chaque go-live d'une étape du refactor :**

1. Faire un test JFB end-to-end manuel
2. Inspecter postmeta du camp créé
3. Si tout est OK → commit + push
4. Sinon → revert et investigation

---

## 11. Test final (acceptance)

Avant de considérer l'outil terminé :

1. **Claude (auto)** : importer 1 camp de test depuis un site coach simple → vérifier dans WP admin :
   - Camp visible, brouillon ou publié correctement
   - Toutes les méta présentes (price, dates, included, etc.)
   - Featured image + gallery affichées
   - Images correctement renommées et optimisées dans `wp-content/uploads/`
   - Coach lié correctement (relation JetEngine ID 20 présente)
   - Spot lié (relation JetEngine ID 18)
   - Hôtel lié si extrait
2. **Utilisateur (Xavier)** : importer 1-2 vrais camps depuis des sites coach réels → vérifier visuellement le rendu sur le frontend Ridemaster + valider la qualité de l'extraction
3. Si OK → le plugin est prêt à être utilisé pour le seeding initial

---

## 12. Évolutions futures (hors scope de ce spec)

Documentées ici pour ne pas être oubliées mais explicitement **pas** dans la version 1 :

- **Page d'admin WordPress** « Importer depuis URL » qui appellerait l'API Anthropic directement. Nécessite une clé API Anthropic séparée du Max sub (paiement par appel). À envisager si la commodité utilisateur le justifie.
- **Exposition de l'opération comme `Ability` WordPress** (cf MCP adapter mergé dans WP 6.9). Permettrait à tout outil MCP-aware (Claude Desktop, Cursor) de déclencher un import sans passer par Claude Code.
- **Détection de doublons par similarité de contenu** (au-delà de l'URL source).
- **Batch import** : passer un sitemap ou une liste d'URLs et tout importer.
- **Synchronisation continue** : surveiller des sites coach et mettre à jour les camps quand le site source change.

---

## Annexe A — Méta-clés Ridemaster à respecter

Référence rapide (la source de vérité reste [class-camp.php](../../../plugins/ridemaster/includes/class-camp.php)) :

### Camp (post_type = product)

| Meta key                  | Type            | Notes                                      |
|---------------------------|-----------------|--------------------------------------------|
| `_price`, `_regular_price`| string/number   | Doivent être identiques                    |
| `_stock`                  | number          | Capacité                                   |
| `_manage_stock`           | string          | Toujours `'yes'`                           |
| `_stock_status`           | string          | `'instock'` ou `'outofstock'`              |
| `camp_start_date`         | string          | Format `YYYY-MM-DD`                        |
| `camp_end_date`           | string          | Idem                                       |
| `full_date`               | number          | Unix timestamp du start                    |
| `full_date__end_date`     | number          | Unix timestamp du end                      |
| `full_date__config`       | JSON string     | `{"dates":[{"start":TS,"end":TS}]}`        |
| `camp_schedule`           | string          | Texte ou HTML                              |
| `camp_included`           | array of objects| `[{"included_in_the_camp":"x"}, ...]`      |
| `camp_not_included`       | array of objects| `[{"not_included_in_the_camp":"x"}, ...]`  |
| `_product_image_gallery`  | string          | CSV d'attachment IDs                       |
| `_thumbnail_id`           | number          | Featured image attachment ID               |
| `_coach_post_id`          | number          | ID du coach CPT (redondant avec relation)  |
| `_hotel_id`               | number          | Optional                                   |
| `_yoast_wpseo_focuskw`    | string          | Focus keyword Yoast SEO                    |
| `_yoast_wpseo_metadesc`   | string          | Meta description Yoast SEO                 |
| `_import_source_url`      | string          | **Nouveau** — pour idempotence             |
| `_import_imported_at`     | number          | **Nouveau** — timestamp d'import           |

### Taxonomies sur product (camp)

- `product_type` = `simple`
- `product_cat` = `camp` (obligatoire, term_id=55)
- `sport` ∈ `kitesurf` | `parakite` | `wingfoil` (term IDs : 21, 57, 22)
- `level` ∈ `beginner` | `intermediate` | `advanced` | `expert` (23-26)
- `language` ∈ `english` | `french` | `german` | `italian` | `portuguese` | `spanish` (27-32)
- `camp-status` ∈ `open` | `full` | `cancelled` (36, 37, 38) — défaut import : `open`

### Taxonomies sur coach

- `sport` (mêmes valeurs que ci-dessus)
- `language` (mêmes valeurs)
- `coach-status` ∈ `pending` | `validated` | `suspended` (33, 34, 35) — défaut import : `validated`
- ❗ `level` n'est PAS associé à coach

### Taxonomies sur spot

- `sport` (mêmes valeurs)
- `level` (mêmes valeurs)
- `water-type` ∈ `flat-water` | `waves` | `choppy` | `mixed` (39, 41, 40, 42)
- ❗ `language` n'est PAS associé à spot

### Coach : WP user + CPT post

- Rôle WP : **`coach_role`** (custom, défini par le plugin ridemaster)
- Liaison : `_coach_post_id` (postmeta sur coach CPT) ↔ `coach_post_id` (usermeta sur WP user)
- ❗ Le champ `post_author` du coach CPT n'est PAS utilisé pour la liaison

### Relations JetEngine (`wp_jet_rel_default`)

| `rel_id` | Parent      | Child       | Type        | Quand                                  |
|----------|-------------|-------------|-------------|----------------------------------------|
| 20       | coach CPT   | camp product| one-to-many | Toujours                               |
| 19       | coach CPT   | spot CPT    | many-to-many| Auto si coach + spot tous deux liés    |
| 18       | spot CPT    | camp product| one-to-many | Toujours                               |

---

## Annexe B — Audit live 2026-05-27

Audit réalisé sur `https://ridemaster.eu` via WP REST API + Application Password. Source de vérité au moment du design ; à re-vérifier si > 3 mois.

### Versions et environnement

- WordPress : 6.9+ (Abilities API présente à `/wp-json/wp-abilities/v1`)
- Timezone : Europe/Paris
- Plugins REST exposés : `wc/v3`, `jet-engine/v2`, `jet-form-builder/v1`, `ridemaster/v1`, `yoast/v1`, `elementor/v1`, `jetpack/v4`, `complianz/v1`, `matomo/v1`, `siteground-optimizer/v1`, `sg-security/v1`, …
- Hosting : SiteGround (présence des namespaces `siteground-*` et `sg-*`)

### CPT enregistrés

| Slug      | rest_base | Display name   | Notes                          |
|-----------|-----------|----------------|--------------------------------|
| `coach`   | coach     | Coaches        | Singulier dans slug, pluriel UI|
| `spot`    | spot      | Spots          |                                |
| `hotel`   | hotel     | Accommodation  |                                |
| `product` | product   | Products (WC)  | Filtré par `product_cat=camp`  |

### État de la BDD au moment de l'audit

- 4 utilisateurs WP (3 avec `coach_role`, 1 admin, 1 customer)
- 8 coachs CPT (Xavier Kain id=189, Val Garat 390, Chris Macdonald 392, Philippe Ancelin 441, Ben Beholz 453, Mike MacDonald 464, Marco Koppel 2158, Pierre Dupont 2259)
- 6 spots (Tarifa 195, Fuerteventura 394, Port Barcares 395, Cape Town 452, Egypt 2161, Mayapo 2275)
- 3 hôtels (Casa Solea 2293, Hotel Tarifa 2382, "HOtel 45" 2387)
- 2 camps existants (Coaching Mayapo Colombie 2279, Parakite in the Egyptian desert 2164)

### Endpoints REST `ridemaster/v1` existants

- `POST /ridemaster/v1/guest-upload`    (défini dans class-auth.php)
- `POST /ridemaster/v1/stripe-webhook`  (défini dans class-payments.php)
- `POST /ridemaster/v1/import-camp`     ← **nouveau, à créer**

### Limite REST observée

Les méta-clés business (`_price`, `camp_start_date`, `camp_included`, etc.) ne sont **pas exposées** via `GET /wp-json/wp/v2/product/{id}?context=edit` car non enregistrées avec `register_post_meta(..., show_in_rest=true)`. Seules les méta Elementor remontent. Cela **confirme** la nécessité d'un endpoint custom côté plugin pour l'import (le serveur peut lire/écrire toutes les méta en PHP direct, contournant cette limite).

### Sources de vérité pour les énumérations de termes

Les listes de slugs en [Annexe A](#annexe-a--méta-clés-ridemaster-à-respecter) reflètent l'état au 2026-05-27. Si un nouveau sport ou statut est ajouté en BDD entre-temps, l'endpoint d'import doit **valider** que le slug fourni existe (`get_term_by('slug', $slug, $taxonomy)`) et renvoyer `INVALID_PAYLOAD` avec la liste des slugs valides en cas d'inconnu — pas créer un nouveau term à la volée.
