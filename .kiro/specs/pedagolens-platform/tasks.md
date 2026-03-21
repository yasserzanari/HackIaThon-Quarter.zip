# Plan d'implémentation : PédagoLens Platform

## Vue d'ensemble

Implémentation des 6 plugins PHP WordPress constituant la plateforme PédagoLens.

**Profils pédagogiques** — gérés dynamiquement par `PedagoLens_Profile_Manager`.
**Convention sécurité AWS** : credentials via constantes PHP ou env vars, jamais en options WP.
**Mode mock** : `pl_ai_mode = mock` retourne des réponses de démonstration crédibles sans appel AWS.

---

## 1. Structure de base

- [x] 1.1 Créer les répertoires des 6 plugins avec structure `includes/`, `admin/`, `assets/`, `tests/`

---

## 2. Plugin pedagolens-core

- [x] 2.1 Bootstrap : `pedagolens-core.php` avec autoloader, constantes, activation hook
- [x] 2.2 CPT : `pl_analysis`, `pl_course`, `pl_interaction`, `pl_project` dans `class-core.php`
- [x] 2.3 Rôles : `pedagolens_teacher`, `pedagolens_student` avec capabilities dans `class-core.php`
- [x] 2.4 Logging : `PedagoLens_Core::log()` dans `class-core.php`
- [x] 2.5 Profile Manager : `class-profile-manager.php` — CRUD complet (get_all, get, save, delete, seed_defaults)
- [x] 2.6 Seed defaults : 7 profils pédagogiques seedés à l'activation
- [x] 2.7 Admin Profiles : `class-admin-profiles.php` — liste, édition, AJAX import/export
- [x] 2.8 Admin Profiles CSS/JS : `admin-profiles.css`, `admin-profiles.js`
- [x] 2.9 Core Settings : `class-core-settings.php` — page de paramètres 4 onglets
  - FAIT : structure de classe, register(), add_menu(), enqueue_assets(), render_page() avec navigation 4 onglets
  - MANQUE : render_tab_ia(), render_tab_profiles(), render_tab_behavior(), render_tab_advanced()
  - MANQUE : handle_save(), ajax_test_bedrock(), ajax_reset_profiles(), ajax_clear_logs(), ajax_export_config(), ajax_import_config()
  - MANQUE : wiring dans autoloader (pedagolens-core.php) et init (class-core.php)
  - MANQUE : assets/css/core-settings.css, assets/js/core-settings.js

---

## 3. Plugin pedagolens-api-bridge

- [x] 3.1 Bootstrap : `pedagolens-api-bridge.php` avec autoloader pour 3 classes
- [x] 3.2 API Bridge : `class-api-bridge.php` — invoke(), prompt templates, validate_response(), AWS SigV4
- [x] 3.3 Mock : `class-api-bridge-mock.php` — 5 prompt_keys avec réponses crédibles, profils dynamiques
- [x] 3.4 Settings : `class-api-bridge-settings.php` — mode IA, credentials AWS, config Bedrock, prompts éditables, test connexion AJAX
- [x] 3.5 Admin JS : `assets/js/admin.js`

---

## 4. Plugin pedagolens-landing

- [x] 4.1 Bootstrap : `pedagolens-landing.php` avec autoloader
- [x] 4.2 Landing class : `class-landing.php` — shortcodes (landing, teacher_dashboard, student_dashboard, courses, workbench, account, login)
- [x] 4.3 Landing Admin : `class-landing-admin.php` — settings page (hero, sections, shortcodes)
- [x] 4.4 Front assets : `landing.css`, `landing-front.js`
- [x] 4.5 Header/Footer : render_header(), render_footer() dans class-landing.php
- [x] 4.6 Login/Register : shortcode_login() avec AJAX login/register
- [x] 4.7 Account : shortcode_account() avec AJAX save_account_profile, save_student_difficulties
- [x] 4.8 Courses : shortcode_courses() avec liste de cours et projets
- [x] 4.9 Workbench shortcode : shortcode_workbench()

---

## 5. Plugin pedagolens-teacher-dashboard

- [x] 5.1 Bootstrap : `pedagolens-teacher-dashboard.php` avec autoloader
- [x] 5.2 Dashboard class : `class-teacher-dashboard.php` — analyze_course(), save_analysis(), get_profile_scores(), get_recommendations(), create_project(), get_projects(), render_front()
- [x] 5.3 Dashboard Admin : `class-dashboard-admin.php` — admin page avec course cards, AJAX analyze/create_project/create_course
- [x] 5.4 Admin assets : `dashboard-admin.css`, `dashboard-admin.js`

---

## 6. Plugin pedagolens-course-workbench

- [x] 6.1 Bootstrap : `pedagolens-course-workbench.php` avec autoloader
- [x] 6.2 Workbench class : `class-course-workbench.php` — get_suggestions(), apply_suggestion(), reject_suggestion(), save_version(), compare_versions(), get_content_sections()
- [x] 6.3 Workbench Admin : `class-workbench-admin.php` — admin page (project list, workbench view, section blocks, score bars, suggestions), AJAX handlers, render_front(), file upload (PPTX/DOCX/PDF extraction)
- [x] 6.4 Admin assets : `workbench-admin.css`, `workbench-admin.js`

---

## 7. Plugin pedagolens-student-twin

- [x] 7.1 Bootstrap : `pedagolens-student-twin.php` avec autoloader + dependency check core
- [x] 7.2 Twin class : `class-student-twin.php` — start_session(), end_session(), get_history(), send_message(), apply_guardrails()
- [x] 7.3 Twin Admin : `class-twin-admin.php` — admin page 3 onglets (settings/demo/sessions), shortcode [pedagolens_twin], AJAX handlers
- [x] 7.4 Admin assets : `twin-admin.css`, `twin-admin.js`

---

## 8. Tâches restantes — Backend

- [x] 8.1 Finir class-core-settings.php : render_tab_ia(), render_tab_profiles(), render_tab_behavior(), render_tab_advanced()
- [x] 8.2 Finir class-core-settings.php : handle_save() + 5 AJAX handlers (test_bedrock, reset_profiles, clear_logs, export_config, import_config)
- [x] 8.3 Wirer core-settings dans autoloader (pedagolens-core.php) et init (class-core.php)
- [x] 8.4 Créer assets/css/core-settings.css
- [x] 8.5 Créer assets/js/core-settings.js

---

## 9. Tâches restantes — Front-end (Design System Stitch)

Appliquer le design system des templates Stitch (Manrope/Inter, couleurs Material, glass cards, mesh gradients, rounded-[1.5rem]) à toutes les pages front-end :

- [x] 9.1 Landing page : refaire shortcode_landing() avec template `p_dagolens_landing_page_premium`
- [x] 9.2 Login/Register : refaire shortcode_login() avec template `connexion_p_dagolens`
- [x] 9.3 Accueil (post-login) : refaire shortcode_teacher_dashboard() / shortcode_student_dashboard() avec template `p_dagolens_accueil`
- [x] 9.4 Dashboard enseignant : refaire render_front() avec template `tableau_de_bord_p_dagolens`
- [-] 9.5 Détails du cours : ajouter vue détaillée avec template `d_tails_du_cours_p_dagolens`
- [x] 9.6 Analyse de contenu : refaire render_analysis_result() avec template `analyse_de_contenu_p_dagolens`
- [x] 9.7 Atelier pédagogique : refaire render_front() workbench avec template `atelier_p_dagogique_p_dagolens`
- [x] 9.8 Assistant étudiant : refaire render_shortcode() twin avec template `assistant_tudiant_p_dagolens`
- [-] 9.9 Historique : ajouter page historique avec template `historique_p_dagolens`
- [-] 9.10 Paramètres front : ajouter page paramètres front avec template `param_tres_p_dagolens`
- [-] 9.11 Lumière institutionnelle : ajouter vue institutionnelle avec template `lumi_re_institutionnelle`
- [x] 9.12 Mettre à jour landing.css avec design tokens globaux (couleurs, fonts, spacing, shadows)
- [x] 9.13 Mettre à jour landing-front.js avec animations et interactions
