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

- [ ] 22.1 Retirer le bloc `pl-header-switch-btn` de `render_header()` dans `class-landing.php`
- [ ] 22.2 Ajouter un lien "Agent IA Léa" dans la sidebar enseignant (render_sidebar) — icône `psychology`, lien vers le dashboard étudiant pour accéder au jumeau numérique

---

## 23. Redesign du bouton "Créer un cours" — Card dans la grille

Le bouton "Créer un cours" doit être plus visible. Si des cours existent déjà, afficher une card "Créer un cours" à la fin de la grille (style card avec icône +). Si aucun cours, afficher un grand CTA centré animé.

- [ ] 23.1 Retirer le bouton "Créer un cours" du header de `shortcode_courses()` et le remplacer par une card dans la grille `pl-courses-grid`
- [ ] 23.2 Ajouter les styles CSS pour `.pl-create-course-card` — card avec bordure dashed, icône + animée, hover glow effect
- [ ] 23.3 Si aucun cours : afficher un grand CTA centré avec animation pulse au lieu du message vide actuel

---

## 24. Restructuration cours → séances (remplacer "projets" par "séances")

La hiérarchie correcte est : Cours (session) → Séances (semaines) → Fichiers/Analyse IA. Les "projets" deviennent des "séances" avec un type (cours magistral, travail d'équipe, exercice, évaluation).

- [ ] 24.1 Renommer toute la terminologie "projet" → "séance" dans `shortcode_courses()` — labels, boutons, modals, titres
- [ ] 24.2 Mettre à jour le modal "Créer un projet" → "Créer une séance" — champs : titre de la séance, semaine (Semaine 1, 2...), type (magistral/exercice/travail d'équipe/évaluation), upload fichiers
- [ ] 24.3 Mettre à jour les handlers AJAX `ajax_create_project_front()` pour refléter la terminologie "séance"
- [ ] 24.4 Mettre à jour l'affichage des séances dans les cards de cours — icônes par type, badge semaine

---

## 25. Corrections du header — Logo cliquable + section droite propre

- [ ] 25.1 Dans `render_header()` variante connectée : ajouter le logo + texte "PédagoLens AI" cliquable vers `home_url('/')` dans `pl-header-app-left`
- [ ] 25.2 Dans `render_header()` variante connectée : remplacer le texte "Déconnexion" par un bouton "Compte" avec icône `person`, et un bouton icône `logout` séparé
- [ ] 25.3 Ajouter les styles CSS pour le header app amélioré — logo dans le header, boutons Compte/Logout stylés

---

## 26. Sidebar enseignant — Ajouter lien "Compte"

- [ ] 26.1 Ajouter un item "Compte" dans le tableau `$nav` des enseignants/admins dans `render_sidebar()` — icône `person`, slug `account`, url vers `/compte/`

---

## 27. Version bump et déploiement des corrections

- [ ] 27.1 Bump `PL_LANDING_VERSION` de `2.5.0` → `2.6.0` (header PHP + constante)
- [ ] 27.2 Commit + push + déploiement SSM (après confirmation utilisateur)


---

## 28. Debug et refonte visuelle de la page Connexion

La page de connexion est visuellement moche. Utiliser Chrome MCP pour diagnostiquer et corriger le rendu.

- [ ] 28.1 Inspecter la page `/connexion/` via Chrome MCP — identifier les problèmes visuels (layout, couleurs, espacement, responsive)
- [ ] 28.2 Refaire le design de `shortcode_login()` — card centrée glass effect, gradient background, logo, champs stylés, boutons Stitch
- [ ] 28.3 Ajouter/corriger les styles CSS pour la page connexion dans `landing.css`
