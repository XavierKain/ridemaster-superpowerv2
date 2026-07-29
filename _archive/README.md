# `_archive/` — code et données remplacés, conservés pour référence

**Rien ici ne tourne sur ridemaster.eu.** Tout le contenu de ce dossier a été
remplacé par du code vivant ailleurs dans le dépôt, ou correspond à du matériel
d'exploration figé à une date donnée.

On garde ces fichiers volontairement : ils documentent des décisions de design et
servent de point de comparaison. Mais ils ne doivent **jamais** être réactivés,
réimportés ou copiés tels quels en production.

Archivé le 29 juillet 2026.

---

## ⚠️ `plugins/ridemaster-inline-edit/` — NE PAS ACTIVER

Plugin WordPress autonome, version 3.1.5, dernière modification le 22 février 2026.
C'est l'ancêtre de l'édition inline des profils coach et des camps.

**Remplacé par :** `plugins/ridemaster/includes/class-inline-edit.php`
(1748 lignes contre 1164 ici) et `plugins/ridemaster/assets/js/inline-edit.js`
(2680 lignes contre 2415 ici).

**Pourquoi c'est dangereux :** ce plugin déclare `class RM_Inline_Edit`, soit
**exactement le même nom de classe** que le plugin principal, qui l'instancie
dans `plugins/ridemaster/ridemaster.php` (lignes 31 et 45). Activer les deux
simultanément provoque une *fatal error* PHP « Cannot declare class RM_Inline_Edit »
et met le site hors ligne. Le fait que le site fonctionne aujourd'hui prouve que
ce plugin est désactivé.

Il porte un numéro de version crédible et ressemble à un plugin installable :
c'est précisément ce qui en fait un piège. D'où cet avertissement.

Les zips de release correspondants (`ridemaster-inline-edit-*.zip`) sont restés
dans `plugins/` et sont exclus par `.gitignore`.

## `plugins/ridemaster-coach-status-column.php`

Plugin mono-fichier (222 lignes, 4 mars 2026) ajoutant une colonne « Status »
colorée dans la liste des coachs de wp-admin, avec select inline, auto-publication
et e-mail au coach lors du passage à *validated*.

**Remplacé par :** `plugins/ridemaster/includes/class-admin.php`, qui enregistre
`manage_coach_posts_columns` → `add_status_column` (ligne 10), l'action AJAX
`wp_ajax_rm_update_coach_status` (ligne 16) et le hook `on_coach_status_change`
(ligne 19). Fonctionnalité strictement équivalente, mieux intégrée.

## `tools/` — anciens snippets manuels

Ces fichiers étaient collés à la main dans le plugin *Code Snippets* ou dans
*Apparence → Personnaliser → CSS additionnel* — leurs entêtes le disent
explicitement. Ils ont tous été industrialisés dans le plugin principal.

| Fichier | Ce qu'il faisait | Où ça vit maintenant |
|---|---|---|
| `coach-sidebar-dynamic.php` | shortcodes `[rm_coach_avatar]` et `[rm_coach_name]` pour la sidebar du dashboard (la page n'étant pas un single Coach, les champs dynamiques JetEngine n'y résolvaient pas le bon contexte) | `plugins/ridemaster/includes/class-coach.php` lignes 50-51 |
| `camp-form-flatpickr.php` | remplacement du date picker natif par Flatpickr sur la page de création de camp | `plugins/ridemaster/includes/class-inline-edit.php` ligne 748 |
| `camp-form-polish.css` | 454 lignes de style pour le formulaire de camp et le thème teal de Flatpickr | `plugins/ridemaster/includes/ui-tweaks.php` |

`analyze-elementor-widget.js` et `extract-widget-essentials.js` sont des scripts
Node d'analyse de widgets Elementor. Ils ne tournent pas sur le site — ils ont
servi à produire `docs/elementor-widgets-essential.json`. Toujours exécutables si
besoin.

## `jetformbuilder-imports/` — exports périmés, NE PAS RÉIMPORTER

Exports JSON des trois formulaires JetFormBuilder (inscription coach, profil
coach, création de camp), datés des 12 et 15 février 2026.

**Pourquoi ils sont périmés :** les formulaires réels ont été modifiés directement
dans wp-admin pendant des mois. Preuve : le commit `1952b28` (24 juin) mentionne
« Hotel field in camp form: removed required:true », donc le formulaire en
production possède un champ hôtel — or `camp-creation-form.json` n'en contient
aucun. D'autres écarts existent sur les champs photo.

Réimporter ces JSON écraserait le formulaire live par une version de février.

Les formulaires actifs n'existent aujourd'hui que dans la base de données. Un
ré-export à jour serait une vraie sécurité, mais il reste à faire.

## `elementor-templates-v2/` et `elementor-templates-v3/`

Itérations successives des templates Elementor (hero de la homepage, catégories
de sport, loop item de carte camp, single camp), janvier 2026.

La version qui a servi à l'import est `elementor-templates/` à la racine du dépôt,
suivie par git et référencée par `IMPORT-GUIDE.md`. Les v2 et v3 sont des
explorations qui n'ont jamais été branchées.

## `elementor-automation/`

Environ 73 fichiers utiles : scripts Node d'import via l'API REST Elementor,
comparaison de rendu avec la maquette, et une série de rapports markdown
(`DISCOVERY-REPORT.md`, `VISUAL-DIFFERENCES.md`, `IMPORT-STATUS.md`…).

Matériel de mise au point des templates, janvier 2026. Les rapports restent
intéressants à relire.

**Deux sous-dossiers ne sont pas versionnés** (exclus dans `.gitignore`, mais
présents sur le disque) :

- `screenshots/` — 32 Mo de captures de comparaison visuelle
- `node_modules/` — 13 Mo de dépendances, réinstallables via `npm install`

---

## Ce qui n'est **pas** ici, et pourquoi

`wp-content/mu-plugins/rm-translate-helper.php` tourne réellement en production :
il porte le miroir des termes en SQL direct, le hook de redirection JFB et le
snapshot `$_FILES`. Il est resté non versionné par oubli jusqu'au 29 juillet 2026,
et il est maintenant suivi à sa place normale — surtout pas ici.
