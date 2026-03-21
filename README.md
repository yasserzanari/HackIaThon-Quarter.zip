# HackIAthon Quarter

Projet réalisé dans le cadre du HackIAthon.

## Présentation

HackIAthon Quarter est une plateforme d’intelligence artificielle pensée pour aider les enseignants à améliorer leurs cours selon différents profils d’étudiants.

L’objectif est simple : permettre à un professeur d’analyser un contenu pédagogique avant de le donner, de voir quels types d’étudiants risquent de moins bien le comprendre, puis d’obtenir des recommandations concrètes pour le rendre plus clair, plus accessible et plus efficace.

Le projet ne se limite pas à une analyse statique. Il ajoute aussi un accompagnement étudiant via un jumeau numérique du cours, ainsi qu’un tableau de bord enseignant qui met en évidence les incompréhensions réelles observées pendant l’usage.

---

## Problème

Aujourd’hui, les cours, consignes et évaluations sont souvent conçus pour un étudiant moyen.

En réalité, les classes sont composées de profils très variés :
- étudiants avec difficultés de concentration
- étudiants avec TDAH
- étudiants en surcharge cognitive
- étudiants en contexte de langue seconde
- étudiants plus autonomes
- étudiants qui utilisent l’IA de manière trop passive

Résultat :
- une même consigne peut être claire pour certains, mais confuse pour d’autres
- les enseignants manquent de temps pour tester et adapter leurs contenus
- les incompréhensions sont souvent découvertes trop tard, une fois le cours déjà donné
- l’usage de l’IA par les étudiants peut aider… ou remplacer l’apprentissage s’il est mal encadré

---

## Solution

Notre solution repose sur trois blocs principaux :

### 1. Analyse pédagogique par profils étudiants
L’enseignant colle un plan de cours, une consigne, un exercice ou une activité pédagogique.

Le système :
- analyse le contenu selon plusieurs profils étudiants simulés
- attribue un score de compréhension sur 100 à chaque profil
- détecte les ambiguïtés, zones floues et risques de surcharge
- propose des améliorations concrètes

### 2. Mesure d’impact des modifications
Chaque recommandation est accompagnée d’un impact estimé sur les profils.

Exemple :
- +10 points pour les étudiants en langue seconde
- +7 points pour les étudiants ayant des difficultés de concentration
- -2 points pour un profil très autonome si le contenu devient plus encadré

L’enseignant peut donc voir non seulement quoi modifier, mais aussi quels compromis chaque changement peut créer.

### 3. Jumeau numérique + dashboard enseignant
Dans une deuxième phase, le projet ajoute :
- un jumeau numérique du cours pour accompagner l’étudiant sans faire le travail à sa place
- un tableau de bord enseignant qui regroupe les incompréhensions les plus fréquentes et les zones du cours qui bloquent réellement

---

## Fonctionnalités principales

- Analyse de consignes et contenus pédagogiques
- Simulation de compréhension par sous-groupes d’étudiants
- Score de compréhension par profil
- Recommandations d’amélioration concrètes
- Visualisation de l’impact estimé de chaque changement
- Jumeau numérique du cours pour les étudiants
- Dashboard enseignant avec synthèse des incompréhensions
- Encadrement de l’usage responsable de l’IA

---

## Démo visée pour le hackathon

Le projet est structuré autour de 3 parcours de démonstration :

### Parcours 1 — Enseignant avant le cours
Le professeur soumet un contenu pédagogique.

La plateforme :
- détecte les ambiguïtés
- repère la surcharge cognitive
- identifie les risques d’incompréhension selon plusieurs profils
- suggère des corrections concrètes

### Parcours 2 — Étudiant pendant le cours
L’étudiant interagit avec le jumeau numérique du cours.

Le système peut :
- reformuler une consigne
- expliquer une tâche
- découper le travail en étapes
- guider sans donner directement la réponse finale

### Parcours 3 — Enseignant après usage étudiant
Le dashboard enseignant montre :
- les notions les plus confuses
- les sections du cours qui bloquent
- les types de questions les plus fréquents
- les recommandations prioritaires à appliquer

---

## Différenciateur

Ce projet n’est pas un simple chatbot éducatif.

Sa valeur distinctive est de :
- simuler un même contenu selon plusieurs profils étudiants
- quantifier la compréhension avec un score
- proposer des améliorations actionnables
- montrer l’impact mesurable de chaque modification
- relier l’analyse initiale à l’usage réel côté étudiant

En d’autres mots, la plateforme aide à optimiser un cours avant, pendant et après son utilisation.

---

## Architecture cible

Architecture recommandée pour la démo hackathon :

- **Frontend** : React / Next.js
- **Hébergement** : AWS Amplify
- **Authentification** : Amazon Cognito
- **API** : API Gateway + AWS Lambda
- **IA** : Amazon Bedrock
- **Stockage fichiers** : Amazon S3
- **Données applicatives** : DynamoDB
- **Observabilité** : CloudWatch

Une variante WordPress + plugins personnalisés peut aussi être utilisée pour accélérer la couche interface et la démonstration.

---

## Modules du projet

### Analyseur pédagogique
Entrée :
- consigne
- plan de cours
- critères
- documents de référence

Sortie :
- ambiguïtés
- charge cognitive
- risques d’incompréhension
- recommandations
- comparaison par profils étudiants

### Simulateur de profils étudiants
Profils de démonstration :
- étudiant fort et autonome
- étudiant qui manque de clarté
- étudiant allophone
- étudiant qui cherche à aller vite avec l’IA
- étudiant en surcharge cognitive

### Jumeau numérique du cours
Le jumeau peut :
- expliquer une consigne
- résumer les attentes
- aider à planifier le travail
- poser des questions de vérification
- refuser de faire le devoir à la place de l’étudiant

### Dashboard enseignant
Le tableau de bord présente :
- top incompréhensions
- notions les plus demandées
- types de prompts les plus fréquents
- zones du cours à améliorer
- recommandations prioritaires

---

## Objectif du hackathon

L’objectif n’est pas de construire un LMS complet.

L’objectif est de livrer une démo forte, crédible et fonctionnelle montrant :
1. l’analyse d’un cours selon plusieurs profils étudiants
2. l’accompagnement étudiant via un jumeau numérique
3. la remontée d’insights pédagogiques utiles pour l’enseignant

---

## Public cible

- enseignants du collégial
- enseignants universitaires
- conseillers pédagogiques
- équipes d’innovation pédagogique
- établissements souhaitant améliorer l’accessibilité et la clarté des cours

---

## Vision

Nous croyons qu’un cours ne devrait pas être optimisé pour un étudiant moyen, mais testé à travers la diversité réelle des apprenants.

HackIAthon Quarter vise à transformer l’IA en outil d’optimisation pédagogique mesurable, responsable et centré sur la compréhension réelle.

---

## Statut du projet

Projet en développement dans le cadre du HackIAthon.

### Priorités actuelles
- finaliser l’analyseur de consignes
- structurer les profils étudiants
- construire le jumeau numérique
- créer un dashboard enseignant simple et fort
- préparer une démo claire et convaincante

---

## Équipe

Projet développé dans le cadre du HackIAthon par une équipe de 3 personnes, avec une répartition typique :

- **Frontend / UX**
- **IA / logique pédagogique**
- **Backend / AWS / data**

---

## Licence

À définir.cé