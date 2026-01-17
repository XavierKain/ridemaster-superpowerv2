# ⚡ Déploiement Rapide - Mode d'emploi

## 🎯 En 3 étapes simples

### 1️⃣ Connectez-vous à votre FTP

Utilisez votre client FTP préféré (FileZilla, Cyberduck, etc.)

### 2️⃣ Copiez ces fichiers et dossiers

```
ridemaster-design/
├── 📁 css/              ← Tout le dossier
├── 📁 js/               ← Tout le dossier
└── 📄 Tous les .html    ← Les 13 fichiers
```

### 3️⃣ Collez dans votre serveur

**Destination :** Racine de votre site (généralement `/public_html/` ou `/www/`)

---

## 📦 Liste exacte des fichiers à copier

### Dossiers (2)
- ✅ `css/` avec tous ses fichiers (6 fichiers)
- ✅ `js/` avec tous ses fichiers (1 fichier)

### Fichiers HTML (13)
- ✅ `index.html`
- ✅ `camps.html`
- ✅ `camp-detail.html`
- ✅ `coaches.html`
- ✅ `coach-profile.html`
- ✅ `spots.html`
- ✅ `spot-detail.html`
- ✅ `booking-checkout.html`
- ✅ `login.html`
- ✅ `register-rider.html`
- ✅ `register-coach.html`
- ✅ `dashboard-rider.html`
- ✅ `dashboard-coach.html`

---

## ❌ NE PAS copier

- ❌ `start-server.sh`
- ❌ `server.log`
- ❌ Tous les fichiers `.md` (documentation)
- ❌ `.DS_Store`

---

## ✅ Résultat attendu

Après le déploiement, votre URL devrait être :

```
https://votre-domaine.com/index.html
```

ou simplement

```
https://votre-domaine.com/
```

---

## 🔍 Comment vérifier que ça marche ?

1. Ouvrez `https://votre-domaine.com/`
2. La page doit s'afficher avec les couleurs et le design
3. Cliquez sur une carte de camp → Doit aller vers camp-detail.html
4. Cliquez sur "Coaches" dans le menu → Doit afficher la liste des coachs
5. Scrollez en bas → Les liens "Rider Dashboard" et "Coach Dashboard" doivent être visibles

**Si tout fonctionne = Déploiement réussi ! 🎉**

---

## 📊 Récapitulatif visuel

```
VOTRE ORDINATEUR                 VOTRE SERVEUR FTP
═══════════════════             ═══════════════════

ridemaster-design/              /public_html/
│                                │
├── css/ ──────────────────────> ├── css/
├── js/ ───────────────────────> ├── js/
├── index.html ────────────────> ├── index.html
├── camps.html ────────────────> ├── camps.html
├── camp-detail.html ──────────> ├── camp-detail.html
├── coaches.html ──────────────> ├── coaches.html
├── coach-profile.html ────────> ├── coach-profile.html
├── spots.html ────────────────> ├── spots.html
├── spot-detail.html ──────────> ├── spot-detail.html
├── booking-checkout.html ─────> ├── booking-checkout.html
├── login.html ────────────────> ├── login.html
├── register-rider.html ───────> ├── register-rider.html
├── register-coach.html ───────> ├── register-coach.html
├── dashboard-rider.html ──────> ├── dashboard-rider.html
└── dashboard-coach.html ──────> └── dashboard-coach.html
```

---

## 💡 Astuce Pro

Si vous utilisez **FileZilla** :
1. Sélectionnez tous les fichiers SAUF les `.md` et `.sh`
2. Glissez-déposez directement
3. Attendez la fin de l'upload
4. C'est fait ! ✨
