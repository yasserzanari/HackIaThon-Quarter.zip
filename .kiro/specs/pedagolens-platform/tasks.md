# Plan d'implémentation : PédagoLens Platform

## Vue d'ensemble

Implémentation des 6 plugins PHP WordPress constituant la plateforme PédagoLens.

**Profils pédagogiques** — gérés dynamiquement par `PedagoLens_Profile_Manager`.
**Convention sécurité AWS** : credentials via constantes PHP ou env vars, jamais en options WP.
**Mode mock** : `pl_ai_mode = mock` retourne des réponses de démonstration crédibles sans appel AWS.

---

## 1–8. Backend Plugins (COMPLÉTÉ)

- [x] 1.1 Structure de base des 6 plugins
- [x] 2.1–2.9 Plugin pedagolens-core (bootstrap, CPT, rôles, logging, profiles, admin, settings)
- [x] 3.1–3.5 Plugin pedagolens-api-bridge (bootstrap, bridge, mock, settings, admin JS)
- [x] 4.1–4.9 Plugin pedagolens-landing (bootstrap, shortcodes, admin, assets, header/footer, login, account, courses, workbench)
- [x] 5.1–5.4 Plugin pedagolens-teacher-dashboard (bootstrap, dashboard, admin, assets)
- [x] 6.1–6.4 Plugin pedagolens-course-workbench (bootstrap, workbench, admin, assets)
- [x] 7.1–7.4 Plugin pedagolens-student-twin (bootstrap, twin, admin, assets)
- [x] 8.1–8.5 Backend restant (core-settings tabs, AJAX, wiring, CSS/JS)

---

## 9. Front-end Stitch Design System (COMPLÉTÉ)

- [x] 9.1 Landing page avec template Stitch
- [x] 9.2 Login/Register avec template Stitch
- [x] 9.3 Accueil post-login (teacher/student dashboard)
- [x] 9.4 Dashboard enseignant render_front()
- [x] 9.5 Détails du cours
- [x] 9.6 Analyse de contenu
- [x] 9.7 Atelier pédagogique workbench
- [x] 9.8 Assistant étudiant twin
- [x] 9.9 Historique
- [x] 9.10 Paramètres front
- [x] 9.11 Lumière institutionnelle
- [x] 9.12 landing.css design tokens
- [x] 9.13 landing-front.js animations

---

## 10. Fix affichage global — Layout, sidebar & CSS cassé

Problème : l'affichage de TOUTES les pages internes est cassé de haut en bas. Chaque page (dashboard enseignant, dashboard étudiant, cours-projets, workbench, compte, historique, paramètres, institutionnel) a sa propre sidebar hardcodée en HTML inline avec des styles inline, les CSS ne s'appliquent pas correctement, et le rendu est incohérent et laid.

- [x] 10.1 Créer un composant sidebar réutilisable `render_sidebar()` dans `class-landing.php`
  - Sidebar fixe à gauche (260px) avec logo PédagoLens, navigation contextuelle selon le rôle
  - Liens enseignant/admin : Dashboard, Analyses IA, Cours & Projets, Historique, Paramètres, Lumière institutionnelle
  - Liens étudiant : Dashboard, Jumeau IA, Historique, Compte
  - Bouton "Nouvelle Analyse" (teacher/admin), Aide, Déconnexion
  - Lien actif détecté automatiquement via `$_SERVER['REQUEST_URI']`
  - Style Stitch : fond blanc, shadow-xl, Manrope/Inter, icônes Material Symbols
  - Responsive : sidebar cachée < 1024px, remplacée par un hamburger menu

- [x] 10.2 Refactorer toutes les pages internes pour utiliser `render_sidebar()`
  - `shortcode_teacher_dashboard()` — retirer la sidebar inline, utiliser render_sidebar()
  - `shortcode_student_dashboard()` — retirer la sidebar inline, utiliser render_sidebar()
  - `shortcode_courses()` — retirer la sidebar inline, utiliser render_sidebar()
  - `shortcode_workbench()` — retirer la sidebar inline, utiliser render_sidebar()
  - `shortcode_account()` — retirer la sidebar inline, utiliser render_sidebar()
  - `shortcode_history()` — retirer la sidebar `.pl-hi-sidebar`, utiliser render_sidebar()
  - `shortcode_settings()` — retirer la sidebar `.pl-st-sidebar`, utiliser render_sidebar()
  - `shortcode_institutional()` — ajouter render_sidebar() (actuellement n'a pas de sidebar)
  - Chaque page doit avoir la structure : `<div class="pl-app-layout"><sidebar/><main class="pl-app-main">...</main></div>`

- [x] 10.3 Fix complet de l'affichage de `shortcode_teacher_dashboard()` (Dashboard Enseignant)
  - Le HTML inline actuel est cassé — refaire le markup proprement avec des classes CSS
  - KPI cards en grille 4 colonnes (cours analysés, score moyen, projets actifs, profils à risque)
  - Liste des cours récents avec cards : titre, dernier score, date, boutons Analyser/Voir
  - Section accès rapide : Nouvelle analyse, Atelier pédagogique, Historique
  - Appliquer le design Stitch : glass cards, rounded-1.5rem, shadow-xl, Manrope headings, Inter body
  - Responsive : grille 2 colonnes sur tablette, 1 colonne sur mobile

- [x] 10.4 Fix complet de l'affichage de `shortcode_student_dashboard()` (Dashboard Étudiant)
  - Le HTML inline actuel est cassé — refaire le markup proprement avec des classes CSS
  - Message de bienvenue personnalisé avec avatar
  - Profil d'apprentissage : barres de score par profil pédagogique (7 profils)
  - Accès au jumeau numérique IA (Léa) : card avec bouton "Démarrer une session"
  - Liste des cours de l'étudiant
  - Appliquer le design Stitch : glass cards, rounded-1.5rem, shadow-xl
  - Responsive

- [x] 10.5 Fix complet de l'affichage de `shortcode_courses()` (Cours & Projets)
  - Le HTML inline actuel est cassé — refaire le markup proprement avec des classes CSS
  - Header avec titre + bouton "Créer un cours"
  - Grille de cards cours : titre, nombre de projets, dernier score, statut, boutons
  - Sous-section projets par cours avec liens vers le Workbench
  - Appliquer le design Stitch
  - Responsive

- [x] 10.6 Fix complet de l'affichage de `shortcode_workbench()` (Atelier Pédagogique)
  - Le HTML inline actuel est cassé — refaire le markup proprement avec des classes CSS
  - Breadcrumb : Cours > Projet > Atelier
  - Sections du cours avec scores par profil (barres colorées)
  - Panel suggestions IA avec boutons Appliquer/Rejeter
  - Upload de fichiers
  - Appliquer le design Stitch
  - Responsive

- [x] 10.7 Fix complet de l'affichage de `shortcode_account()` (Mon Compte)
  - Le HTML inline actuel est cassé — refaire le markup proprement avec des classes CSS
  - Card profil : avatar, nom, email, rôle
  - Formulaire édition profil (nom, prénom, email)
  - Section étudiant : "Mes difficultés d'apprentissage"
  - Section enseignant : lien vers paramètres avancés
  - Appliquer le design Stitch
  - Responsive

- [x] 10.8 Fix complet de l'affichage de `shortcode_history()` (Historique)
  - Le CSS existe déjà (`.pl-hi-*`) mais le layout est cassé à cause de la sidebar inline
  - Retirer la sidebar hardcodée, utiliser render_sidebar() + `.pl-app-layout`
  - Vérifier que le tableau, les filtres, la pagination, la recherche fonctionnent correctement
  - Fix responsive (sidebar cachée, tableau en cards sur mobile)

- [x] 10.9 Fix complet de l'affichage de `shortcode_settings()` (Paramètres)
  - Le CSS existe déjà (`.pl-st-*`) mais le layout est cassé à cause de la sidebar inline
  - Retirer la sidebar hardcodée, utiliser render_sidebar() + `.pl-app-layout`
  - Vérifier que le formulaire, les toggles, les profils, les boutons fonctionnent correctement
  - Fix responsive

- [x] 10.10 Fix complet de l'affichage de `shortcode_institutional()` (Lumière institutionnelle)
  - Actuellement utilise render_header()/render_footer() au lieu de la sidebar
  - Remplacer par render_sidebar() + `.pl-app-layout`
  - Vérifier que les KPI cards, graphiques, recommandations s'affichent correctement
  - Fix responsive

- [x] 10.11 Ajouter tous les styles CSS manquants dans `landing.css`
  - `.pl-app-layout` : display flex, min-height 100vh, background surface
  - `.pl-app-sidebar` : fixed left, 260px, fond blanc, shadow-xl, z-50
  - `.pl-app-sidebar-logo`, `.pl-app-sidebar-nav`, `.pl-app-sidebar-link`, `.pl-app-sidebar-link--active`
  - `.pl-app-main` : margin-left 260px, padding 3rem, flex 1
  - `.pl-app-hamburger` : bouton hamburger pour mobile
  - Styles dashboard enseignant : `.pl-dash-kpi`, `.pl-dash-kpi-card`, `.pl-dash-courses`, `.pl-dash-course-card`
  - Styles dashboard étudiant : `.pl-stu-profile`, `.pl-stu-scores`, `.pl-stu-twin-cta`
  - Styles cours & projets : `.pl-courses-grid`, `.pl-course-card`, `.pl-project-row`
  - Styles workbench : `.pl-wb-sections`, `.pl-wb-section-card`, `.pl-wb-suggestions`
  - Styles compte : `.pl-account-card`, `.pl-account-form`
  - Responsive breakpoints : sidebar hidden < 1024px, grilles adaptatives

- [x] 10.12 Fix le footer des pages internes
  - `render_footer()` actuel est trop basique (juste logo + 2 liens)
  - Créer un footer Stitch cohérent pour les pages internes (pas le même que la landing)
  - Footer compact : logo, copyright, liens utiles (Aide, Confidentialité, Contact)
  - Style : fond `surface-container-low`, texte `on-surface-variant`, padding compact

---

## 11. Fix headers — Adaptatifs selon rôle et contexte (TOUS les rôles ont un header top)

Problème : le `render_header()` est un simple nav bar sans style. La landing page a son propre header Stitch mais les pages internes n'ont pas de header cohérent. TOUS les utilisateurs (visiteur, étudiant, enseignant, admin) doivent avoir un header top en plus de la sidebar pour les pages internes.

- [x] 11.1 Refaire `render_header()` avec 3 variantes contextuelles (TOUS avec header top)
  - **Visiteur (non connecté)** : header frosted glass fixe en haut, logo PédagoLens, liens (Découvrir, Ressources, Tarification), boutons (Connexion, Essai gratuit)
  - **Étudiant connecté** : header top compact avec logo, breadcrumb contextuel, nom + avatar de l'étudiant, notifications, bouton Déconnexion
  - **Enseignant/Admin connecté** : header top compact avec logo, breadcrumb contextuel, nom + avatar, notifications, bouton Déconnexion. Le header top coexiste avec la sidebar (header en haut, sidebar à gauche, contenu à droite)
  - Accepter un paramètre `$context` pour personnaliser le breadcrumb (ex: "Dashboard > Cours > Détails")

- [x] 11.2 Mettre à jour la landing page pour utiliser le header visiteur amélioré
  - Le header actuel de `shortcode_landing()` est hardcodé avec des liens non pertinents (Tarification, Manifeste)
  - Remplacer par des liens pertinents pour cégeps/universités : Fonctionnalités, Comment ça marche, Témoignages, Contact
  - Ajouter détection : si l'utilisateur est connecté, afficher "Mon Dashboard" au lieu de "Connexion"
  - Le header doit être sticky avec glass effect (déjà le cas via `.plx-header`)

- [x] 11.3 Ajouter les styles CSS pour les variantes de header dans `landing.css`
  - `.pl-header-visitor` : frosted glass, fixed top, z-50
  - `.pl-header-app` : header top compact pour pages internes (enseignant + étudiant), fond blanc, shadow-sm, z-40
  - `.pl-header-app` doit coexister avec `.pl-app-sidebar` : header full-width en haut, sidebar en dessous à gauche, main content décalé
  - `.pl-breadcrumb` : fil d'Ariane pour toutes les pages internes
  - `.pl-header-user` : section avatar + nom + notifications à droite du header
  - Responsive : hamburger menu pour mobile sur toutes les variantes

---

## 12. Landing page — Pertinence cégeps/universités

Problème : la landing page n'est pas assez ciblée pour le marché cégeps et universités québécoises/canadiennes. Le contenu est trop générique et les références (UNESCO, ERASMUS+, Sorbonne, HEC Paris) ne parlent pas au public cible.

- [x] 12.1 Refaire le contenu hero de la landing page
  - Titre : plus percutant, orienté cégeps/universités (ex: "L'IA qui transforme chaque cours en expérience d'apprentissage personnalisée")
  - Sous-titre : mentionner explicitement cégeps, universités, professeurs
  - Badge : "Conçu pour l'enseignement supérieur québécois" ou similaire
  - CTA principal : "Démarrer gratuitement" + CTA secondaire : "Voir une démo"
  - Trust badges : remplacer UNESCO/ERASMUS+ par des références pertinentes (MEES, Cégep de Montréal, UQAM, etc.)

- [x] 12.2 Refaire la section "Problème" pour le contexte québécois
  - Statistiques pertinentes sur le décrochage au cégep/université
  - Problématiques spécifiques : diversité des profils étudiants, cours magistraux de 200+ étudiants, manque de feedback personnalisé
  - Ton plus direct et moins "corporate européen"

- [x] 12.3 Refaire la section "Social Proof" avec des institutions pertinentes
  - Remplacer Sorbonne/HEC/Polytechnique/Sciences Po par des institutions québécoises/canadiennes
  - Témoignages de professeurs de cégep/université (fictifs mais crédibles)
  - Métriques d'impact : "+25% de rétention", "3x plus de feedback personnalisé", etc.

- [x] 12.4 Refaire le footer de la landing page
  - Liens pertinents : Fonctionnalités, Tarification, À propos, Blog, Contact
  - Mentions légales adaptées au Québec
  - Réseaux sociaux pertinents

---

## 13. Mettre à jour `plan-structuré.md`

- [x] 13.1 Mettre à jour `plan-structuré.md` avec toutes les pages WordPress nécessaires
  - Ajouter les pages manquantes : Connexion, Historique, Paramètres, Lumière institutionnelle, Détails du cours
  - Pour chaque page : titre, slug, URL, shortcode, description détaillée du contenu
  - Préciser quel shortcode mettre dans chaque page WordPress
  - Préciser les rôles autorisés pour chaque page
  - Ajouter une section "Configuration WordPress" (page d'accueil, permalinks, etc.)
