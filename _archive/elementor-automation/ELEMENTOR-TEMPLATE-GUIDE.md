# Guide de Création de Templates Elementor via REST API

**Date de création:** 2026-01-19
**Dernière mise à jour:** 2026-01-19

**Basé sur:**

- 10 itérations du template Homepage Hero
- Template Camp Card Loop Item v1

**Contexte:** Génération automatique de templates Elementor JSON et import via WordPress REST API

---

## Table des matières

1. [Structure JSON Elementor](#structure-json-elementor)
2. [❌ Ce qui NE FONCTIONNE PAS](#-ce-qui-ne-fonctionne-pas)
3. [✅ Ce qui FONCTIONNE](#-ce-qui-fonctionne)
4. [Best Practices](#best-practices)
5. [Globals Elementor](#globals-elementor)
6. [Checklist de Validation](#checklist-de-validation)

---

## Structure JSON Elementor

### Format de base

```json
{
  "version": "0.4",
  "title": "Template Name",
  "type": "page",
  "content": [
    {
      "id": "unique-id",
      "elType": "container",
      "isInner": false,
      "settings": { ... },
      "elements": [ ... ]
    }
  ],
  "page_settings": {}
}
```

### Hiérarchie des éléments

```
container (isInner: false) - Container principal
  ├─ widget (heading, text-editor, icon, html)
  ├─ container (isInner: true) - Sous-container
  │   ├─ widget
  │   └─ widget
  └─ widget
```

---

## ❌ Ce qui NE FONCTIONNE PAS

### 1. Meta Fields REST API

**❌ ERREUR:**
```javascript
meta: {
  _elementor_page_settings: JSON.stringify([])  // ❌ STRING
}
```

**Symptôme:** Erreur 400 - "meta._elementor_page_settings is not of type object"

**Pourquoi ça ne marche pas:** L'API WordPress attend un objet, pas une string JSON.

---

### 2. Icon-Box Widgets sans Configuration Propre

**❌ PROBLÈME:**
```json
{
  "widgetType": "icon-box",
  "settings": {
    "selected_icon": { ... },
    "title_text": "Text",
    // ❌ Ajoute automatiquement des backgrounds/borders indésirables
  }
}
```

**Symptôme:** Widgets icon-box ajoutent des fonds blancs, bordures, padding non désirés

**Pourquoi ça ne marche pas:** Le widget icon-box a trop de styles par défaut difficiles à neutraliser.

---

### 3. HTML Inline au lieu de Widgets Natifs

**❌ MAUVAISE PRATIQUE:**
```json
{
  "widgetType": "html",
  "settings": {
    "html": "<div><svg>...</svg><span>Text</span></div>"
  }
}
```

**Problèmes:**
- Pas de support des globals Elementor
- Difficile à maintenir
- Pas responsive automatiquement
- Pas éditable via l'interface Elementor

---

### 4. Containers Nested avec Mauvais Alignement

**❌ ERREUR:**
```json
{
  "elType": "container",
  "settings": {
    "content_width": "full",  // ❌ Mais on veut 800px boxed
    "flex_align_items": "center"  // ❌ Tout centré au lieu de left
  }
}
```

**Symptôme:** Contenu centré alors que la maquette demande un alignement à gauche.

---

### 5. Trust Badges avec flex-wrap: wrap

**❌ ERREUR:**
```json
{
  "flex_wrap": "wrap"  // ❌ Les badges passent à la ligne
}
```

**Symptôme:** Les 3 badges ne restent pas sur la même ligne horizontale.

---

### 6. Valeurs en Dur au lieu de Globals

**❌ MAUVAISE PRATIQUE:**
```json
{
  "settings": {
    "title_color": "#0D9488",  // ❌ Valeur en dur
    "typography_font_size": { "size": "48" }  // ❌ Pas lié aux globals
  }
}
```

**Problèmes:**
- Incohérence avec le reste du site
- Pas de mise à jour automatique si les globals changent
- Difficile à maintenir

---

### 7. Rééditer la Même Page

**❌ NE FONCTIONNE PAS:**
```javascript
await page.goto(`${wpAdminURL}/post.php?post=${EXISTING_PAGE_ID}&action=edit`);
// ❌ Template library ne s'ouvre pas correctement
```

**Symptôme:** Timeout lors de l'ouverture de la template library sur une page existante.

**Solution:** Créer une NOUVELLE page à chaque import pour éviter les problèmes d'état.

---

## ✅ Ce qui FONCTIONNE

### 1. Meta Fields REST API - Format Correct

**✅ CORRECT:**
```javascript
meta: {
  _elementor_data: JSON.stringify(templateData.content),  // ✅ String OK
  _elementor_page_settings: {},  // ✅ Object (pas string!)
  _elementor_template_type: 'page'  // ✅ String OK
}
```

---

### 2. Container Boxed avec Max-Width

**✅ CORRECT:**
```json
{
  "elType": "container",
  "settings": {
    "content_width": "boxed",
    "width": {
      "unit": "px",
      "size": "800",
      "sizes": []
    },
    "flex_align_items": "flex-start",  // ✅ Left aligned
    "flex_justify_content": "center"  // ✅ Vertical center
  }
}
```

**Résultat:** Container de 800px max-width, contenu aligné à gauche, centré verticalement.

---

### 3. Trust Badges avec Container Blanc + Widgets Natifs

**✅ ARCHITECTURE CORRECTE:**

```json
{
  "id": "trust-badges-container",
  "elType": "container",
  "isInner": true,
  "settings": {
    "flex_direction": "row",
    "flex_wrap": "nowrap",  // ✅ Tout sur une ligne
    "flex_justify_content": "flex-start",
    "flex_gap": { "column": "32", "row": "32", "unit": "px" },
    "background_background": "classic",
    "background_color": "#FFFFFF",  // ✅ Fond blanc
    "border_radius": { "unit": "px", "top": "12", ... },
    "box_shadow_box_shadow_type": "yes",
    "box_shadow_box_shadow": {
      "horizontal": 0,
      "vertical": 4,
      "blur": 20,
      "spread": 0,
      "color": "rgba(0, 0, 0, 0.08)"
    },
    "_padding": {
      "top": "20",
      "right": "32",
      "bottom": "20",
      "left": "32",
      "unit": "px"
    }
  },
  "elements": [
    {
      "id": "badge-1-container",
      "elType": "container",
      "isInner": true,
      "settings": {
        "flex_direction": "row",
        "flex_gap": { "column": "8", "row": "8", "unit": "px" },
        "background_color": "transparent",  // ✅ Pas de fond
        "border_border": "none"  // ✅ Pas de bordure
      },
      "elements": [
        {
          "elType": "widget",
          "widgetType": "icon",  // ✅ Widget natif Elementor
          "settings": {
            "selected_icon": {
              "value": "fas fa-shield-alt",
              "library": "fa-solid"
            },
            "primary_color": "#0D9488",
            "size": { "unit": "px", "size": 18 },
            "__globals__": {
              "primary_color": "globals/colors?id=primary"  // ✅ Global
            }
          }
        },
        {
          "elType": "widget",
          "widgetType": "text-editor",  // ✅ Widget natif
          "settings": {
            "editor": "<p style=\"margin: 0; padding: 0;\">Insurance included</p>",
            "text_color": "#475569",
            "__globals__": {
              "text_color": "globals/colors?id=rm_slate_600",
              "typography_typography": "globals/typography?id=accent"
            }
          }
        }
      ]
    }
    // Répéter pour les autres badges...
  ]
}
```

**Pourquoi ça fonctionne:**
- Container parent avec fond blanc visible ✅
- Widgets natifs Elementor (icon + text-editor) ✅
- flex-wrap: nowrap pour garder tout sur une ligne ✅
- Globals pour couleurs et typographies ✅
- Pas de background/border sur les sous-containers ✅

---

### 4. Utilisation des Globals Elementor

**✅ CORRECT:**
```json
{
  "settings": {
    "title_color": "#FFFFFF",  // Valeur de fallback
    "typography_typography": "custom",
    "typography_font_size": { "size": "48" },
    "__globals__": {
      "typography_typography": "globals/typography?id=rm_display"
    }
  }
}
```

**Format des globals:**
- Couleurs: `"globals/colors?id=primary"` ou `"globals/colors?id=rm_slate_600"`
- Typographies: `"globals/typography?id=rm_display"` ou `"globals/typography?id=accent"`

**Globals disponibles sur Ridemaster:**

**System Colors:**
- `primary` → #0D9488 (Ocean Teal)
- `secondary` → #EAB308 (Gold)
- `text` → #1E293B (Dark Slate)
- `accent` → #F97316 (Sunset Orange)

**Custom Colors:**
- `rm_slate_600` → #475569
- `rm_slate_700` → #334155
- `rm_slate_800` → #1E293B
- `rm_primary_600` → #0D9488
- etc.

**System Typography:**
- `primary` → Headings (30px, 700)
- `secondary` → Subheadings (20px, 600)
- `text` → Body Text (16px, 400)
- `accent` → Accent Text (14px, 500)

**Custom Typography:**
- `rm_display` → Display 5XL (48px, 700)
- `rm_hero_title` → Hero Title 4XL (36px, 700)
- `rm_page_title` → Page Title 3XL (30px, 700)

---

### 5. Création de Nouvelle Page à Chaque Import

**✅ CORRECT:**
```javascript
await page.goto(`${CONFIG.wpAdminURL}/post-new.php?post_type=page`);
await page.waitForTimeout(5000);

const titleSelector = '.editor-post-title__input, #post-title-0, input[name="post_title"]';
await page.fill(titleSelector, 'Test Page Title').catch(async () => {
  await page.click(titleSelector);
  await page.keyboard.type('Test Page Title');
});
```

**Pourquoi ça fonctionne:** État propre, pas de conflits avec l'éditeur Elementor déjà chargé.

---

### 6. DOM Click pour Save Button

**✅ CORRECT:**
```javascript
const saved = await page.evaluate(() => {
  const publishBtn = document.querySelector('#elementor-panel-saver-button-publish');
  if (publishBtn) {
    publishBtn.click();
    return 'publish';
  }
  const saveBtn = document.querySelector('#elementor-panel-footer-sub-menu-item-save-draft');
  if (saveBtn) {
    saveBtn.click();
    return 'draft';
  }
  return null;
});
```

**Pourquoi ça fonctionne:** Bypass les problèmes de visibilité/viewport en cliquant directement via DOM.

---

### 7. Viewport Élargi

**✅ CORRECT:**
```javascript
const context = await browser.newContext({
  viewport: { width: 1920, height: 1400 }  // ✅ 1400px height pour voir les boutons footer
});
```

**Pourquoi ça fonctionne:** Boutons footer Elementor visibles, pas coupés par viewport trop petit.

---

## Best Practices

### 1. Toujours Utiliser des Widgets Natifs

**Priorité:**
1. `heading` pour les titres
2. `text-editor` pour le texte
3. `icon` pour les icônes seules
4. `html` SEULEMENT pour les placeholders temporaires

**Éviter:** `icon-box` (trop de styles par défaut)

---

### 2. Structure de Container

```
Container Principal (isInner: false)
  ├─ Widget direct (heading, text-editor)
  ├─ Container avec fond/style (isInner: true)
  │   └─ Sous-containers pour grouper (isInner: true)
  │       ├─ Widget icon
  │       └─ Widget text
  └─ Widget direct
```

---

### 3. Settings Essentiels pour Containers

**Container principal (Hero section):**
```json
{
  "content_width": "boxed",
  "width": { "unit": "px", "size": "800" },
  "flex_direction": "column",
  "flex_align_items": "flex-start",  // Left align
  "min_height": { "unit": "vh", "size": "80" },
  "background_background": "classic",
  "background_image": { "url": "..." },
  "background_overlay_background": "gradient"
}
```

**Container avec fond blanc (Trust badges):**
```json
{
  "flex_direction": "row",
  "flex_wrap": "nowrap",
  "flex_gap": { "column": "32", "row": "32", "unit": "px" },
  "background_background": "classic",
  "background_color": "#FFFFFF",
  "border_radius": { "unit": "px", "top": "12", ... },
  "box_shadow_box_shadow_type": "yes",
  "_padding": { "top": "20", "right": "32", ... }
}
```

**Sous-container transparent (Badge individuel):**
```json
{
  "flex_direction": "row",
  "flex_gap": { "column": "8", "row": "8", "unit": "px" },
  "background_background": "classic",
  "background_color": "transparent",
  "border_border": "none",
  "_padding": { "top": "0", "right": "0", ... },
  "_margin": { "top": "0", "right": "0", ... }
}
```

---

### 4. Margins et Padding

**Toujours spécifier explicitement:**
```json
{
  "_margin": {
    "top": "0",
    "right": "0",
    "bottom": "24",
    "left": "0",
    "unit": "px",
    "isLinked": false
  },
  "_padding": {
    "top": "0",
    "right": "0",
    "bottom": "0",
    "left": "0",
    "unit": "px",
    "isLinked": true
  }
}
```

**Valeurs de spacing cohérentes:**
- `8px` - Gap entre icon et texte
- `16px` - Padding mobile
- `20px` - Padding vertical container
- `24px` - Margin bottom titre
- `32px` - Gap entre badges, padding horizontal, margin bottom subtitle

---

### 5. Responsive Design

**Toujours définir 3 breakpoints:**
```json
{
  "typography_font_size": { "size": "48" },  // Desktop
  "typography_font_size_tablet": { "size": "36" },  // Tablet
  "typography_font_size_mobile": { "size": "30" }  // Mobile
}
```

```json
{
  "padding": { "top": "120", ... },  // Desktop
  "padding_tablet": { "top": "80", ... },  // Tablet
  "padding_mobile": { "top": "60", ... }  // Mobile
}
```

---

### 6. Ordre de Développement

1. **Créer le JSON du template**
   - Structure containers
   - Ajouter widgets natifs
   - Configurer settings avec valeurs en dur

2. **Ajouter les globals**
   - Remplacer couleurs par `__globals__`
   - Remplacer typographies par `__globals__`

3. **Tester l'import**
   - Créer template via REST API
   - Insérer dans nouvelle page
   - Valider le rendu frontend

4. **Itérer si nécessaire**
   - Comparer avec maquette statique
   - Ajuster spacing/alignment
   - Re-importer et valider

---

## Globals Elementor

### Comment Extraire les Globals

```javascript
const globals = await page.evaluate(() => {
  return {
    colors: window.elementorCommon?.config?.globals?.colors || {},
    typography: window.elementorCommon?.config?.globals?.typography || {}
  };
});
```

**Alternative:** Lire le fichier `elementor-site-kit-export.json` :
```javascript
const siteKit = JSON.parse(fs.readFileSync('elementor-site-kit-export.json'));
const systemColors = siteKit.elementor.active_kit.kit_settings.system_colors;
const customColors = siteKit.elementor.active_kit.kit_settings.custom_colors;
const systemTypography = siteKit.elementor.active_kit.kit_settings.system_typography;
const customTypography = siteKit.elementor.active_kit.kit_settings.custom_typography;
```

---

### Format des Globals dans les Settings

**Dans `__globals__`:**
```json
{
  "settings": {
    "primary_color": "#0D9488",  // ✅ Valeur de fallback
    "__globals__": {
      "primary_color": "globals/colors?id=primary"  // ✅ Référence global
    }
  }
}
```

**IMPORTANT:**
- Toujours garder la valeur en dur comme fallback
- Le `__globals__` override la valeur en dur si le global existe
- Format: `"globals/{type}?id={id}"`
  - type: `colors` ou `typography`
  - id: l'identifiant du global (ex: `primary`, `rm_slate_600`)

---

## Templates Loop Item

### Spécificités des Loop Item

Les **Loop Item templates** sont utilisés avec le widget **Loop Grid** d'Elementor Pro pour afficher des listes de posts/CPT.

**Type de template:** `"type": "loop-item"`

**Différences avec les templates Page:**

1. **Placeholders dynamiques** : Utiliser `{{field_name}}` pour les champs dynamiques
2. **Structure fixe** : Pas de variations de layout, une seule carte
3. **Pas de Loop Grid imbriqué** : Ne pas mettre de Loop Grid dans un Loop Item

### Exemple Camp Card Loop Item

```json
{
  "version": "0.4",
  "title": "Camp Card - Loop Item v1",
  "type": "loop-item",
  "content": [
    {
      "id": "camp-card-main",
      "elType": "container",
      "settings": {
        "background_color": "#FFFFFF",
        "border_radius": { "unit": "px", "top": "12", ... },
        "box_shadow_box_shadow_type": "yes"
      },
      "elements": [
        // Image wrapper avec aspect ratio 4:3
        {
          "id": "image-wrapper",
          "elType": "container",
          "settings": {
            "height": { "size": "0" },
            "padding_bottom": { "unit": "%", "size": "75" }
          }
        },
        // Content avec title, location, dates, rating, footer
      ]
    }
  ]
}
```

### Best Practices Loop Items

1. **Aspect Ratio avec padding-bottom**
   - `height: 0` + `padding_bottom: 75%` = ratio 4:3
   - `height: 0` + `padding_bottom: 100%` = ratio 1:1
   - Image en `position: absolute` inside

2. **Placeholders pour Dynamic Data**
   - Image: `"url": "{{featured_image_url}}"`
   - Texte: Laisser exemple statique, remplacer en Dynamic Tags

3. **Badges positionnés en absolute**
   - Container parent: `position: relative`
   - Badges: `_position: absolute`, `_offset_x/y`

4. **Footer avec separator**
   - `border-top: 1px solid`
   - `padding-top` pour spacing

---

## Checklist de Validation

### Avant Import

- [ ] JSON valide (pas d'erreurs de syntaxe)
- [ ] Tous les containers ont `elType: "container"`
- [ ] Tous les widgets ont `widgetType` valide
- [ ] `isInner: false` seulement sur le container principal
- [ ] `_elementor_page_settings` est un objet `{}`
- [ ] Tous les globals utilisent le format `globals/colors?id=...`
- [ ] Margins et padding explicitement définis
- [ ] Responsive défini (desktop, tablet, mobile)

### Après Import

- [ ] Template créé avec succès (status 201)
- [ ] Template visible dans la library Elementor
- [ ] Template s'insère correctement dans la page
- [ ] Page se sauvegarde sans erreur
- [ ] Frontend affiche tous les éléments
- [ ] Trust badges sur UNE seule ligne (si applicable)
- [ ] Container blanc visible (si applicable)
- [ ] Icônes avec la bonne couleur
- [ ] Texte avec la bonne couleur
- [ ] Responsive fonctionne (tester mobile/tablet)

### Comparaison avec Maquette

- [ ] Alignement (left, center, right) correct
- [ ] Spacing (margins, paddings, gaps) correct
- [ ] Couleurs exactement identiques
- [ ] Typographies (size, weight, line-height) correctes
- [ ] Background/overlay correct
- [ ] Border-radius correct
- [ ] Box-shadow correct

---

## Troubleshooting

### Template créé mais éléments manquants

**Cause:** Structure JSON incorrecte, nested containers mal configurés

**Solution:** Vérifier que `isInner: true` sur tous les sous-containers

---

### Trust badges passent à la ligne

**Cause:** `flex-wrap: "wrap"` sur le container parent

**Solution:** `flex-wrap: "nowrap"`

---

### Trust badges ont des fonds/bordures indésirables

**Cause:** Utilisation de `icon-box` widget ou backgrounds non neutralisés

**Solution:**
- Utiliser `icon` + `text-editor` widgets séparément
- Sur sous-containers: `"background_color": "transparent"`, `"border_border": "none"`

---

### Globals ne s'appliquent pas

**Cause:** Format incorrect du global ID ou global inexistant

**Solution:** Vérifier que le global existe dans le site kit et utiliser le bon format `globals/colors?id=rm_slate_600`

---

### Save button timeout

**Cause:** Viewport trop petit, bouton hors de l'écran

**Solution:**
- Viewport minimum 1920x1400
- Utiliser DOM click via `page.evaluate()`

---

## Conclusion

**Leçons Clés:**

1. **Toujours utiliser des widgets natifs Elementor** (heading, text-editor, icon)
2. **Structure en containers nested** avec fond blanc sur le parent, transparent sur les enfants
3. **flex-wrap: nowrap** pour garder les éléments sur une ligne
4. **Toujours utiliser les globals** pour couleurs et typographies
5. **Créer une nouvelle page** à chaque import (pas rééditer une existante)
6. **DOM click** pour les boutons Elementor
7. **Viewport élargi** (1920x1400) pour visibilité

**Résultat:** Templates production-ready, maintenables, cohérents avec le design system du site.

---

**Versions du Hero Template:**
- v1-v4: Échecs (centrés, HTML inline, mauvais widgets)
- v5: Line-height corrigé
- v6: Nested containers (ne rendaient pas)
- v7: HTML inline (fonctionne mais pas maintenable)
- v8: Widgets natifs (fonctionne !)
- v9: Container blanc + badges sur une ligne (quasi parfait !)
- v10: **FINAL** - Avec globals Elementor ✅

**Template final (v10):**
- ✅ Widgets natifs Elementor
- ✅ Container blanc avec shadow
- ✅ Trust badges sur une ligne
- ✅ Globals pour couleurs et typos
- ✅ 100% conforme à la maquette
- ✅ Production-ready
