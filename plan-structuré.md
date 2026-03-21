# Plan structuré — Pages WordPress & Shortcodes PédagoLens

URL de base : `http://pedagolens.34.199.149.247.nip.io`

---

## Configuration WordPress requise

| Paramètre | Valeur |
|-----------|--------|
| Réglages > Lecture > Page d'accueil statique | Page "Accueil" |
| Réglages > Permaliens | Structure personnalisée : `/%postname%/` |
| Rôles actifs | `administrator`, `pedagolens_teacher`, `pedagolens_student` |
| Plugins requis | pedagolens-core, pedagolens-api-bridge, pedagolens-landing, pedagolens-teacher-dashboard, pedagolens-course-workbench, pedagolens-student-twin |

---

## Pages à créer dans WordPress

### 1. Landing Page (Accueil)

| Champ | Valeur |
|-------|--------|
| Titre | Accueil |
| Slug | `/` (définir comme page d'accueil dans Réglages > Lecture) |
| URL | `http://pedagolens.34.199.149.247.nip.io/` |
| Shortcode | `[pedagolens_landing]` |
| Accès | Public (visiteurs, étudiants, enseignants) |
| Description | Page vitrine premium ciblant cégeps et universités. Contient : header frosted glass avec navigation, hero avec mockup IA, section problème, méthodologie en 3 étapes (Analyser, Simuler, Optimiser), fonctionnalités premium, social proof, témoignages, CTA final, footer complet. Si l'utilisateur est connecté, le bouton "Connexion" devient "Mon Dashboard". |

---

### 2. Connexion / Inscription

| Champ | Valeur |
|-------|--------|
| Titre | Connexion |
| Slug | `/connexion` |
| URL | `http://pedagolens.34.199.149.247.nip.io/connexion` |
| Shortcode | `[pedagolens_login]` |
| Accès | Public (redirige vers dashboard si déjà connecté) |
| Description | Page de connexion et inscription avec design Stitch. Formulaire login (email + mot de passe) avec bascule vers formulaire inscription (nom, email, mot de passe, rôle). Glass card centrée sur fond mesh gradient. AJAX login/register sans rechargement. Après connexion : redirige vers `/dashboard-enseignant` (teacher/admin) ou `/dashboard-etudiant` (student). |

---

### 3. Dashboard Enseignant

| Champ | Valeur |
|-------|--------|
| Titre | Dashboard Enseignant |
| Slug | `/dashboard-enseignant` |
| URL | `http://pedagolens.34.199.149.247.nip.io/dashboard-enseignant` |
| Shortcode | `[pedagolens_teacher_dashboard]` |
| Accès | `pedagolens_teacher`, `administrator` |
| Description | Tableau de bord principal enseignant. Sidebar navigation à gauche. Contenu : message de bienvenue personnalisé, KPI cards (cours analysés, score moyen, projets actifs, profils à risque), liste des cours récents avec boutons Analyser/Voir détails, accès rapide aux fonctionnalités (Nouvelle analyse, Atelier, Historique). |

---

### 4. Dashboard Étudiant

| Champ | Valeur |
|-------|--------|
| Titre | Dashboard Étudiant |
| Slug | `/dashboard-etudiant` |
| URL | `http://pedagolens.34.199.149.247.nip.io/dashboard-etudiant` |
| Shortcode | `[pedagolens_student_dashboard]` |
| Accès | `pedagolens_student` |
| Description | Interface étudiant avec sidebar navigation. Contenu : message de bienvenue, profil d'apprentissage (scores par profil pédagogique), accès au jumeau numérique IA (Léa), liste des cours auxquels l'étudiant est inscrit, historique des sessions avec le jumeau. Paramètre optionnel : `[pedagolens_student_dashboard course_id="ID"]` pour fixer un cours. |

---

### 5. Cours & Projets

| Champ | Valeur |
|-------|--------|
| Titre | Cours & Projets |
| Slug | `/cours-projets` |
| URL | `http://pedagolens.34.199.149.247.nip.io/cours-projets` |
| Shortcode | `[pedagolens_courses]` |
| Accès | `pedagolens_teacher`, `administrator` |
| Description | Liste de tous les cours (CPT `pl_course`) avec leurs projets associés (CPT `pl_project`). Chaque cours affiche : titre, nombre de projets, dernier score d'analyse, statut. Cliquer sur un cours affiche ses projets avec lien vers le Workbench. Bouton "Créer un cours" en haut. |

---

### 6. Workbench (Atelier pédagogique)

| Champ | Valeur |
|-------|--------|
| Titre | Atelier Pédagogique |
| Slug | `/workbench` |
| URL | `http://pedagolens.34.199.149.247.nip.io/workbench?project_id=ID` |
| Shortcode | `[pedagolens_workbench]` |
| Accès | `pedagolens_teacher`, `administrator` |
| Description | Atelier d'édition de contenu pédagogique. Reçoit `?project_id=ID` via l'URL. Affiche : sections du cours avec scores par profil pédagogique, suggestions IA pour chaque section, boutons Appliquer/Rejeter suggestion, historique des versions, upload de fichiers (PPTX/DOCX/PDF). Sidebar navigation + breadcrumb. |

---

### 7. Mon Compte

| Champ | Valeur |
|-------|--------|
| Titre | Mon Compte |
| Slug | `/compte` |
| URL | `http://pedagolens.34.199.149.247.nip.io/compte` |
| Shortcode | `[pedagolens_account]` |
| Accès | Tout utilisateur connecté |
| Description | Page de profil utilisateur. Affiche : avatar, nom, email, rôle. Formulaire d'édition du profil (nom, prénom, email). Pour les étudiants : section supplémentaire "Mes difficultés d'apprentissage" avec checkboxes. Pour les enseignants : lien vers les paramètres avancés. Sidebar navigation. |

---

### 8. Historique des analyses

| Champ | Valeur |
|-------|--------|
| Titre | Historique |
| Slug | `/historique` |
| URL | `http://pedagolens.34.199.149.247.nip.io/historique` |
| Shortcode | `[pedagolens_history]` ou `[pedagolens_historique]` |
| Accès | `pedagolens_teacher`, `administrator` (voit tout), `pedagolens_student` (voit ses sessions) |
| Description | Timeline unifiée de toutes les analyses IA et sessions jumeau. Sidebar navigation. Contenu : barre de recherche, filtres (type: analyse/session, tri: date), tableau avec colonnes (Détails, Date, Type, Risque IA, Actions). Pagination. Chaque ligne affiche : icône type, titre, cours associé, date, badge type, indicateur de risque (Faible/Moyen/Élevé), boutons (Voir, Dupliquer, Exporter). |

---

### 9. Paramètres

| Champ | Valeur |
|-------|--------|
| Titre | Paramètres |
| Slug | `/parametres` |
| URL | `http://pedagolens.34.199.149.247.nip.io/parametres` |
| Shortcode | `[pedagolens_settings]` ou `[pedagolens_parametres]` |
| Accès | `pedagolens_teacher`, `administrator` |
| Description | Page de configuration utilisateur. Layout 2 colonnes. Colonne gauche : carte profil (avatar, nom, email, lien modifier), carte institution (université, département). Colonne droite : modèles de profils élèves (grille de cards), préférences IA (modèle d'analyse, ton épistémologique, niveau de détail, suggestions proactives), notifications (alertes progression, rapports hebdomadaires), affichage & langue (thème sombre, langue). Boutons Annuler/Sauvegarder. |

---

### 10. Lumière institutionnelle

| Champ | Valeur |
|-------|--------|
| Titre | Vue Institutionnelle |
| Slug | `/institutionnel` |
| URL | `http://pedagolens.34.199.149.247.nip.io/institutionnel` |
| Shortcode | `[pedagolens_institutional]` ou `[pedagolens_institutionnel]` |
| Accès | `administrator`, `pedagolens_teacher` |
| Description | Vue d'ensemble agrégée de toutes les analyses pédagogiques de l'institution. Header + sidebar navigation. Contenu : 4 KPI cards (cours analysés, analyses effectuées, score moyen global, projets actifs), graphique scores moyens par profil pédagogique (7 barres), tendance des scores sur 6 mois (bar chart), top 5 recommandations récurrentes, 3 profils les plus impactés (scores les plus bas). Design system éditorial premium (glass cards, gradients, typographie Manrope). |

---

## Résumé complet

| # | Page | Slug | Shortcode | Accès |
|---|------|------|-----------|-------|
| 1 | Landing | `/` | `[pedagolens_landing]` | Public |
| 2 | Connexion | `/connexion` | `[pedagolens_login]` | Public |
| 3 | Dashboard Enseignant | `/dashboard-enseignant` | `[pedagolens_teacher_dashboard]` | Teacher, Admin |
| 4 | Dashboard Étudiant | `/dashboard-etudiant` | `[pedagolens_student_dashboard]` | Student |
| 5 | Cours & Projets | `/cours-projets` | `[pedagolens_courses]` | Teacher, Admin |
| 6 | Atelier Pédagogique | `/workbench` | `[pedagolens_workbench]` | Teacher, Admin |
| 7 | Mon Compte | `/compte` | `[pedagolens_account]` | Connecté |
| 8 | Historique | `/historique` | `[pedagolens_history]` | Connecté |
| 9 | Paramètres | `/parametres` | `[pedagolens_settings]` | Teacher, Admin |
| 10 | Vue Institutionnelle | `/institutionnel` | `[pedagolens_institutional]` | Teacher, Admin |

---

## Notes importantes

- **Un seul shortcode par page** — ne pas combiner plusieurs shortcodes
- Le Workbench reçoit `?project_id=ID` via l'URL — le lien est généré automatiquement depuis Cours & Projets
- Les shortcodes français (`pedagolens_historique`, `pedagolens_parametres`, `pedagolens_institutionnel`) sont des alias qui appellent les versions anglaises
- La page Connexion redirige automatiquement vers le dashboard approprié après login
- La page Compte affiche un formulaire de connexion si l'utilisateur n'est pas connecté
- Les rôles WordPress : `administrator` (accès total), `pedagolens_teacher` (dashboard + analyses + workbench), `pedagolens_student` (jumeau + historique perso)
- Toutes les pages internes (3-10) partagent une sidebar de navigation commune et un footer compact
- La landing page (1) et la connexion (2) ont leur propre header/footer distinct
