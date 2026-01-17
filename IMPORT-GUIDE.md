# Guide d'Import des Templates Elementor

Ce guide explique comment importer et configurer les templates Elementor JSON générés pour RideMaster.

## Table des matières

1. [Fichiers Générés](#fichiers-générés)
2. [Prérequis](#prérequis)
3. [Import des Templates](#import-des-templates)
4. [Configuration Post-Import](#configuration-post-import)
5. [Dépannage](#dépannage)

---

## 1. Fichiers Générés

Les templates suivants ont été générés dans le dossier `elementor-templates/` :

### Templates Principaux

1. **`homepage-full.json`** - Template de la page d'accueil
   - Type: `page`
   - Contient: Hero, Sport Categories, Featured Camps

2. **`camp-card-loop-item.json`** - Template pour les cartes de camp
   - Type: `loop-item`
   - Utilisé par: JetEngine Listing Grid

3. **`camp-detail-single.json`** - Template pour la page détail d'un camp
   - Type: `single`
   - Post Type: `camp`

### Fichiers de Référence

- **`ELEMENTOR-REFERENCE.md`** - Documentation technique complète
- **`IMPORT-GUIDE.md`** - Ce guide

---

## 2. Prérequis

Avant d'importer les templates, vérifiez que vous avez :

### Plugins Requis

✅ **Elementor Pro** (version 3.34.0 ou supérieure)
✅ **JetEngine** (pour les CPT et dynamic tags)
✅ **WordPress** (version 6.9 ou supérieure)

### Configuration Préalable

1. **Site Kit Elementor** déjà configuré avec:
   - Global Colors (Primary, Secondary, rm_primary_*, rm_slate_*, etc.)
   - Global Typography (rm_display, rm_hero_title, rm_card_title, etc.)

2. **Custom Post Types JetEngine** créés:
   - `camp` avec tous les meta fields (camp_price, camp_start_date, etc.)
   - `coach` avec meta fields
   - `spot` avec meta fields

3. **Taxonomies** créées:
   - `sport` (shared: camp, coach, spot)
   - `level` (shared: camp, spot)
   - `camp-status`, `coach-status`, `water-type`, `language`

> **Note:** Si ces éléments ne sont pas déjà configurés, référez-vous au fichier `full-config.json` et `elementor-site-kit-export-2026-01-17.json` pour la configuration exacte.

---

## 3. Import des Templates

### Méthode 1: Import via Elementor (Recommandé)

#### Étape 1: Importer le Loop Item (Camp Card)

1. Allez dans **Elementor > Templates > Saved Templates**
2. Cliquez sur **Import Templates**
3. Sélectionnez le fichier **`camp-card-loop-item.json`**
4. Cliquez sur **Import Now**
5. Une fois importé, notez l'**ID du template** (visible dans l'URL d'édition)
   - Exemple: `https://ridemaster.eu/wp-admin/post.php?post=123&action=elementor` → ID = `123`

> **Important:** Vous aurez besoin de cet ID pour configurer le Loop Grid dans la homepage.

#### Étape 2: Importer la Homepage

1. Créez une nouvelle page (ou éditez une existante) pour la homepage
2. Ouvrez l'éditeur Elementor
3. Cliquez sur l'icône **dossier** (Import/Export) en bas à gauche
4. Sélectionnez **Import Template**
5. Choisissez le fichier **`homepage-full.json`**
6. Le template sera importé dans la page actuelle

#### Étape 3: Configurer le Loop Grid

Après import de la homepage :

1. Dans l'éditeur, localisez le widget **JetEngine Listing Grid** (section "Featured Camps")
2. Dans le panneau de gauche, configurez :
   - **Listing ID**: Sélectionnez le template "Camp Card - Loop Item" importé à l'étape 1
   - **Query Source**: `Custom Query`
   - **Custom Query**:
     ```
     Post Type: camp
     Posts per page: 6
     Order by: Date
     Order: DESC
     ```
3. Sauvegardez la page

#### Étape 4: Importer le Camp Detail (Single Template)

1. Allez dans **Elementor > Templates > Theme Builder**
2. Cliquez sur **Add New** → **Single**
3. Donnez un nom: "Camp Detail Template"
4. Dans l'éditeur, cliquez sur l'icône **dossier** → **Import Template**
5. Sélectionnez **`camp-detail-single.json`**
6. Le template sera importé
7. Dans les **Settings** (bas à gauche) :
   - **Preview Settings** → Post Type: `camp`
   - Sélectionnez un camp de test pour prévisualiser
8. **Publish** et configurez les conditions d'affichage :
   - **Include** → Singular → Post → `camp`

---

### Méthode 2: Import Programmatique (Avancé)

Si vous préférez importer via code PHP :

```php
// Dans functions.php ou un plugin custom
function ridemaster_import_templates() {
    // Vérifier que Elementor est actif
    if (!did_action('elementor/loaded')) {
        return;
    }

    // Import du Camp Card Loop Item
    $loop_item_file = get_template_directory() . '/elementor-templates/camp-card-loop-item.json';
    $loop_item_data = json_decode(file_get_contents($loop_item_file), true);

    $loop_item_id = \Elementor\Plugin::instance()->templates_manager->import_template([
        'fileData' => base64_encode(json_encode($loop_item_data)),
        'fileName' => 'camp-card-loop-item.json'
    ]);

    // Stocker l'ID pour utilisation ultérieure
    update_option('ridemaster_camp_card_template_id', $loop_item_id);

    // Import de la homepage (similaire)
    // Import du single template (similaire)
}
add_action('after_setup_theme', 'ridemaster_import_templates');
```

---

## 4. Configuration Post-Import

### A. Vérifier les Dynamic Tags

Après import, vérifiez que les dynamic tags fonctionnent correctement :

1. **Éditez le Loop Item "Camp Card"**
2. Vérifiez les champs dynamiques :
   - Image → Featured Image (dynamic tag actif)
   - Titre → Post Title (dynamic tag actif)
   - Prix → JetEngine Meta Field `camp_price` (dynamic tag actif)
   - Date → JetEngine Meta Field `camp_start_date` (dynamic tag actif)
   - Taxonomies → JetEngine Taxonomy `sport`, `level` (dynamic tags actifs)

3. **Testez avec un camp réel** :
   - Créez un camp de test avec toutes les données
   - Prévisualisez la homepage pour voir si les données s'affichent

### B. Ajuster les Couleurs et Typographies

Si les Global Colors/Typography ne s'appliquent pas automatiquement :

1. Ouvrez chaque template dans l'éditeur
2. Pour chaque widget, vérifiez les paramètres de couleur/typo
3. Si une valeur hex est affichée au lieu de `var(--e-global-color-xxx)` :
   - Cliquez sur le sélecteur de couleur
   - Sélectionnez l'onglet **Global**
   - Choisissez la couleur globale correspondante

### C. Configurer la Search Bar (Homepage)

Le template homepage contient un placeholder pour la search bar. Pour la remplacer :

1. **Option 1: Utiliser JetSearch**
   - Installez **JetSearch** (plugin JetPlugins)
   - Créez un Search Widget dans JetSearch
   - Remplacez le widget HTML placeholder par le JetSearch Widget

2. **Option 2: Utiliser Elementor Search**
   - Supprimez le widget HTML placeholder
   - Ajoutez le widget **Search Form** d'Elementor Pro
   - Configurez les champs: Location, Sport, Level, Dates

3. **Option 3: Garder le placeholder**
   - Laissez le placeholder en attendant l'implémentation
   - Le design est préservé pour développement ultérieur

### D. Ajouter les Données de Test

Pour tester complètement les templates :

1. **Créez au moins 6 camps** avec :
   - Featured Image
   - Title, Excerpt
   - Meta fields: price, dates, spots
   - Taxonomies: sport, level
   - Gallery images

2. **Créez quelques coaches et spots** (pour les relations)

3. **Testez la navigation** :
   - Homepage → Camp Card → Camp Detail
   - Vérifiez que tous les dynamic tags affichent les bonnes données

---

## 5. Dépannage

### Problème 1: "Template import failed"

**Solution:**
- Vérifiez que vous avez **Elementor Pro** installé (pas seulement Elementor Free)
- Vérifiez la version d'Elementor (minimum 3.34.0)
- Essayez d'augmenter `upload_max_filesize` et `post_max_size` dans php.ini

### Problème 2: Les Dynamic Tags n'affichent rien

**Causes possibles:**

1. **JetEngine non configuré correctement**
   - Vérifiez que les meta fields existent (JetEngine → Meta Boxes)
   - Vérifiez les slugs: `camp_price`, `camp_start_date`, etc.

2. **Mauvais nom de champ**
   - Dans le dynamic tag, vérifiez le paramètre `key`
   - Exemple: `%7B%22key%22%3A%22camp_price%22%7D` décodé = `{"key":"camp_price"}`
   - Le slug doit correspondre exactement au slug dans JetEngine

3. **Pas de données dans le post**
   - Créez un camp de test avec toutes les données remplies
   - Utilisez les Preview Settings pour sélectionner ce camp

### Problème 3: Les couleurs ne correspondent pas

**Solution:**
- Exportez votre Site Kit actuel : `Elementor > Site Settings > Export Site Kit`
- Comparez avec `elementor-site-kit-export-2026-01-17.json`
- Assurez-vous que les IDs de couleurs correspondent :
  - `rm_primary_600`, `rm_slate_500`, etc.
- Si nécessaire, ré-importez le Site Kit de référence

### Problème 4: Le Loop Grid est vide

**Vérifications:**

1. **Le Listing ID est-il défini ?**
   - Ouvrez le widget Listing Grid
   - Settings → Listing ID doit être sélectionné (le template Camp Card)

2. **La Query est-elle correcte ?**
   - Post Type: `camp` (pas `post`)
   - Posts Num: 6 (ou autre nombre)
   - Vérifiez qu'il y a des camps publiés

3. **Le Loop Item Template est-il publié ?**
   - Allez dans Elementor > Templates
   - Vérifiez que "Camp Card - Loop Item" a le statut **Publish**

### Problème 5: Responsive cassé

**Solution:**
- Les templates utilisent Flexbox Container d'Elementor
- Vérifiez que l'option **Flexbox Container** est activée :
  - Elementor > Settings > Features > Flexbox Container = Active
- Si vous voyez des messages d'erreur, activez cette feature expérimentale

---

## Notes Importantes

### Global Colors/Typography

Les templates utilisent **intensivement** les Global Colors et Typography. Si vous modifiez le Site Kit :

- ⚠️ **NE modifiez PAS** les IDs des couleurs/typos (ex: `rm_primary_600`)
- ✅ **Modifiez uniquement** les valeurs hex/font-size
- Les changements se propageront automatiquement à tous les templates

### JetEngine Listing Grid

Pour modifier l'affichage des camps :

1. **Pour modifier le design d'UNE card** → Éditez le template "Camp Card - Loop Item"
2. **Pour modifier le nombre/ordre** → Éditez les settings du Listing Grid dans la homepage
3. **Pour ajouter des filtres** → Installez JetSmartFilters et configurez les filtres

### Dynamic Tags: Format des Settings

Les dynamic tags utilisent un format URL-encoded. Pour décoder/encoder :

```javascript
// Décoder
decodeURIComponent('%7B%22key%22%3A%22camp_price%22%7D')
// Résultat: {"key":"camp_price"}

// Encoder
encodeURIComponent('{"key":"camp_price"}')
// Résultat: %7B%22key%22%3A%22camp_price%22%7D
```

---

## Prochaines Étapes

Après avoir importé les templates de base :

1. ✅ **Testez** avec des données réelles
2. ✅ **Ajustez** les espacements/tailles selon vos préférences
3. ✅ **Complétez** les sections manquantes (footer, autres pages)
4. ✅ **Implémentez** la search bar avec JetSearch
5. ✅ **Créez** les autres templates (coaches.html, spots.html, etc.)

---

## Support

Pour toute question :

1. Consultez **`ELEMENTOR-REFERENCE.md`** pour les détails techniques
2. Vérifiez la **documentation JetEngine** : https://crocoblock.com/knowledge-base/
3. Consultez la **documentation Elementor** : https://elementor.com/help/

---

**Dernière mise à jour:** 2026-01-17
