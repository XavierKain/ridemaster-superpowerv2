# Onboarding Coach - Inline Edit avec Placeholders

## Decisions
- Option 2: Tout en inline (pas de formulaire separé)
- Mode edit automatique quand profil vide
- Placeholders descriptifs/instructifs par champ
- Banniere de bienvenue pour les nouveaux profils

## Changements

### PHP
- Ajouter `placeholder` a chaque champ dans `$field_config`
- Calculer `isProfileEmpty` (pas de bio ET pas de featured image)
- Passer le flag + placeholders au JS via wp_localize_script

### JS
- Auto `enterEditMode()` si `config.isProfileEmpty`
- Banniere de bienvenue injectee en haut
- Tous les placeholders utilisent `config.fields[fieldName].placeholder`
- Fallback editor WYSIWYG utilise le placeholder specifique

### CSS
- Style banniere de bienvenue
- Zone placeholder pour images vides (bordure pointillee)
