# Elementor Templates v3 - Option B + D

Cette version des templates utilise l'approche **Option B + D** :
- **Option B** : Structure avec spécifications en commentaires HTML
- **Option D** : Templates dynamiques complets avec JetEngine dynamic tags

## 📁 Fichiers Inclus

### Templates de Structure (Option B)

Ces templates contiennent la structure de base avec toutes les spécifications de design en commentaires HTML détaillés :

1. **01-homepage-hero-structure.json**
   - Section Hero de la homepage
   - Contient : Titre, sous-titre, placeholder pour search bar, trust badges
   - Specs complètes pour : couleurs, typographies, espacements, images, gradients

2. **02-sport-categories-structure.json**
   - Section des catégories de sports
   - 5 cards : Kitesurf, Wingfoil, Surf, Paraglide, Sailing
   - Specs pour : gradients, emojis, layout responsive, scroll horizontal

### Templates Dynamiques Complets (Option D)

Ces templates sont prêts à l'emploi avec tous les JetEngine dynamic tags configurés :

3. **03-camp-card-loop-item.json**
   - Template pour les cards de camps (Loop Item)
   - Dynamic tags configurés pour :
     - Image featured
     - Badges (sport taxonomy, level field)
     - Titre, location, dates
     - Rating et reviews count
     - Coach avatar et nom
     - Prix
   - Prêt pour utilisation dans JetEngine Listing Grid

4. **04-camp-detail-single.json**
   - Template pour la page single de camp
   - Structure 2 colonnes (contenu + booking card sticky)
   - Dynamic tags pour :
     - Galerie d'images
     - Titre, badges, meta informations
     - Description
     - What's Included (repeater)
     - Prix et booking button
   - Booking card sticky dans la colonne de droite

## 🚀 Comment Utiliser

### Templates de Structure (01, 02)

1. **Importer le template** dans Elementor (Templates > Saved Templates > Import)

2. **Trouver le widget HTML avec les specs** :
   - Il sera le premier élément du template
   - Contient toutes les spécifications de design

3. **Appliquer les styles manuellement** :
   - Ouvrez le widget dans l'éditeur Elementor
   - Utilisez les specs comme référence
   - Copiez-collez les valeurs (couleurs, tailles, espacements)
   - Pour les couleurs : utilisez les globals (rm_primary_500, etc.) ou HEX directs
   - Pour les typographies : utilisez rm_hero_title, rm_lead_text, etc.

4. **Supprimer le widget HTML de specs** une fois le styling terminé

**Avantages** :
- Toutes les valeurs exactes sont documentées
- Vous gardez le contrôle sur le styling
- Vous pouvez adapter selon vos besoins
- Gain de temps : ~50% (pas besoin de mesurer dans le HTML/CSS)

### Templates Dynamiques (03, 04)

1. **Importer le template** dans Elementor

2. **Configurer comme Loop Item / Single Template** :
   - Pour 03 : Templates > Theme Builder > Loop Item > New
   - Pour 04 : Templates > Theme Builder > Single > New
   - Assigner à "Camp" post type

3. **Vérifier les dynamic tags** :
   - Ouvrez chaque widget
   - Vérifiez que les noms de fields correspondent à votre JetEngine setup
   - Ajustez si nécessaire (par exemple si votre field s'appelle "camp_price" au lieu de "price")

4. **Tester** :
   - Créez un camp test avec toutes les données
   - Prévisualisez le template
   - Ajustez les couleurs/espacements si nécessaire

**Important** : Ces templates utilisent des noms de fields standards. Si vos fields ont des noms différents, vous devrez les mapper :

| Template utilise | Votre field pourrait être |
|------------------|---------------------------|
| `price` | `camp_price` |
| `location` | `camp_location` |
| `start_date` | `date_start` |
| `coach_name` | Relation → coach → title |

## 🎨 Référence des Styles Globaux

### Couleurs (Site Kit)

```
rm_primary_500     → #14B8A6 (Teal principal)
rm_primary_600     → #0D9488 (Teal hover)
rm_slate_900       → #0F172A (Texte foncé)
rm_slate_700       → #334155 (Texte secondaire)
rm_slate_600       → #475569 (Texte tertiaire)
e-global-color-ffffff → #FFFFFF (Blanc)
```

### Typographies (Site Kit)

```
rm_hero_title      → DM Sans 700, 48px/36px/30px
rm_display         → DM Sans 700, 36px+
rm_heading         → DM Sans 600, 24px
rm_lead_text       → DM Sans 400, 18px/16px
rm_body            → DM Sans 400, 16px
rm_small           → DM Sans 400, 14px
```

## 🔧 Personnalisation

### Modifier les couleurs

**Méthode 1 - Globals (recommandé)** :
1. Allez dans Elementor > Site Settings > Global Colors
2. Modifiez les valeurs des couleurs rm_*
3. Tous les templates se mettent à jour automatiquement

**Méthode 2 - Direct** :
1. Ouvrez le widget
2. Style > Color
3. Entrez la valeur HEX directement

### Modifier les typographies

1. Elementor > Site Settings > Global Fonts
2. Modifiez rm_hero_title, rm_lead_text, etc.
3. Ou appliquez directement dans chaque widget

### Ajouter des éléments

Les templates de structure (01, 02) sont là pour ça ! Ajoutez vos widgets et stylez-les selon les specs.

## 📝 Notes Importantes

### Search Bar (Homepage Hero)

Le placeholder HTML doit être remplacé par un **JetSearch widget** :

1. Ajoutez le widget JetSearch
2. Configurez les champs :
   - Location (text input)
   - Sport (select from taxonomy)
   - Level (select from field)
   - Dates (date picker)
3. Stylez le widget selon les specs dans le commentaire

### Listing Grid (Featured Camps)

Pour afficher les camps featured :

1. Ajoutez un **JetEngine Listing Grid** widget
2. Query :
   - Post Type : camp
   - Posts Per Page : 3 ou 6
   - Tax Query : Featured = yes (si vous avez ce field)
3. Loop Item : Sélectionnez le template 03-camp-card-loop-item
4. Columns : 3 (desktop), 2 (tablet), 1 (mobile)

### Booking Card Sticky

Le booking card dans le template 04 utilise `position: sticky`. Pour que ça fonctionne :

1. Le container parent doit avoir une hauteur définie
2. Le `top` doit être défini (100px par défaut pour passer sous le header)
3. Testez le scroll sur une vraie page avec du contenu

## 🐛 Troubleshooting

### Les couleurs ne s'appliquent pas

- Vérifiez que les globals sont définis dans Site Settings
- Sinon, utilisez les valeurs HEX directes

### Les dynamic tags sont vides

- Vérifiez que le field existe dans JetEngine
- Vérifiez l'orthographe exacte du field name
- Vérifiez que le post a des données dans ce field

### Le layout est cassé sur mobile

- Ouvrez l'éditeur responsive (icône mobile/tablet)
- Ajustez les flex_gap, padding, font_size pour mobile
- Les specs incluent les valeurs responsive

### Les images ne s'affichent pas

- Vérifiez que le post a une featured image
- Pour la galerie : vérifiez que le field "gallery" existe et contient des images
- Vérifiez les permissions d'accès aux médias

## 💡 Conseils

1. **Commencez par les templates dynamiques (03, 04)** - ils sont prêts à l'emploi
2. **Utilisez les templates de structure (01, 02)** comme référence visuelle pendant que vous stylez
3. **Créez des posts de test** avec toutes les données pour voir le rendu final
4. **Exportez vos templates** une fois stylés pour les réutiliser
5. **Documentez vos field names** si différents des standards

## 📚 Ressources

- [ELEMENTOR-REFERENCE.md](../ELEMENTOR-REFERENCE.md) - Référence complète des couleurs, typographies, et structure JetEngine
- [IMPORT-GUIDE.md](../IMPORT-GUIDE.md) - Guide d'importation détaillé

## ✅ Checklist de Mise en Place

- [ ] Importer 03-camp-card-loop-item.json
- [ ] Créer un Loop Item template avec 03
- [ ] Importer 04-camp-detail-single.json
- [ ] Créer un Single template avec 04
- [ ] Assigner les templates au post type "camp"
- [ ] Créer 2-3 camps de test avec toutes les données
- [ ] Tester la Loop Grid sur la homepage
- [ ] Tester la page single d'un camp
- [ ] Importer 01-homepage-hero-structure.json
- [ ] Styler le hero selon les specs
- [ ] Configurer JetSearch pour la barre de recherche
- [ ] Importer 02-sport-categories-structure.json
- [ ] Styler les sport cards selon les specs
- [ ] Tester le responsive sur mobile/tablet

Bon courage ! 🚀
