# 🚀 Guide de Déploiement - RideMaster Design

## 📦 Ce qu'il faut déployer

Vous devez copier **TOUT le contenu** du dossier `ridemaster-design` sur votre serveur FTP.

### Structure à déployer

```
ridemaster-design/
├── css/                    ← OBLIGATOIRE : Tous les fichiers CSS
│   ├── base.css
│   ├── components.css
│   ├── layout.css
│   ├── pages.css
│   ├── reset.css
│   └── tokens.css
│
├── js/                     ← OBLIGATOIRE : Fichiers JavaScript
│   └── main.js
│
├── *.html                  ← OBLIGATOIRE : Tous les fichiers HTML
│   ├── index.html
│   ├── camps.html
│   ├── camp-detail.html
│   ├── coaches.html
│   ├── coach-profile.html
│   ├── spots.html
│   ├── spot-detail.html
│   ├── booking-checkout.html
│   ├── login.html
│   ├── register-rider.html
│   ├── register-coach.html
│   ├── dashboard-rider.html
│   └── dashboard-coach.html
│
└── Documentation            ← OPTIONNEL : Vous pouvez ne pas les copier
    ├── README.md
    ├── NAVIGATION-FIXES.md
    ├── TESTS-NAVIGATION.md
    ├── MODIFICATIONS-FINALES.md
    ├── GUIDE-DEPLOIEMENT.md
    └── start-server.sh

```

## 📋 Instructions de déploiement FTP

### Étape 1 : Préparer les fichiers

**NE PAS COPIER :**
- ❌ `start-server.sh`
- ❌ `server.log`
- ❌ Fichiers `.md` (documentation) - optionnel
- ❌ `.DS_Store` (fichiers Mac)

**OBLIGATOIRE À COPIER :**
- ✅ Dossier `css/` complet
- ✅ Dossier `js/` complet
- ✅ Tous les fichiers `.html`

### Étape 2 : Connexion FTP

1. Connectez-vous à votre serveur FTP
2. Naviguez vers le dossier racine de votre site (généralement `/public_html/` ou `/www/`)

### Étape 3 : Upload des fichiers

**Option A - Tout déployer (recommandé) :**
1. Sélectionnez tous les fichiers SAUF ceux listés dans "NE PAS COPIER"
2. Glissez-déposez dans votre client FTP
3. Attendez la fin de l'upload

**Option B - Déploiement manuel :**
1. Créez le dossier `css/` sur le serveur
2. Uploadez tous les fichiers `.css` dans `/css/`
3. Créez le dossier `js/` sur le serveur
4. Uploadez `main.js` dans `/js/`
5. Uploadez tous les fichiers `.html` à la racine

### Étape 4 : Vérification

Une fois l'upload terminé, ouvrez votre site dans un navigateur :

```
https://votre-domaine.com/
```

**Vérifiez que :**
- ✅ La page d'accueil s'affiche correctement
- ✅ Les styles CSS sont chargés (pas de texte brut)
- ✅ Les cartes de camps sont cliquables
- ✅ La navigation fonctionne
- ✅ Les liens dashboard dans le footer fonctionnent

## 🔧 Structure FTP finale

Votre serveur devrait ressembler à ceci :

```
/public_html/  (ou /www/)
├── css/
│   ├── base.css
│   ├── components.css
│   ├── layout.css
│   ├── pages.css
│   ├── reset.css
│   └── tokens.css
│
├── js/
│   └── main.js
│
├── index.html
├── camps.html
├── camp-detail.html
├── coaches.html
├── coach-profile.html
├── spots.html
├── spot-detail.html
├── booking-checkout.html
├── login.html
├── register-rider.html
├── register-coach.html
├── dashboard-rider.html
└── dashboard-coach.html
```

## ⚠️ Points d'attention

1. **Chemins relatifs** : Tous les liens utilisent des chemins relatifs, donc peu importe si vous déployez à la racine ou dans un sous-dossier

2. **Pas de sous-dossier "ridemaster-design"** :
   - ❌ INCORRECT : `votre-domaine.com/ridemaster-design/index.html`
   - ✅ CORRECT : `votre-domaine.com/index.html`

   OU si vous voulez un sous-dossier :
   - ✅ CORRECT : `votre-domaine.com/nom-du-dossier/index.html`

3. **Permissions** : Assurez-vous que les fichiers HTML ont les permissions 644 (lecture pour tous)

4. **Cache navigateur** : Après le déploiement, videz le cache de votre navigateur (Ctrl+F5 ou Cmd+Shift+R)

## ✅ Checklist de déploiement

- [ ] Tous les fichiers `.html` copiés
- [ ] Dossier `css/` complet copié
- [ ] Dossier `js/` copié
- [ ] Page d'accueil accessible
- [ ] Styles CSS chargés
- [ ] Navigation fonctionnelle
- [ ] Cartes cliquables
- [ ] Dashboards accessibles depuis le footer

## 🆘 Dépannage

**Problème : Les styles ne s'affichent pas**
- Vérifiez que le dossier `css/` est bien au même niveau que `index.html`
- Vérifiez les permissions des fichiers CSS (644)

**Problème : Les liens ne fonctionnent pas**
- Vérifiez que tous les fichiers `.html` sont bien uploadés
- Vérifiez qu'il n'y a pas de majuscules dans les noms de fichiers

**Problème : Images cassées**
- Normal ! Les images utilisent des URLs externes (Unsplash)
- Si vous voulez vos propres images, créez un dossier `images/` et modifiez les liens

## 📞 Support

Si vous avez des problèmes, vérifiez :
1. Les fichiers sont bien au bon endroit
2. Les permissions sont correctes
3. Le cache du navigateur est vidé
