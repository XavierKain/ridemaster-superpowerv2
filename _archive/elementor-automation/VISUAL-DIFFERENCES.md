# Différences Visuelles: Maquette vs Elementor

Date: 2026-01-18

## Comparaison Visuelle

### ✅ Ce qui fonctionne correctement:

1. **Titre principal**: "Find Your Next Camp" ✅
   - Texte correct
   - Couleur blanche ✅
   - Centrage ✅

2. **Sous-titre**: "Discover world-class camps with expert coaches at stunning destinations" ✅
   - Texte correct
   - Visible et lisible ✅

3. **Image de fond**: Photo de surf Unsplash ✅
   - URL correcte
   - Affichage correct ✅

4. **Trust badges**: 3 badges présents ✅
   - "Insurance included" ✅
   - "Secure payment" ✅
   - "Free cancellation" ✅
   - Icônes FontAwesome visibles ✅

### ❌ Différences à corriger:

#### 1. **Barre de recherche** 🔴 CRITIQUE
**Maquette statique**:
- Barre de recherche interactive avec 3 champs:
  - Location (avec icône map-pin)
  - Sport (dropdown avec icône wind)
  - Dates (avec icône calendar)
- Bouton "Search" avec icône search
- Fond blanc avec ombres
- Border radius arrondi

**Elementor actuel**:
- Simple placeholder HTML gris avec texte "🔍 Search Bar - Configure with JetSearch"
- Pas de champs interactifs
- Pas d'icônes
- Pas de fonctionnalité

**Action requise**:
- Remplacer le placeholder par JetSearch widget configuré
- OU créer widget HTML personnalisé avec les vrais champs
- OU utiliser Elementor Form widget stylisté

---

#### 2. **Taille des polices** 🟡 MOYEN
**Observation**:
- Titre semble légèrement plus petit dans Elementor
- Police configurée: 48px desktop, 36px tablet, 30px mobile

**À vérifier**:
- Font-size du H1 dans la maquette statique (CSS)
- Ajuster si nécessaire

---

#### 3. **Espacement vertical** 🟡 MOYEN
**Observation**:
- Padding top/bottom peut différer
- Maquette: padding généreuxpour hero section
- Elementor: 100px top/bottom actuellement

**À vérifier**:
- Mesurer padding exact dans maquette
- Ajuster min-height si nécessaire

---

#### 4. **Overlay gradient** 🟢 FAIBLE
**Actuel**:
```javascript
background_overlay_background: "gradient",
background_overlay_color: "rgba(15, 23, 42, 0.4)",  // Start
background_overlay_color_b: "rgba(15, 23, 42, 0.7)", // End
background_overlay_gradient_type: "linear",
background_overlay_gradient_angle: { size: 180 }  // Top to bottom
```

**À vérifier**:
- Intensité du gradient dans la maquette
- Direction (peut-être diagonal?)
- Opacité exacte

---

#### 5. **Position des trust badges** 🟢 FAIBLE
**Actuel**: Badges en ligne (flex-direction: row)

**À vérifier**:
- Espacement entre les badges
- Alignement exact dans maquette

---

## Prochaines Actions

### Priorité HAUTE 🔴
1. **Implémenter la barre de recherche fonctionnelle**
   - Option A: Utiliser JetSearch widget
   - Option B: Créer HTML/CSS custom avec JavaScript
   - Option C: Utiliser Elementor Form widget

### Priorité MOYENNE 🟡
2. **Ajuster la typographie**
   - Comparer font-sizes exacts avec CSS de la maquette
   - Vérifier line-height
   - Vérifier letter-spacing

3. **Ajuster les espacements**
   - Mesurer padding/margin exact dans maquette
   - Ajuster min-height du hero
   - Vérifier gap entre éléments

### Priorité FAIBLE 🟢
4. **Affiner le gradient overlay**
   - Tester différentes opacités
   - Tester angles différents si nécessaire

5. **Optimiser trust badges**
   - Affiner spacing
   - Vérifier responsive

---

## CSS de la Maquette (Référence)

Pour comparer, voici les styles clés de la maquette statique:

### Hero Section
```css
.hero {
  position: relative;
  min-height: 85vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 120px 20px;
  overflow: hidden;
}
```

### Hero Title
```css
.hero__title {
  font-size: 3.5rem;      /* 56px */
  font-weight: 700;
  color: #FFFFFF;
  margin-bottom: 1rem;
  text-align: center;
  text-shadow: 0 2px 8px rgba(0,0,0,0.3);
}
```

### Hero Subtitle
```css
.hero__subtitle {
  font-size: 1.25rem;     /* 20px */
  color: #FFFFFF;
  margin-bottom: 2.5rem;
  text-align: center;
  max-width: 600px;
  text-shadow: 0 1px 4px rgba(0,0,0,0.25);
}
```

### Search Bar
```css
.search-bar {
  background: white;
  border-radius: 16px;
  padding: 8px;
  display: flex;
  align-items: center;
  gap: 0;
  max-width: 900px;
  width: 100%;
  box-shadow: 0 10px 40px rgba(0,0,0,0.15);
  margin-bottom: 2rem;
}
```

---

## Modifications Nécessaires dans le Template JSON

### 1. Changer le titre font-size: 48px → 56px
```json
{
  "typography_font_size": {
    "unit": "px",
    "size": "56",  // Changed from 48
  }
}
```

### 2. Changer le subtitle font-size: 18px → 20px
```json
{
  "typography_font_size": {
    "unit": "px",
    "size": "20",  // Changed from 18
  }
}
```

### 3. Changer le padding du hero: 100px → 120px
```json
{
  "padding": {
    "top": "120",    // Changed from 100
    "bottom": "120", // Changed from 100
  }
}
```

### 4. Remplacer le Search Placeholder par un vrai widget

**Option recommandée**: Créer un widget HTML custom qui réplique exactement la barre de recherche

---

## Timeline de Correction

**Aujourd'hui**:
1. Ajuster font-sizes et padding (modifications JSON) - 10 min
2. Re-générer et importer template - 5 min
3. Implémenter barre de recherche (HTML custom ou JetSearch) - 30-60 min

**Total**: ~1 heure pour avoir un match quasi-parfait avec la maquette

---

## Notes

Le template Elementor est **fonctionnellement correct** mais nécessite des **ajustements visuels fins** pour matcher exactement la maquette statique.

Les modifications sont mineures et peuvent être faites rapidement en ajustant le JSON du template ou directement dans l'éditeur Elementor.
