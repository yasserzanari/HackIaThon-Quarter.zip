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


---

## 14. Logo PédagoLens — Remplacer le "P" par le vrai logo partout

Le logo est disponible à : `http://pedagolens.34.199.149.247.nip.io/wp-content/uploads/2026/03/logo.png` (1536×1024). Il doit être redimensionné (32-40px de hauteur) et utilisé partout où le "P" icône apparaît.

- [x] 14.1 Remplacer le logo dans `render_sidebar()` — changer `<span class="pl-app-sidebar-logo-icon">P</span>` par une balise `<img>` avec le logo redimensionné (40px height, auto width)
- [x] 14.2 Remplacer le logo dans `render_header()` — variante visiteur : changer `<span class="plx-nav-logo-icon">P</span>` par `<img>` logo (36px height)
- [x] 14.3 Remplacer le logo dans `shortcode_landing()` — nav header : changer `<div class="plx-logo-icon">P</div>` par `<img>` logo (36px height)
- [x] 14.4 Remplacer le logo dans `shortcode_landing()` — footer : changer `<div class="plx-logo-icon plx-logo-icon--white">P</div>` par `<img>` logo (32px height, filtre blanc si nécessaire)
- [x] 14.5 Remplacer le logo dans `shortcode_login()` — changer le "P" icône par `<img>` logo (48px height, centré)
- [x] 14.6 Ajouter les styles CSS pour `.pl-logo-img` dans `landing.css` — height contrôlée, object-fit contain, border-radius léger, transition hover

---

## 15. Header enseignant — Bouton switch vers interface étudiant

L'enseignant doit pouvoir basculer vers l'interface étudiant pour expérimenter avec l'agent IA Léa (jumeau numérique). Ajouter un toggle/bouton dans le header des pages internes enseignant.

- [x] 15.1 Ajouter un bouton "Tester l'interface étudiant" dans `render_header()` pour les enseignants/admins — icône `swap_horiz`, lien vers `/dashboard-etudiant/`, style pill accent, visible uniquement pour rôle teacher/admin
- [x] 15.2 Ajouter les styles CSS pour `.pl-header-switch-btn` — pill button, couleur secondaire (#712ae2), hover effect, icône Material, responsive (icône seule sur mobile)
- [x] 15.3 Ajouter un bouton "Retour interface enseignant" dans le header quand un enseignant visite le dashboard étudiant — détecter via `$_GET['mode']` ou via le rôle, afficher un bandeau info "Vous êtes en mode aperçu étudiant"

---

## 16. Système complet de création de cours — CRUD front-end

Actuellement les cours sont créés uniquement via l'admin WP. Il faut un système complet de création/édition/suppression de cours depuis le front-end (page Cours & Projets).

- [x] 16.1 Ajouter un formulaire modal de création de cours dans `shortcode_courses()` — champs : titre du cours (ex: "Français 103"), code du cours, session (A25, H26...), description, type de cours (magistral/exercice/mixte), upload du plan de cours (PDF)
- [x] 16.2 Créer le handler AJAX `ajax_create_course_front()` dans `class-landing.php` — valider les champs, créer le CPT `pl_course`, sauvegarder les metas (`_pl_course_code`, `_pl_session`, `_pl_course_type`, `_pl_syllabus_url`), retourner JSON avec le nouveau cours
- [x] 16.3 Ajouter un formulaire modal d'édition de cours — pré-remplir les champs, bouton "Mettre à jour", handler AJAX `ajax_update_course_front()`
- [x] 16.4 Ajouter un bouton de suppression de cours avec confirmation — handler AJAX `ajax_delete_course_front()`, vérifier qu'il n'y a pas de projets liés avant suppression (ou proposer suppression cascade)
- [x] 16.5 Ajouter les styles CSS pour les modals de cours — `.pl-modal`, `.pl-modal-overlay`, `.pl-modal-content`, `.pl-modal-header`, `.pl-modal-body`, `.pl-modal-footer`, animation slide-up, glass effect
- [x] 16.6 Ajouter le JavaScript pour les modals et AJAX dans `landing-front.js` — ouvrir/fermer modal, validation côté client, appels AJAX, feedback visuel (loading spinner, toast success/error)

---

## 17. Système complet de création de projets dans un cours

Chaque cours contient des projets de 2 types principaux : "Cours magistral" (upload PowerPoint, l'IA analyse la présentation) et "Exercice" (upload consignes/exercices, approche d'analyse différente).

- [x] 17.1 Refaire le formulaire modal de création de projet dans `shortcode_courses()` — champs : titre du projet, type (Cours magistral / Exercice), description, upload de fichier(s) (PowerPoint pour magistral, Word/PDF pour exercice)
- [x] 17.2 Créer/améliorer le handler AJAX `ajax_create_project_front()` dans `class-landing.php` — valider les champs, créer le CPT `pl_project` avec meta `_pl_course_id`, `_pl_project_type` (magistral/exercice), `_pl_files`, retourner JSON
- [x] 17.3 Ajouter la logique d'upload de fichiers pour les projets — utiliser `wp_handle_upload()`, stocker les URLs dans `_pl_files` (JSON array), supporter .pptx, .pdf, .docx, .doc
- [x] 17.4 Différencier l'affichage selon le type de projet dans la page Cours & Projets — icône et badge différents pour magistral vs exercice, aperçu du fichier uploadé
- [x] 17.5 Ajouter la vue détail d'un projet (dans le workbench) — afficher les fichiers uploadés, le type d'analyse, les résultats d'analyse IA, les suggestions
- [x] 17.6 Ajouter un bouton "Lancer l'analyse IA" dans la vue projet — appel AJAX vers `PedagoLens_API_Bridge::invoke()` avec le type approprié (magistral vs exercice), afficher un loader pendant l'analyse, puis les résultats

---

## 18. Historique des analyses dans un cours

Permettre de voir l'historique des analyses précédentes pour chaque cours et projet.

- [x] 18.1 Ajouter une section "Historique des analyses" dans la vue détail d'un cours (quand on clique "Voir les projets") — liste chronologique des analyses avec date, score, résumé
- [x] 18.2 Ajouter un lien "Voir l'historique complet" qui redirige vers la page Historique filtrée par cours — passer `?course_id=X` dans l'URL de la page historique
- [x] 18.3 Mettre à jour `shortcode_history()` pour supporter le filtre par cours — si `$_GET['course_id']` est présent, filtrer les analyses par meta `_pl_course_id`

---

## 19. Landing page — Corrections visuelles et polish

- [x] 19.1 Vérifier et corriger l'affichage de la landing page sur mobile (< 768px) — hero, features, testimonials, footer doivent être responsive
- [x] 19.2 Ajouter des animations d'entrée (fade-in, slide-up) sur les sections de la landing page via `IntersectionObserver` dans `landing-front.js`
- [x] 19.3 Vérifier que le header sticky de la landing fonctionne correctement — glass effect au scroll, transition smooth
- [x] 19.4 Corriger les liens du footer de la landing — s'assurer que les ancres (#plx-features, #plx-how, etc.) scrollent correctement

---

## 20. Optimisations et bugs divers

- [x] 20.1 Vérifier que toutes les pages internes redirigent vers /connexion/ si l'utilisateur n'est pas connecté — tester chaque shortcode
- [x] 20.2 Vérifier que le WP admin bar n'interfère pas avec le layout (sidebar + header) — offset de 32px déjà ajouté, vérifier sur toutes les pages
- [x] 20.3 Ajouter des meta `_pl_course_code` et `_pl_session` au CPT `pl_course` dans `register_cpt_course()` de `class-core.php`
- [x] 20.4 Ajouter la gestion des fichiers uploadés (meta `_pl_files`) au CPT `pl_project` dans `register_cpt_project()` de `class-core.php`
- [x] 20.5 Vérifier la cohérence des nonces AJAX sur tous les handlers — `pl_nonce` doit être vérifié partout
- [x] 20.6 Ajouter un toast/notification system global dans `landing-front.js` — pour feedback après création/édition/suppression de cours/projets

---

## 21. Version bump et déploiement

- [x] 21.1 Bump version `PL_LANDING_VERSION` de `2.4.1` → `2.5.0` (header PHP + constante define)
- [x] 21.2 Bump version des autres plugins modifiés si applicable (core 1.0.1 → 1.0.2)
- [x] 21.3 Commit + push + déploiement SSM (après confirmation utilisateur)


---

## 22. Supprimer le bouton "Vue étudiant" du header enseignant

L'enseignant ne doit PAS basculer vers l'interface étudiant. Il doit accéder directement à l'agent IA (Léa) depuis son propre dashboard.

- [x] 22.1 Retirer le bloc `pl-header-switch-btn` de `render_header()` dans `class-landing.php`
- [x] 22.2 Ajouter un lien "Agent IA Léa" dans la sidebar enseignant (render_sidebar) — icône `psychology`, lien vers le dashboard étudiant pour accéder au jumeau numérique

---

## 23. Redesign du bouton "Créer un cours" — Card dans la grille

Le bouton "Créer un cours" doit être plus visible. Si des cours existent déjà, afficher une card "Créer un cours" à la fin de la grille (style card avec icône +). Si aucun cours, afficher un grand CTA centré animé.

- [x] 23.1 Retirer le bouton "Créer un cours" du header de `shortcode_courses()` et le remplacer par une card dans la grille `pl-courses-grid`
- [x] 23.2 Ajouter les styles CSS pour `.pl-create-course-card` — card avec bordure dashed, icône + animée, hover glow effect
- [x] 23.3 Si aucun cours : afficher un grand CTA centré avec animation pulse au lieu du message vide actuel

---

## 24. Restructuration cours → séances (remplacer "projets" par "séances")

La hiérarchie correcte est : Cours (session) → Séances (semaines) → Fichiers/Analyse IA. Les "projets" deviennent des "séances" avec un type (cours magistral, travail d'équipe, exercice, évaluation).

- [x] 24.1 Renommer toute la terminologie "projet" → "séance" dans `shortcode_courses()` — labels, boutons, modals, titres
- [x] 24.2 Mettre à jour le modal "Créer un projet" → "Créer une séance" — champs : titre de la séance, semaine (Semaine 1, 2...), type (magistral/exercice/travail d'équipe/évaluation), upload fichiers
- [x] 24.3 Mettre à jour les handlers AJAX `ajax_create_project_front()` pour refléter la terminologie "séance"
- [x] 24.4 Mettre à jour l'affichage des séances dans les cards de cours — icônes par type, badge semaine

---

## 25. Corrections du header — Logo cliquable + section droite propre

- [x] 25.1 Dans `render_header()` variante connectée : ajouter le logo + texte "PédagoLens AI" cliquable vers `home_url('/')` dans `pl-header-app-left`
- [x] 25.2 Dans `render_header()` variante connectée : remplacer le texte "Déconnexion" par un bouton "Compte" avec icône `person`, et un bouton icône `logout` séparé
- [x] 25.3 Ajouter les styles CSS pour le header app amélioré — logo dans le header, boutons Compte/Logout stylés

---

## 26. Sidebar enseignant — Ajouter lien "Compte"

- [x] 26.1 Ajouter un item "Compte" dans le tableau `$nav` des enseignants/admins dans `render_sidebar()` — icône `person`, slug `account`, url vers `/compte/`

---

## 27. Version bump et déploiement des corrections

- [x] 27.1 Bump `PL_LANDING_VERSION` de `2.5.0` → `2.6.0` (header PHP + constante)
- [x] 27.2 Commit + push + déploiement SSM (après confirmation utilisateur)


---

## 28. Debug et refonte visuelle de la page Connexion

La page de connexion est visuellement moche. Utiliser Chrome MCP pour diagnostiquer et corriger le rendu.

- [x] 28.1 Inspecter la page `/connexion/` via Chrome MCP — identifier les problèmes visuels (layout, couleurs, espacement, responsive)
- [x] 28.2 Refaire le design de `shortcode_login()` — card centrée glass effect, gradient background, logo, champs stylés, boutons Stitch
- [x] 28.3 Ajouter/corriger les styles CSS pour la page connexion dans `landing.css`


---

## 29. Amélioration du branding page connexion

- [x] 29.1 Remplacer le texte branding à gauche par du contenu pertinent — "Votre assistant pédagogique propulsé par l'IA", widgets "Analyse de contenu" et "Jumeau numérique"
- [x] 29.2 Agrandir le logo (96px) et retirer le texte "PédagoLens" redondant (le logo contient déjà le nom)
- [x] 29.3 Corriger le login pour accepter username en plus de l'email — `sanitize_text_field` + `is_email()` check dans `ajax_login()`


---

## 30. Amélioration texte branding page connexion + logo agrandi

- [x] 30.1 Réécrire le titre branding : "Transformez chaque séance de cours grâce à l'IA"
- [x] 30.2 Réécrire la description : focus sur analyse de plans de cours, zones à risque, personnalisation
- [x] 30.3 Mettre à jour les widgets : "Analyse intelligente" (lacunes dans PowerPoint/PDF), "Agent IA Léa" (jumeau numérique), nouveau widget "Suivi en temps réel" (tableaux de bord par séance)
- [x] 30.4 Agrandir le logo de 96px → 120px dans le CSS
- [x] 30.5 Bump version 2.6.0 → 2.6.1 + déploiement


---

## 31. CSS polish — Course cards et séance cards sur la page Cours & Séances

Problème : les course cards (`.pl-course-card-front`) et les séance cards (`.pl-project-card-front`) n'ont pas de style visuel — pas de fond blanc, pas de border-radius, pas de shadow, pas de hover effect. Les éléments sont éparpillés sans structure de card visible.

- [x] 31.1 Ajouter les styles CSS pour pl-course-card-front dans landing.css — fond blanc, border-radius 16px, box-shadow, padding 24px, border subtle, hover lift effect, transition
- [x] 31.2 Ajouter les styles CSS pour pl-course-card-top — flex layout, gap entre header/meta/actions
- [x] 31.3 Ajouter les styles CSS pour pl-course-card-header — flex row, align center, gap 12px entre icône et titre
- [x] 31.4 Ajouter les styles CSS pour pl-course-type-icon — taille 40px, fond léger, border-radius 12px, centré
- [x] 31.5 Ajouter les styles CSS pour pl-course-card-title — Manrope 600, 1.25rem, couleur #00236f
- [x] 31.6 Ajouter les styles CSS pour pl-badge pl-type — badges colorés par type (magistral=bleu, exercice=vert, travail_equipe=orange, evaluation=rouge)
- [x] 31.7 Ajouter les styles CSS pour pl-course-card-meta — flex row, gap, font-size 0.85rem, couleur muted
- [x] 31.8 Ajouter les styles CSS pour pl-course-card-actions — flex row, gap 8px, align center, flex-wrap
- [x] 31.9 Ajouter les styles CSS pour pl-course-card-edit-btn et pl-course-card-delete-btn — boutons icône ronds, hover coloré
- [x] 31.10 Ajouter les styles CSS pour pl-projects-panel-front — fond surface léger, border-top, padding
- [x] 31.11 Ajouter les styles CSS pour pl-projects-grid-front — grid 2-3 colonnes, gap 16px
- [x] 31.12 Ajouter les styles CSS pour pl-project-card-front — card cliquable, fond blanc, border, border-radius 12px, padding 16px, hover glow, flex column, gap 8px
- [x] 31.13 Ajouter les styles CSS pour pl-project-card-icon — taille 32px, centré
- [x] 31.14 Ajouter les styles CSS pour pl-project-card-arrow — flèche à droite, couleur accent
- [x] 31.15 Responsive — course cards en 1 colonne sur mobile, séance cards en 1-2 colonnes

---

## 32. Workbench — Extraction de fichiers (PPTX/DOCX/PDF)

Le workbench a un bouton "Importer" et une zone d'upload, mais les méthodes d'extraction (`extract_pptx`, `extract_docx`, `extract_pdf`) dans `class-workbench-admin.php` doivent être vérifiées/implémentées pour extraire le texte des fichiers et créer des sections.

- [x] 32.1 Verifier et implémenter extract_pptx dans class-workbench-admin.php — extraire le texte des slides PowerPoint via ZipArchive + XML parsing, retourner un array de sections (1 section par slide)
- [x] 32.2 Verifier et implémenter extract_docx dans class-workbench-admin.php — extraire le texte des paragraphes Word via ZipArchive + XML parsing, regrouper par headings en sections
- [x] 32.3 Verifier et implémenter extract_pdf dans class-workbench-admin.php — extraire le texte du PDF via shell pdftotext ou fallback basique, retourner comme section unique
- [ ] 32.4 Tester upload end-to-end via Chrome MCP — uploader un fichier test, vérifier que les sections apparaissent dans le workbench

---

## 33. Workbench — Suggestions IA via API Bridge (mock mode)

Le bouton "Suggestions IA" sur chaque section doit appeler l'API Bridge pour obtenir des suggestions. En mode mock, des suggestions crédibles doivent être retournées.

- [x] 33.1 Verifier que le handler AJAX pl_get_suggestions existe et fonctionne dans class-workbench-admin.php
- [x] 33.2 Verifier que le JS workbench-admin.js appelle correctement le handler quand on clique Suggestions IA sur une section
- [x] 33.3 Verifier que le mode mock de PedagoLens_API_Bridge retourne des suggestions crédibles avec structure titre, description, impact score, profils impactés
- [x] 33.4 Verifier le rendu HTML des suggestions dans la sidebar — card par suggestion avec boutons Appliquer et Rejeter
- [ ] 33.5 Tester le flux complet via Chrome MCP — ajouter une section manuellement, cliquer Suggestions IA, vérifier que les suggestions apparaissent

---

## 34. Version bump et déploiement final v2.9.0

- [x] 34.1 Bump PL_LANDING_VERSION de 2.8.0 vers 2.9.0 (header PHP + constante define)
- [x] 34.2 Bump PL_WORKBENCH_VERSION si modifié (header PHP + constante define)
- [x] 34.3 Commit + push + déploiement SSM
- [ ] 34.4 Vérification post-déploiement via Chrome MCP — page cours, workbench, upload


---

## 35. Refonte WOW de la page Atelier (Workbench)

La page Atelier doit être spectaculaire — modals au lieu de prompts JS, auto-save, upload PowerPoint fonctionnel, design premium. Tout doit être WOW.

- [x] 35.1 Remplacer le prompt() JS par une modal WOW pour "Ajouter une section" — modal glass effect avec champ titre, textarea contenu optionnel, bouton Ajouter animé
- [x] 35.2 Remplacer le toggle upload zone par une modal WOW pour "Importer" — modal avec drag & drop zone, progress bar animée, preview du fichier
- [x] 35.3 Ajouter l'auto-save sur les textareas de section — debounce 2s après dernière frappe, indicateur "Sauvegardé" discret, retirer le bouton Enregistrer manuel
- [x] 35.4 Fix le bug d'ajout de section — le JS append dans `.pl-workbench-main` mais le empty state utilise `.pl-stitch-wb-empty`, corriger les sélecteurs
- [x] 35.5 Améliorer le CSS du workbench — sections avec glass cards, animations d'entrée, boutons avec hover glow, sidebar suggestions plus premium
- [x] 35.6 Ajouter les styles CSS pour les nouvelles modals workbench dans workbench-admin.css
- [x] 35.7 Tester le flux complet via Chrome MCP — ajouter section via modal, upload fichier, auto-save, suggestions IA

---

## 36. Page Analyses IA — Popup cours/séances ergonomique

Quand on sélectionne un cours sur la page Analyses IA (cours-projets), une popup WOW s'ouvre avec les séances à l'intérieur.

- [x] 36.1 Remplacer le lien "Voir les séances" par un clic sur la course card qui ouvre une modal WOW — modal avec titre du cours, grille de séances cliquables, bouton créer séance
- [x] 36.2 Ajouter les styles CSS pour la modal cours/séances dans landing.css
- [x] 36.3 Ajouter le JS pour ouvrir/fermer la modal et charger les séances via AJAX dans landing-front.js
- [x] 36.4 Tester via Chrome MCP — cliquer sur un cours, vérifier la modal, cliquer sur une séance

---

## 37. Page Atelier sans projet — Menu esthétique de sélection

Quand on arrive sur /workbench/ sans project_id, afficher un beau menu de sélection de cours/séances au lieu du message "Aucun projet sélectionné".

- [x] 37.1 Remplacer le message vide par un sélecteur de cours WOW dans shortcode_workbench() — grille de cours cards, clic ouvre les séances, clic séance redirige vers le workbench
- [x] 37.2 Ajouter les styles CSS pour le sélecteur de cours workbench
- [x] 37.3 Tester via Chrome MCP — aller sur /workbench/ sans paramètre, vérifier le sélecteur


---

## 38. Pipeline PowerPoint — Prévisualisation visuelle des slides

Le workbench extrait déjà le texte des slides PPTX (`extract_pptx()` via ZipArchive), mais il n'y a aucune prévisualisation visuelle. L'enseignant doit pouvoir VOIR ses slides dans le workbench, pas juste le texte brut.

**Approche** : Convertir les slides en images côté serveur via LibreOffice headless (`libreoffice --headless --convert-to png`), stocker les images, et les afficher dans un viewer JS.

- [x] 38.1 Installer LibreOffice headless sur le serveur EC2 via SSM — `apt-get install -y libreoffice-impress` (nécessaire pour la conversion slides → images)
- [x] 38.2 Créer la méthode `convert_pptx_to_images( string $filepath, int $attachment_id ): array` dans `class-workbench-admin.php`
  - Utiliser `shell_exec('libreoffice --headless --convert-to png --outdir /tmp/pl-slides-{id}/ {filepath}')` pour convertir chaque slide en PNG
  - Déplacer les images générées dans `wp-content/uploads/pedagolens/slides/{attachment_id}/`
  - Retourner un array `[ ['slide_num' => 1, 'url' => '...slide1.png', 'width' => ..., 'height' => ...], ... ]`
  - Stocker les URLs dans la meta `_pl_slide_images` du projet (JSON)
  - Gérer les erreurs (LibreOffice absent, fichier corrompu, timeout)
- [x] 38.3 Modifier `ajax_upload_file()` pour appeler `convert_pptx_to_images()` après l'extraction texte quand le fichier est un .pptx
  - Ajouter `slide_images` dans la réponse JSON (array d'URLs)
  - Ajouter `slide_image_url` à chaque section extraite (lier section ↔ image de la slide correspondante)
- [x] 38.4 Créer le composant JS "Slide Viewer" dans `workbench-admin.js`
  - Afficher une miniature de la slide au-dessus de chaque textarea de section (si `slide_image_url` existe)
  - Clic sur la miniature → ouvre un viewer modal plein écran avec navigation gauche/droite entre slides
  - Navigation clavier (flèches gauche/droite, Escape pour fermer)
  - Zoom pinch/scroll sur l'image
  - Indicateur "Slide X / Y" en bas du viewer
- [x] 38.5 Ajouter le HTML du viewer modal dans `render_front()` — `<div id="pl-slide-viewer" class="pl-stitch-modal">` avec image, boutons prev/next, compteur
- [x] 38.6 Modifier `render_front_section()` pour afficher la miniature de slide si disponible
  - Ajouter `<div class="pl-stitch-wb-slide-thumb">` avant le textarea
  - Image cliquable avec overlay "Voir la diapositive"
  - Fallback si pas d'image : ne rien afficher (comportement actuel)
- [x] 38.7 Ajouter les styles CSS dans `workbench-admin.css` pour le slide viewer
  - `.pl-stitch-wb-slide-thumb` : miniature 200px max-height, border-radius 8px, shadow, cursor pointer, hover scale
  - `.pl-slide-viewer-modal` : fond noir 90% opacity, image centrée max 90vw/90vh, boutons prev/next semi-transparents
  - `.pl-slide-viewer-counter` : badge "Slide X/Y" en bas, fond glass
  - Responsive : miniatures en full-width sur mobile
- [ ] 38.8 Tester le pipeline complet via Chrome MCP

---

## 39. Pipeline PowerPoint — Suggestions IA contextuelles par slide

Les suggestions IA doivent être liées à une slide spécifique et montrer clairement QUELLE modification est proposée et OÙ dans la slide.

- [x] 39.1 Enrichir le prompt template `workbench_suggestions` dans `default_prompt_templates()` de `class-api-bridge.php`
  - Ajouter le contexte : numéro de slide, type de cours, profils pédagogiques actifs
  - Demander à l'IA de retourner pour chaque suggestion : `slide_num`, `original_text`, `proposed_text`, `rationale`, `profile_target`, `impact_score` (0-100)
  - Ajouter un champ `modification_type` : "reformulation", "ajout", "suppression", "restructuration"
- [x] 39.2 Enrichir le mock `mock_workbench_suggestions()` dans `class-api-bridge-mock.php`
  - Retourner des suggestions avec les nouveaux champs (`slide_num`, `modification_type`, `impact_score`)
  - Varier les types de modifications pour la démo
  - Ajouter 4-5 suggestions au lieu de 3 pour plus de richesse
- [x] 39.3 Modifier le rendu HTML des suggestions dans `render_suggestions_html()` de `class-workbench-admin.php`
  - Afficher le numéro de slide concerné avec miniature
  - Afficher le type de modification avec badge coloré (reformulation=bleu, ajout=vert, suppression=rouge, restructuration=orange)
  - Afficher l'impact score avec barre de progression
  - Afficher le profil pédagogique ciblé avec icône
  - Boutons "Appliquer" et "Rejeter" avec animations
  - Afficher un diff visuel : texte original barré → texte proposé en vert
- [x] 39.4 Modifier le JS pour afficher les suggestions dans la sidebar droite avec animations
  - Quand on clique "Suggestions IA" sur une section, les suggestions apparaissent dans `#pl-stitch-suggestions-list` avec slide-in animation
  - Highlight de la section concernée dans la colonne gauche
  - Scroll automatique vers la suggestion dans la sidebar
- [x] 39.5 Ajouter les styles CSS pour les suggestions enrichies dans `workbench-admin.css`
  - `.pl-suggestion-card` : glass card, border-left colorée par type
  - `.pl-suggestion-diff` : texte original en rouge barré, proposé en vert
  - `.pl-suggestion-impact` : barre de score avec gradient
  - `.pl-suggestion-badge` : badge type de modification
  - `.pl-suggestion-profile` : icône + nom du profil ciblé
  - Animations d'entrée staggered (chaque suggestion apparaît avec un délai)

---

## 40. Pipeline PowerPoint — Prévisualisation "avant/après" d'une suggestion

L'enseignant doit pouvoir voir visuellement comment une suggestion modifierait sa slide AVANT de l'appliquer. C'est le "one-click preview".

- [x] 40.1 Créer la méthode `ajax_preview_suggestion()` dans `class-workbench-admin.php`
  - Reçoit : `project_id`, `section_id`, `suggestion_id`
  - Récupère la suggestion depuis le cache (`_pl_last_suggestions`)
  - Retourne le texte original et le texte proposé pour affichage côté client
  - Retourne aussi l'URL de l'image de la slide originale (si disponible)
- [x] 40.2 Créer le composant JS "Preview Modal" dans `workbench-admin.js`
  -  "Après" (texte proposé, texte modifié surligné en vert)
  - Bouton "Appliquer cette modification" en bas de la modal
  - Bouton "Annuler" pour fermer sans appliquer
  - Animation de transition entre avant/après 
- [x] 40.3 Ajouter le HTML de la preview modal dans `render_front()`
  - `<div id="pl-preview-modal" class="pl-stitch-modal">` avec layout 2 colonnes
  - Colonne gauche : titre "Avant", image slide (si dispo), texte original
  - Colonne droite : titre "Après", texte proposé avec diff highlights
  - Footer : boutons Appliquer / Annuler
- [x] 40.4 Ajouter un bouton "Prévisualiser" sur chaque suggestion card (à côté de Appliquer/Rejeter)
  - Icône œil (visibility), tooltip "Voir l'effet de cette suggestion"
  - Clic → ouvre la preview modal avec les données de cette suggestion
- [x] 40.5 Ajouter les styles CSS pour la preview modal dans `workbench-admin.css`
  - `.pl-preview-modal` : modal large (80vw), 2 colonnes 50/50
  - `.pl-preview-after` : fond légèrement vert
  - `.pl-preview-diff-add` : texte ajouté surligné vert
  - Responsive : colonnes empilées sur mobile

---

## 41. Pipeline PowerPoint — Appliquer une suggestion au contenu

Quand l'enseignant clique "Appliquer", le texte de la section est remplacé par le texte proposé, et l'interface se met à jour en temps réel.

- [x] 41.1 Vérifier et améliorer `ajax_apply_suggestion()` dans `class-workbench-admin.php`
  - Vérifier que le contenu de la section est bien remplacé par `proposed`
  - Sauvegarder une version avant modification via `save_version()`
  - Retourner le nouveau contenu dans la réponse JSON pour mise à jour côté client
  - Retourner aussi les scores mis à jour (recalculés après application)
- [x] 41.2 Modifier le JS pour mettre à jour le textarea en temps réel après application
  - Remplacer le contenu du textarea de la section concernée
  - Animation flash vert sur la section modifiée
  - Mettre à jour le compteur de suggestions (X restantes)
  - Retirer la suggestion appliquée de la liste avec animation fade-out
- [x] 41.3 Ajouter un bouton "Annuler la dernière modification" (undo) par section
  - Utiliser l'historique des versions (`ajax_get_versions`)
  - Restaurer le contenu précédent en un clic
  - Icône undo, visible uniquement si une modification a été appliquée
- [x] 41.4 Mettre à jour les scores dans la sidebar après chaque application de suggestion
  - Appel AJAX pour recalculer les scores (ou utiliser les `impact_estimates` du mock)
  - Animation de transition sur les barres de score (width transition)

---

## 42. Pipeline PowerPoint — Génération et téléchargement du PPTX modifié

Après avoir appliqué des suggestions, l'enseignant doit pouvoir télécharger un PPTX modifié avec les changements intégrés.

- [x] 42.1 Créer la méthode `generate_modified_pptx( int $project_id ): string|false` dans `class-workbench-admin.php`
  - Copier le PPTX original dans un fichier temporaire
  - Ouvrir avec ZipArchive
  - Pour chaque section modifiée, retrouver le slide XML correspondant (`ppt/slides/slideN.xml`)
  - Remplacer le texte dans les balises `<a:t>` en respectant la structure XML existante
  - Sauvegarder le PPTX modifié dans `wp-content/uploads/pedagolens/modified/`
  - Retourner le chemin du fichier modifié
- [x] 42.2 Créer le handler AJAX `ajax_download_modified()` dans `class-workbench-admin.php`
  - Appeler `generate_modified_pptx()`
  - Retourner l'URL de téléchargement du fichier modifié
  - Gérer les erreurs (pas de PPTX original, pas de modifications, erreur de génération)
- [x] 42.3 Ajouter un bouton "Télécharger le PowerPoint modifié" dans le header du workbench
  - Visible uniquement si au moins une suggestion a été appliquée
  - Icône download, style accent/primary
  - Clic → appel AJAX → téléchargement du fichier
  - Loading spinner pendant la génération
- [x] 42.4 Ajouter le JS pour le téléchargement dans `workbench-admin.js`
  - Appel AJAX vers `pl_download_modified`
  - Créer un lien temporaire `<a href="..." download>` et cliquer dessus programmatiquement
  - Feedback : toast "PowerPoint modifié téléchargé avec succès"
- [x] 42.5 Ajouter les styles CSS pour le bouton de téléchargement dans `workbench-admin.css`

---

## 43. Pipeline PowerPoint — Bouton "Analyser toutes les sections" (analyse globale)

Le bouton "Demander de nouvelles suggestions" dans la sidebar doit lancer une analyse IA sur TOUTES les sections du projet en une seule fois, pas section par section.

- [x] 43.1 Créer le handler AJAX `ajax_analyze_all_sections()` dans `class-workbench-admin.php`
  - Récupérer toutes les sections du projet
  - Appeler `PedagoLens_API_Bridge::invoke('course_analysis', ...)` avec le contenu complet
  - Retourner les scores par profil + les suggestions globales + le résumé
  - Sauvegarder les scores dans `_pl_profile_scores`, les suggestions dans `_pl_last_suggestions`, le résumé dans `_pl_summary`
- [x] 43.2 Modifier le JS du bouton "Demander de nouvelles suggestions" (`#pl-analyze-all`)
  - Appel AJAX vers `pl_analyze_all_sections`
  - Afficher un loader animé dans la sidebar pendant l'analyse
  - À la réponse : mettre à jour les scores, les suggestions, et le résumé dans la sidebar
  - Animation d'apparition progressive des résultats
- [x] 43.3 Ajouter un état "Analyse en cours" visuellement riche
  - Remplacer le contenu de la sidebar par un skeleton loader animé
  - Texte "PédagoLens AI analyse vos diapositives..." avec animation pulse
  - Barre de progression indéterminée (ou estimée si possible)
- [x] 43.4 Après l'analyse, afficher un résumé en haut de la sidebar
  - Card résumé avec le texte généré par l'IA
  - Score global moyen avec badge coloré
  - Nombre de suggestions générées

---

## 44. Version bump + déploiement + test end-to-end

- [x] 44.1 Bump `PL_WORKBENCH_VERSION` de `1.5.0` → `2.0.0` (header PHP + constante define) — version majeure car refonte complète du pipeline
- [x] 44.2 Bump `PL_BRIDGE_VERSION` si modifié (header PHP + constante define)
- [x] 44.3 Bump `PL_LANDING_VERSION` de `3.0.0` → `3.1.0` si modifié (header PHP + constante define)
- [x] 44.4 Commit + push + déploiement SSM
- [ ] 44.5 Test end-to-end via Chrome MCP :
  - Aller sur /cours-projets/, créer un cours, créer une séance magistrale
  - Aller sur le workbench de cette séance
  - Uploader un fichier PPTX
  - Vérifier que les slides apparaissent en miniatures
  - Cliquer sur une miniature → vérifier le viewer modal
  - Cliquer "Suggestions IA" sur une section → vérifier les suggestions enrichies
  - Cliquer "Prévisualiser" sur une suggestion → vérifier la preview avant/après
  - Cliquer "Appliquer" → vérifier que le texte est mis à jour
  - Cliquer "Télécharger le PowerPoint modifié" → vérifier le téléchargement
  - Cliquer "Demander de nouvelles suggestions" → vérifier l'analyse globale

---

## 45. Refonte Workbench — Mode Éditeur PowerPoint

Objectif : transformer le workbench d'un scroll vertical de textareas en un vrai éditeur de slides type PowerPoint. Une seule diapositive visible à la fois, navigation par miniatures à gauche, panneau IA à droite, aucun scroll vertical de la page principale.

### 45.1 Layout principal — structure 3 colonnes (viewport fixe, pas de scroll page)

- [x] 45.1.1 Refactorer `render_front()` dans `class-workbench-admin.php` : remplacer le layout 2 colonnes scrollable par un layout 3 colonnes plein écran (100vh)
  - Colonne gauche : panneau de miniatures des slides (filmstrip vertical, ~200px, collapsible)
  - Colonne centrale : zone d'édition de la diapositive active (1 seule slide visible à la fois)
  - Colonne droite : panneau IA (suggestions, scores, fichiers — le sidebar actuel)
  - Header compact en haut avec titre du projet, breadcrumb, boutons d'action (Importer, Sauvegarder, Télécharger)
  - `overflow: hidden` sur le body/container principal — AUCUN scroll de page
- [x] 45.1.2 CSS du layout éditeur dans `workbench-admin.css`
  - `.pl-editor-layout` : `display: grid; grid-template-rows: auto 1fr; height: 100vh;`
  - `.pl-editor-body` : `display: grid; grid-template-columns: auto 1fr auto; overflow: hidden;`
  - `.pl-editor-filmstrip` : largeur ~200px, `overflow-y: auto` (scroll interne uniquement), fond sombre
  - `.pl-editor-canvas` : zone centrale, flex centré, fond gris clair (comme un éditeur de slides)
  - `.pl-editor-panel` : panneau droit ~360px, `overflow-y: auto` (scroll interne)
  - Transitions smooth pour collapse/expand du filmstrip

### 45.2 Filmstrip — panneau de miniatures à gauche (collapsible)

- [x] 45.2.1 Générer le HTML du filmstrip dans `render_front()` : liste verticale de miniatures cliquables
  - Chaque miniature = card avec numéro de slide + titre tronqué + preview du contenu (premiers 50 chars)
  - Si des images de slides existent (`_pl_slide_images`), afficher l'image en miniature
  - Slide active = bordure accent + fond highlight
  - Bouton collapse/expand en haut du filmstrip (icône chevron)
  - Quand collapsed : filmstrip réduit à ~48px, affiche seulement les numéros de slides
- [x] 45.2.2 JS de navigation filmstrip : clic sur miniature → affiche la slide correspondante dans le canvas central
  - Mettre à jour la classe active sur la miniature
  - Transition fade/slide sur le contenu central
  - Raccourcis clavier : flèches haut/bas pour naviguer entre slides
  - Scroll automatique du filmstrip pour garder la slide active visible
- [x] 45.2.3 CSS du filmstrip
  - Style sombre (fond `#1a1a2e` ou similaire) pour contraste avec le canvas
  - Miniatures avec `border-radius: 8px`, hover glow, active accent border
  - Animation collapse : `width` transition 300ms ease
  - Scrollbar custom fine et discrète

### 45.3 Canvas central — édition d'une seule slide à la fois

- [x] 45.3.1 Refactorer `render_front_section()` : au lieu de rendre toutes les sections en liste, rendre une seule section dans le canvas
  - Le canvas affiche : titre de la section (éditable inline), textarea du contenu, boutons d'action
  - Si une image de slide existe, l'afficher en fond ou en preview au-dessus du textarea
  - Boutons sous le textarea : Enregistrer, Suggestions IA, Historique
  - Indicateur de slide : "Diapositive 3 / 11" avec flèches prev/next
- [x] 45.3.2 JS du canvas : gestion de la slide active
  - Variable globale `currentSlideIndex` — toutes les sections chargées en mémoire JS (pas de rechargement AJAX par slide)
  - Fonction `showSlide(index)` : met à jour le canvas avec le contenu de la section[index]
  - Auto-save quand on change de slide (sauvegarder la slide qu'on quitte)
  - Flèches prev/next + raccourcis clavier gauche/droite
  - Transition smooth entre slides (fade 200ms)
- [x] 45.3.3 CSS du canvas
  - Fond gris clair `#f0f0f5` (comme l'arrière-plan d'un éditeur de slides)
  - La "slide" elle-même = card blanche centrée, `max-width: 900px`, `aspect-ratio: 16/9` optionnel, `border-radius: 12px`, shadow
  - Textarea stylisé pour ressembler à une zone d'édition de slide (pas un form input classique)
  - Barre d'outils compacte sous la slide

### 45.4 Panneau IA à droite — suggestions et scores

- [x] 45.4.1 Réorganiser le sidebar droit pour le mode éditeur
  - Les suggestions IA s'affichent dans le panneau droit (pas inline sous chaque section)
  - Quand on clique "Suggestions IA" sur le canvas, les suggestions apparaissent dans le panneau droit
  - Scores par profil toujours visibles en bas du panneau
  - Bouton "Demander de nouvelles suggestions" reste dans le panneau
  - Fichiers du projet en section collapsible en bas
- [x] 45.4.2 Adapter le JS des suggestions pour le panneau droit
  - `pl_get_suggestions` → injecter le HTML dans `#pl-editor-suggestions` (panneau droit) au lieu de `#pl-suggestions-{sectionId}`
  - Les boutons Appliquer/Prévisualiser/Rejeter fonctionnent toujours, mais ciblent la slide active dans le canvas
  - Quand on change de slide, vider les suggestions ou les recharger pour la nouvelle slide
- [x] 45.4.3 CSS du panneau droit
  - Fond blanc/glass, `border-left: 1px solid rgba(0,0,0,0.08)`
  - Scroll interne uniquement
  - Cards de suggestions compactes pour tenir dans le panneau
  - Transition smooth quand les suggestions apparaissent

### 45.5 Collapse de la sidebar navigation gauche (menu PédagoLens)

- [-] 45.5.1 Ajouter un bouton toggle sur la sidebar de navigation principale (le menu PédagoLens à gauche avec Dashboard, Analyses IA, etc.)
  - Quand collapsed : sidebar réduite à ~60px, affiche seulement les icônes (pas de texte)
  - Quand expanded : sidebar normale ~260px avec icônes + texte
  - Sauvegarder l'état dans `localStorage` pour persistance
  - Le bouton toggle = icône hamburger/chevron en haut de la sidebar
- [-] 45.5.2 Adapter le CSS de la sidebar dans `landing.css`
  - `.pl-sidebar-collapsed` : `width: 60px`, texte masqué, icônes centrées
  - Transition `width` 300ms ease
  - `.pl-app-main` ajuste sa marge gauche en conséquence
  - Tooltip au hover sur les icônes quand collapsed (affiche le nom du lien)

### 45.6 Header compact du workbench

- [x] 45.6.1 Refactorer le header du workbench pour être plus compact en mode éditeur
  - Une seule ligne : [← Retour] [Titre du projet] [Type badge] [Importer] [Ajouter section] [Sauvegarder] [Télécharger PPTX]
  - Hauteur max ~56px
  - Le titre est éditable inline (click to edit)
- [x] 45.6.2 CSS du header compact
  - `height: 56px`, `display: flex`, `align-items: center`, `gap: 12px`
  - Fond blanc avec `border-bottom: 1px solid rgba(0,0,0,0.08)`
  - Boutons compacts (icône + texte court)

### 45.7 Version bump + déploiement + test

- [x] 45.7.1 Bump `PL_WORKBENCH_VERSION` de `2.0.0` → `3.0.0` (header PHP + constante define) — version majeure car refonte complète du layout
- [ ] 45.7.2 Bump `PL_LANDING_VERSION` si la sidebar est modifiée
- [x] 45.7.3 Commit + push + déploiement SSM
- [x] 45.7.4 Test end-to-end via Chrome MCP :
  - Vérifier le layout 3 colonnes (filmstrip + canvas + panneau IA)
  - Cliquer sur une miniature → la slide s'affiche dans le canvas
  - Naviguer avec les flèches → slide change
  - Collapse le filmstrip → il se réduit à 48px
  - Collapse la sidebar navigation → elle se réduit à 60px
  - Cliquer "Suggestions IA" → suggestions dans le panneau droit
  - Appliquer une suggestion → le texte change dans le canvas
  - Vérifier qu'il n'y a AUCUN scroll vertical de la page


---

## 46. Workbench JS — Système de navigation slides (éditeur PowerPoint)

- [x] 46.1 Réécrire complètement `workbench-admin.js` pour le mode éditeur PowerPoint
  - Variable `currentSlideIndex` pour tracker la slide active
  - Fonction `showSlide(index)` : met à jour le canvas, le filmstrip, le counter, les boutons toolbar
  - Navigation filmstrip : clic sur miniature → `showSlide(index)`
  - Navigation prev/next : boutons + raccourcis clavier (flèches gauche/droite)
  - Auto-save quand on change de slide (sauvegarder la slide qu'on quitte)
  - Suggestions IA → injectées dans `#pl-panel-suggestions` (panneau droit)
  - Filmstrip collapse/expand toggle
  - Toutes les fonctions existantes (apply, reject, preview, upload, download, analyze all) adaptées au nouveau layout
  - Conserver le slide viewer modal, le preview modal, les modals add section et import

---

## 47. Workbench CSS — Layout éditeur 3 colonnes plein écran

- [x] 47.1 Ajouter les styles CSS pour le layout éditeur dans `workbench-admin.css`
  - `.pl-editor` : `height: 100vh; display: grid; grid-template-rows: 56px 1fr; overflow: hidden;`
  - `.pl-editor-header` : header compact flex, fond blanc, border-bottom, z-index
  - `.pl-editor-body` : `display: grid; grid-template-columns: 220px 1fr 360px; overflow: hidden;`
  - `.pl-editor-filmstrip` : fond sombre #1a1a2e, overflow-y auto, transition width 300ms
  - `.pl-editor-filmstrip.collapsed` : width 48px, texte masqué
  - `.pl-filmstrip-item` : card miniature, hover glow, active accent border
  - `.pl-editor-canvas` : fond #f0f0f5, flex centré, overflow hidden
  - `.pl-canvas-slide` : card blanche centrée, max-width 900px, shadow, border-radius 12px
  - `.pl-canvas-textarea` : style éditeur de slide, pas un form input classique
  - `.pl-editor-panel` : fond blanc, border-left, overflow-y auto, scroll interne
  - `.pl-panel-section` : sections du panneau droit avec headers
  - Responsive : sur mobile, filmstrip et panel cachés, canvas plein écran
  - Boutons `.pl-editor-btn` : variantes outline, primary, accent, ghost, glow

---

## 48. Dashboard enseignant — Refonte full-width sans scroll

Chaque page du dashboard enseignant doit utiliser 100% de la largeur disponible, sans scroll vertical sur PC. Adapter la taille de chaque élément selon pertinence, harmonie et ergonomie.

- [x] 48.1 Refonte de `shortcode_teacher_dashboard()` — page Dashboard
  - KPI cards en grille adaptative (4 colonnes sur grand écran, 2 sur tablette)
  - Section "Cours récents" avec cards compactes (titre, dernier score, date, bouton Analyser)
  - Section "Ateliers récents" avec liens rapides vers les derniers workbench utilisés
  - Bouton "Nouveau cours" plus visible et beau (card CTA avec icône + animation)
  - Tout doit tenir dans 100vh sans scroll — utiliser des grilles adaptatives
  - Vérifier via Chrome MCP que rien ne scroll en bas

- [ ] 48.2 Vérifier et corriger `shortcode_courses()` — page Cours & Séances
  - Utiliser la full largeur, pas de marges excessives
  - Cards de cours compactes si nécessaire pour tout afficher sans scroll
  - Vérifier via Chrome MCP

- [ ] 48.3 Vérifier et corriger `shortcode_history()` — page Historique
  - Tableau compact, filtres en ligne, pas de scroll vertical
  - Vérifier via Chrome MCP

- [ ] 48.4 Vérifier et corriger `shortcode_settings()` — page Paramètres
  - Formulaire compact, sections en colonnes si nécessaire
  - Vérifier via Chrome MCP

- [ ] 48.5 Vérifier et corriger `shortcode_institutional()` — page Lumière institutionnelle
  - KPI et graphiques en grille, pas de scroll
  - Vérifier via Chrome MCP

- [ ] 48.6 Vérifier et corriger `shortcode_account()` — page Compte
  - Card profil compacte, pas de scroll
  - Vérifier via Chrome MCP

---

## 49. Agent IA Léa — Dashboard prof avec analytics

- [ ] 49.1 Créer un layout 2 panneaux pour la page Agent IA Léa (accessible depuis sidebar enseignant)
  - Panel gauche : Dashboard analytics — topics les moins bien compris, questions fréquentes, déficits détectés par profil
  - Panel droit : Chat avec l'agent IA Léa (interface existante du jumeau numérique)
  - Les données analytics sont basées sur les interactions étudiants avec le LLM (mock en mode mock)
  - KPI : nombre de sessions, topics problématiques, profils à risque

- [ ] 49.2 Créer les données mock pour le dashboard analytics de Léa
  - Topics les moins compris : liste avec score de compréhension, nombre de questions
  - Déficits par profil : barres de score par profil pédagogique
  - Questions fréquentes : top 5 questions posées par les étudiants
  - Alertes : étudiants en difficulté, topics critiques

---

## 50. Dashboard étudiant — Refonte et test complet

- [ ] 50.1 Se connecter avec etudiant1/etudiant1! via Chrome MCP et inspecter le dashboard si étudiant Utiliser la page connexion pour login jamais wp-login
- [ ] 50.2 Refonte du dashboard étudiant — thème plus simple et étudiant
  - Même palette de couleurs que le site mais design distinct du dashboard prof
  - Plus simple, plus accueillant, moins de KPI complexes
  - Profil d'apprentissage avec barres visuelles
  - Accès rapide à l'agent IA Léa
  - Cours de l'étudiant avec progression
- [ ] 50.3 Tester la fonction Agent IA Léa côté étudiant
  - Vérifier que le chat fonctionne (mode mock)
  - Corriger les bugs si le chat ne fonctionne pas
  - Fine-tuner l'interface jusqu'à satisfaction complète
- [ ] 50.4 Vérifier que le dashboard étudiant ne scroll pas verticalement sur PC

---

## 51. Configuration Bedrock — Settings admin + prompts + credentials

- [ ] 51.1 Ajouter un onglet "Bedrock" dans les settings admin de pedagolens-core
  - Champ pour AWS Access Key ID
  - Champ pour AWS Secret Access Key
  - Champ pour AWS Region (défaut us-east-1)
  - Sélecteur de modèle Bedrock (Claude 3 Sonnet, Claude 3 Haiku, Claude 3.5 Sonnet, etc.)
  - Bouton "Tester la connexion"
  - Les credentials sont stockés en constantes PHP ou env vars (jamais en options WP en clair)

- [ ] 51.2 Créer les prompts système pour chaque type d'analyse
  - Prompt "Analyse de cours magistral" : analyser un PowerPoint/contenu de cours, détecter les zones de friction par profil pédagogique, générer des suggestions
  - Prompt "Analyse d'exercice" : analyser des consignes/exercices, évaluer la clarté par profil
  - Prompt "Jumeau numérique Léa" : agent conversationnel qui aide l'étudiant sans donner les réponses
  - Chaque prompt est éditable dans les settings admin
  - Stocker les prompts dans les options WP

- [ ] 51.3 Modifier `class-api-bridge.php` pour supporter le vrai mode Bedrock
  - Si `pl_ai_mode = bedrock` : appeler AWS Bedrock via le SDK PHP
  - Utiliser les credentials des settings
  - Formater les requêtes selon le modèle sélectionné (Claude Messages API)
  - Parser les réponses et les convertir au format attendu par le workbench
  - Fallback sur mock si erreur Bedrock

- [ ] 51.4 Configurer les credentials Bedrock sur le serveur EC2
  - Créer les constantes dans wp-config.php via SSM
  - Tester la connexion Bedrock depuis le serveur

- [ ] 51.5 Tester le flux complet Bedrock via Chrome MCP
  - Aller sur le workbench, cliquer Suggestions IA
  - Vérifier que les suggestions viennent de Bedrock (pas du mock)
  - Fine-tuner les prompts si les résultats ne sont pas satisfaisants
  - Configurer et tester le mode IA Lea pour le prof et l'étudiant
  - Tester le dashboard lea et faire le backend pour que sa fonctionne

---

## 52. Version bump + déploiement + test final

- [ ] 52.1 Bump toutes les versions modifiées
- [ ] 52.2 Commit + push + déploiement SSM
- [ ] 52.3 Test end-to-end complet via Chrome MCP de toutes les pages


---

## 52. Éditeur PowerPoint Visuel — Rendu fidèle des slides avec positionnement réel

Objectif : Transformer le canvas central du workbench en un vrai rendu visuel de diapositive PowerPoint. Au lieu d'un simple textarea, afficher les éléments (textes, images, formes) à leur position réelle sur un canvas 16:9 qui ressemble à une vraie slide PowerPoint. L'enseignant voit ses slides comme dans PowerPoint, peut cliquer sur un élément texte pour l'éditer inline.

- [x] 52.1 Créer la méthode `extract_pptx_visual( string $filepath ): array` dans `class-workbench-admin.php`
  - Parser chaque slide XML (`ppt/slides/slideN.xml`) pour extraire les éléments visuels avec positions
  - Pour chaque shape (`<p:sp>`), extraire : position (x, y en EMU → px), taille (cx, cy), texte, couleur, taille police, alignement, gras/italique
  - Pour chaque image (`<p:pic>`), extraire : position, taille, référence au fichier image (rId → `ppt/media/imageN.png`)
  - Extraire les images du PPTX (ZipArchive) et les sauvegarder dans `wp-content/uploads/pedagolens/slides/{id}/media/`
  - Extraire le fond de slide (couleur unie ou image de fond)
  - Retourner un array structuré par slide avec tous les éléments positionnés
  - Stocker dans meta `_pl_slide_visual_data` (JSON)

- [x] 52.2 Modifier `ajax_upload_file()` pour appeler `extract_pptx_visual()` en plus de `extract_pptx()` quand le fichier est un .pptx
  - Ajouter `visual_data` dans la réponse JSON
  - Passer les données visuelles au JS pour le rendu

- [x] 52.3 Modifier `render_front()` pour passer les données visuelles au JS via `wp_localize_script`
  - Ajouter `visualData` dans le tableau `plWorkbench` (données visuelles par slide)
  - Ajouter les URLs des images extraites

- [x] 52.4 Réécrire le rendu canvas dans `workbench-admin.js` — fonction `renderVisualSlide(index)`
  - Si des données visuelles existent pour la slide, afficher un canvas visuel 16:9 au lieu du textarea
  - Chaque élément texte = `<div>` positionné en absolute avec les bonnes coordonnées, taille, couleur, police
  - Chaque image = `<img>` positionné en absolute avec les bonnes coordonnées et taille
  - Fond de slide = couleur ou image de fond sur le container
  - Clic sur un élément texte → passe en mode édition inline (contenteditable)
  - Fallback : si pas de données visuelles, afficher le textarea classique (comportement actuel)

- [x] 52.5 Ajouter les styles CSS pour le rendu visuel des slides dans `workbench-admin.css`
  - `.pl-visual-slide` : container 16:9, position relative, fond blanc, shadow, overflow hidden
  - `.pl-visual-element` : position absolute, transition smooth
  - `.pl-visual-text` : contenteditable au clic, outline au focus
  - `.pl-visual-image` : object-fit contain
  - `.pl-visual-slide-bg` : fond de slide (couleur ou image)
  - Responsive : scale proportionnel pour s'adapter à la largeur du canvas

- [x] 52.6 Ajouter la synchronisation édition visuelle → contenu texte
  - Quand l'enseignant modifie un texte dans le rendu visuel, mettre à jour le contenu de la section correspondante
  - Auto-save après modification (debounce 2s)
  - Bouton toggle "Vue visuelle / Vue texte" pour basculer entre les deux modes

- [-] 52.7 Version bump `PL_WORKBENCH_VERSION` 3.0.0 → 4.0.0 + commit + push + déploiement SSM
