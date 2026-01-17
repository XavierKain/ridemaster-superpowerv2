# RideMaster - Référence Elementor

Documentation technique pour la génération des templates Elementor JSON du projet RideMaster.

## Table des matières

1. [Configuration Globale](#configuration-globale)
2. [Structure JetEngine](#structure-jetengine)
3. [Widgets Mapping](#widgets-mapping)
4. [Dynamic Tags](#dynamic-tags)
5. [Exemples de Code](#exemples-de-code)

---

## 1. Configuration Globale

### Global Colors

Les couleurs globales sont définies dans le Kit Elementor (ID: 121 - "RideMaster Site Kit").

#### System Colors

```json
{
  "primary": "#0D9488",        // Ocean Teal
  "secondary": "#EAB308",      // Gold
  "text": "#1E293B",           // Text
  "accent": "#F97316"          // Sunset Orange
}
```

#### Custom Colors (sélection)

```json
{
  "rm_primary_50": "#F0FDFA",
  "rm_primary_100": "#CCFBF1",
  "rm_primary_600": "#0D9488",
  "rm_primary_700": "#0F766E",
  "rm_secondary_500": "#F97316",
  "rm_gold_500": "#EAB308",
  "rm_success": "#10B981",
  "rm_error": "#EF4444",
  "rm_warning": "#F59E0B",
  "rm_kitesurf": "#3B82F6",
  "rm_kitesurf_light": "#DBEAFE",
  "rm_wingfoil": "#8B5CF6",
  "rm_wingfoil_light": "#EDE9FE",
  "rm_slate_50": "#F8FAFC",
  "rm_slate_100": "#F1F5F9",
  "rm_slate_500": "#64748B",
  "rm_slate_800": "#1E293B",
  "rm_slate_900": "#0F172A"
}
```

### Global Typography

#### System Typography

```json
{
  "primary": {
    "_id": "primary",
    "title": "Headings",
    "typography_font_family": "DM Sans",
    "typography_font_weight": "700",
    "typography_font_size": { "unit": "px", "size": 30 },
    "typography_line_height": { "unit": "em", "size": 1.25 }
  },
  "secondary": {
    "_id": "secondary",
    "title": "Subheadings",
    "typography_font_family": "DM Sans",
    "typography_font_weight": "600",
    "typography_font_size": { "unit": "px", "size": 20 }
  },
  "text": {
    "_id": "text",
    "title": "Body Text",
    "typography_font_family": "DM Sans",
    "typography_font_weight": "400",
    "typography_font_size": { "unit": "px", "size": 16 }
  }
}
```

#### Custom Typography

- `rm_display` - Display (5XL) - 48px/36px/30px - 700
- `rm_hero_title` - Hero Title (4XL) - 36px/30px/24px - 700
- `rm_page_title` - Page Title (3XL) - 30px/24px/20px - 700
- `rm_section_title` - Section Title (2XL) - 24px/20px/18px - 700
- `rm_card_title` - Card Title (XL) - 20px/18px/16px - 600
- `rm_lead_text` - Lead Text (LG) - 18px/16px - 400
- `rm_body` - Body (Base) - 16px - 400
- `rm_body_medium` - Body Medium - 16px - 500
- `rm_body_semibold` - Body Semibold - 16px - 600
- `rm_small` - Small Text - 14px - 400
- `rm_caption` - Caption - 12px - 400

### Utilisation des Global Colors/Typography dans les widgets

```json
{
  "settings": {
    "title_color": "var(--e-global-color-primary)",
    "typography_typography": "custom",
    "typography_typography": "var(--e-global-typography-rm_hero_title)"
  }
}
```

---

## 2. Structure JetEngine

### Custom Post Types (CPT)

#### Camp (slug: `camp`)

**Meta Fields:**
- `camp_start_date` (date)
- `camp_end_date` (date)
- `camp_price` (number)
- `camp_currency` (select: EUR, USD, GBP)
- `camp_max_spots` (number)
- `camp_booked_spots` (number)
- `camp_included` (textarea)
- `camp_not_included` (textarea)
- `camp_schedule` (textarea)
- `camp_gallery` (gallery)

**Taxonomies:**
- `sport` (slug: `sport`) - shared with coach, spot
- `level` (slug: `level`) - shared with spot
- `camp-status` (slug: `camp-status`)

#### Coach (slug: `coach`)

**Meta Fields:**
- `coach_first_name` (text)
- `coach_last_name` (text)
- `coach_bio` (textarea)
- `coach_years_experience` (number)
- `coach_certifications` (textarea)
- `coach_rating_avg` (number)
- `coach_review_count` (number)

**Taxonomies:**
- `sport` (slug: `sport`)
- `language` (slug: `language`)
- `coach-status` (slug: `coach-status`)

#### Spot (slug: `spot`)

**Meta Fields:**
- `spot_country` (text)
- `spot_region` (text)
- `spot_location` (map)
- `spot_wind_direction` (text)
- `spot_best_season` (text)
- `spot_gallery` (gallery)

**Taxonomies:**
- `sport` (slug: `sport`)
- `level` (slug: `level`)
- `water-type` (slug: `water-type`)

### Relations JetEngine

1. **Coach to Camps** (one-to-many)
   - Parent: `posts::coach`
   - Child: `posts::camp`

2. **Spot to Camps** (one-to-many)
   - Parent: `posts::spot`
   - Child: `posts::camp`

3. **Coach to Spots** (many-to-many)
   - Parent: `posts::coach`
   - Child: `posts::spot`

---

## 3. Widgets Mapping

### HTML → Elementor Widgets

| HTML Element | Elementor Widget | Notes |
|--------------|------------------|-------|
| `<h1>`, `<h2>`, `<h3>` | `heading` | Utiliser `header_size` pour le tag HTML |
| `<p>` | `text-editor` | Pour du texte simple |
| `<img>` | `image` | Supporte les dynamic tags |
| `<a class="btn">` | `button` | Avec styles personnalisés |
| `<div class="camp-card">` | `container` + widgets | Ou Loop Grid pour listing |
| Hero background | `container` | Utiliser `background_image` |
| Icons (lucide) | `icon` | Ou utiliser Font Awesome |
| Star rating | `star-rating` | Widget natif Elementor |
| Gallery | `image-gallery` | Ou `gallery` pour lightbox |

### Containers Flex

Les containers Elementor utilisent Flexbox. Configuration typique :

```json
{
  "id": "abc12345",
  "elType": "container",
  "settings": {
    "content_width": "boxed",        // ou "full"
    "flex_direction": "column",      // ou "row"
    "flex_justify_content": "center",
    "flex_align_items": "center",
    "flex_gap": {
      "column": "24",
      "row": "24",
      "unit": "px"
    },
    "padding": {
      "top": "64",
      "right": "16",
      "bottom": "64",
      "left": "16",
      "unit": "px",
      "isLinked": false
    }
  },
  "elements": []
}
```

---

## 4. Dynamic Tags

### Format Dynamic Tag

Les dynamic tags utilisent le format suivant :

```
[elementor-tag id="UNIQUE_ID" name="TAG_NAME" settings="ENCODED_JSON"]
```

**Encodage des settings:**
```javascript
// Original
{"key": "camp_price"}

// URL encoded
"%7B%22key%22%3A%22camp_price%22%7D"
```

### Dynamic Tags JetEngine

#### Meta Field (JetEngine)

**Champ texte:**
```json
{
  "__dynamic__": {
    "title": "[elementor-tag id=\"abc12345\" name=\"jet-post-meta\" settings=\"%7B%22key%22%3A%22camp_price%22%7D\"]"
  }
}
```

**Champ image:**
```json
{
  "__dynamic__": {
    "image": "[elementor-tag id=\"def67890\" name=\"jet-post-thumbnail\" settings=\"%7B%7D\"]"
  }
}
```

#### Post Data

**Post Title:**
```json
{
  "__dynamic__": {
    "title": "[elementor-tag id=\"abc12345\" name=\"post-title\" settings=\"%7B%7D\"]"
  }
}
```

**Post Excerpt:**
```json
{
  "__dynamic__": {
    "editor": "[elementor-tag id=\"abc12345\" name=\"post-excerpt\" settings=\"%7B%7D\"]"
  }
}
```

**Featured Image:**
```json
{
  "__dynamic__": {
    "image": "[elementor-tag id=\"abc12345\" name=\"post-featured-image\" settings=\"%7B%7D\"]"
  }
}
```

#### Taxonomie

**Taxonomy Terms:**
```json
{
  "__dynamic__": {
    "text": "[elementor-tag id=\"abc12345\" name=\"jet-post-terms\" settings=\"%7B%22taxonomy%22%3A%22sport%22%2C%22separator%22%3A%22%2C%20%22%7D\"]"
  }
}
```

### JetEngine Listing Grid

Pour afficher une liste de posts :

```json
{
  "id": "abc12345",
  "elType": "widget",
  "widgetType": "jet-listing-grid",
  "settings": {
    "listing_id": "TEMPLATE_ID",
    "columns": "3",
    "columns_tablet": "2",
    "columns_mobile": "1",
    "posts_num": "6",
    "post_type": "camp",
    "tax_query": [],
    "meta_query": [],
    "order": "DESC",
    "orderby": "date"
  }
}
```

---

## 5. Exemples de Code

### Exemple 1: Hero Section

```json
{
  "id": "hero001",
  "elType": "container",
  "settings": {
    "content_width": "full",
    "flex_direction": "column",
    "flex_justify_content": "center",
    "flex_align_items": "center",
    "min_height": {
      "unit": "vh",
      "size": 85
    },
    "background_background": "classic",
    "background_image": {
      "url": "https://images.unsplash.com/photo-1502680390469-be75c86b636f?w=1920"
    },
    "background_position": "center center",
    "background_size": "cover",
    "background_overlay_background": "classic",
    "background_overlay_color": "rgba(0,0,0,0.4)",
    "padding": {
      "top": "100",
      "bottom": "100",
      "unit": "px"
    }
  },
  "elements": [
    {
      "id": "hero002",
      "elType": "widget",
      "widgetType": "heading",
      "settings": {
        "title": "Find Your Next Camp",
        "header_size": "h1",
        "align": "center",
        "title_color": "#FFFFFF",
        "typography_typography": "custom",
        "typography_font_size": {
          "unit": "px",
          "size": 48,
          "sizes": []
        },
        "typography_font_size_tablet": {
          "unit": "px",
          "size": 36
        },
        "typography_font_size_mobile": {
          "unit": "px",
          "size": 30
        }
      }
    }
  ]
}
```

### Exemple 2: Camp Card avec Dynamic Tags

```json
{
  "id": "card001",
  "elType": "container",
  "settings": {
    "flex_direction": "column",
    "background_background": "classic",
    "background_color": "#FFFFFF",
    "border_radius": {
      "top": "12",
      "right": "12",
      "bottom": "12",
      "left": "12",
      "unit": "px",
      "isLinked": true
    },
    "box_shadow_box_shadow_type": "yes",
    "box_shadow_box_shadow": {
      "horizontal": 0,
      "vertical": 2,
      "blur": 8,
      "spread": 0,
      "color": "rgba(0,0,0,0.08)"
    }
  },
  "elements": [
    {
      "id": "card002",
      "elType": "widget",
      "widgetType": "image",
      "settings": {
        "__dynamic__": {
          "image": "[elementor-tag id=\"img001\" name=\"post-featured-image\" settings=\"%7B%7D\"]"
        },
        "image_size": "medium_large"
      }
    },
    {
      "id": "card003",
      "elType": "widget",
      "widgetType": "heading",
      "settings": {
        "header_size": "h3",
        "__dynamic__": {
          "title": "[elementor-tag id=\"ttl001\" name=\"post-title\" settings=\"%7B%7D\"]"
        },
        "typography_typography": "var(--e-global-typography-rm_card_title)"
      }
    },
    {
      "id": "card004",
      "elType": "widget",
      "widgetType": "text-editor",
      "settings": {
        "__dynamic__": {
          "editor": "[elementor-tag id=\"txt001\" name=\"jet-post-meta\" settings=\"%7B%22key%22%3A%22camp_price%22%7D\"]"
        }
      }
    }
  ]
}
```

### Exemple 3: Loop Grid pour Camps

```json
{
  "id": "loop001",
  "elType": "widget",
  "widgetType": "jet-listing-grid",
  "settings": {
    "listing_id": "CAMP_CARD_TEMPLATE_ID",
    "columns": "3",
    "columns_tablet": "2",
    "columns_mobile": "1",
    "columns_gap": "24",
    "rows_gap": "24",
    "posts_num": "6",
    "post_type": "camp",
    "use_custom_post_types": "true",
    "custom_post_types": ["camp"],
    "order": "DESC",
    "orderby": "date",
    "show_title": "no",
    "show_excerpt": "no",
    "show_meta": "no"
  }
}
```

---

## Génération d'ID Uniques

Pour générer des IDs Elementor (8 caractères hexadécimaux) :

```javascript
function generateElementorId() {
  return Math.random().toString(16).substr(2, 8);
}
```

En PHP :
```php
substr(md5(uniqid(mt_rand(), true)), 0, 8)
```

---

## Notes Importantes

1. **Responsive Design** : Utiliser les suffixes `_tablet` et `_mobile` pour les valeurs responsive
2. **Global Colors** : Toujours utiliser `var(--e-global-color-XXX)` au lieu de valeurs hex en dur
3. **Global Typography** : Préférer les global typography aux styles inline
4. **Dynamic Tags** : L'ID dans le tag peut être n'importe quel string unique (8 chars hex recommandé)
5. **Container vs Section** : Toujours utiliser `container` (nouveau système Elementor), pas `section`
6. **Box Shadow** : Nécessite `box_shadow_box_shadow_type: "yes"` pour être actif

---

**Dernière mise à jour:** 2026-01-17
