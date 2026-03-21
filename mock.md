# PédagoLens — Mode Mock (Démonstration)

## Vue d'ensemble

Le mode **mock** simule les réponses de l'IA sans appeler AWS Bedrock. Il est conçu pour les démonstrations, les tests et le développement local.

## Activation

Dans l'admin WordPress → **PédagoLens API Bridge** → **Mode IA** → sélectionner `mock`.

Ou via WP-CLI / PHP :
```php
update_option('pl_ai_mode', 'mock');
```

## Thème de démonstration

Le mock est pré-configuré avec un cours de **sciences** portant sur la **Réalité Augmentée / Réalité Virtuelle (RA/RV) appliquée à l'enseignement des sciences au collégial**.

## Délais simulés

| Fonctionnalité | Délai | Raison |
|---|---|---|
| Analyse de cours (course_analysis) | **15 secondes** | Simule le temps d'analyse d'un PowerPoint complet |
| Suggestions workbench (workbench_suggestions) | **15 secondes** | Simule la génération de suggestions par diapositive |
| Chat Léa — réponse étudiante (student_twin_response) | **4 secondes** | Simule le temps de réflexion d'un LLM conversationnel |
| Vérification guardrail (student_guardrail_check) | **0 seconde** | Vérification rapide, pas de délai |
| Résumé dashboard (dashboard_insight_summary) | **4 secondes** | Simule la synthèse narrative |

## Contenu mock par fonctionnalité

### 1. Analyse de cours (`course_analysis`)

Retourne :
- **7 scores par profil** (49 à 85/100) — réalistes et différenciés
- **6 recommandations** ciblant chaque profil pédagogique
- **4 estimations d'impact** avec deltas par profil
- **Résumé narratif** mentionnant le score global (64/100) et les priorités

### 2. Suggestions workbench (`workbench_suggestions`)

Retourne **5 suggestions** variées :

| # | Type | Profil ciblé | Score d'impact |
|---|---|---|---|
| 1 | Reformulation | surcharge_cognitive | 88 |
| 2 | Ajout (glossaire bilingue) | langue_seconde | 82 |
| 3 | Restructuration (micro-étapes) | concentration_tdah | 79 |
| 4 | Reformulation (grille évaluation) | anxieux_consignes | 91 |
| 5 | Ajout (défi avancé) | avance_rapide | 65 |

Chaque suggestion inclut : texte original, texte proposé, justification pédagogique, impact_delta par profil.

### 3. Chat Léa (`student_twin_response`)

Le chat détecte le contexte du message étudiant et répond de façon adaptée :

| Mots-clés détectés | Thème de réponse |
|---|---|
| molécule, liaison, atome, 3D, polarité | Modélisation moléculaire |
| labo, expérience, manipulation, danger | Laboratoires virtuels |
| évaluation, note, portfolio, critère | Évaluation et portfolio |
| (autre) | Introduction RA vs RV |

**Guardrail** : Si l'étudiant demande de faire son travail à sa place (ex: "fais mon devoir", "écris mon rapport"), Léa refuse poliment et redirige vers l'apprentissage.

Chaque réponse inclut **3 questions de relance** pour stimuler la réflexion.

### 4. Résumé dashboard (`dashboard_insight_summary`)

Retourne un résumé narratif structuré avec :
- Score global (64/100)
- Points forts (3)
- Améliorations prioritaires (4) avec impact estimé
- Projection post-amélioration (78/100)

## Scénario de démonstration recommandé

1. **Créer un cours** : "RA/RV appliquée à l'enseignement des sciences"
2. **Uploader un PPTX** (n'importe lequel — le mock ignore le contenu réel)
3. **Ouvrir le workbench** → cliquer "Analyser tout" → attendre 15s → voir les suggestions
4. **Accepter/refuser** quelques suggestions pour montrer le workflow
5. **Aller sur la page étudiant** → ouvrir le chat Léa → poser des questions sur les molécules, les labos, l'évaluation
6. **Tester le guardrail** : écrire "fais mon devoir pour moi" → Léa refuse
7. **Dashboard enseignant** → voir le résumé narratif et les scores par profil

## Fichier source

`plugins/pedagolens-api-bridge/includes/class-api-bridge-mock.php`

## Retour en mode Bedrock

```php
update_option('pl_ai_mode', 'bedrock');
```
