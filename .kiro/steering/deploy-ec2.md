# Déploiement EC2 — PédagoLens

## Infra

| Ressource    | Valeur                      |
|--------------|-----------------------------|
| Instance ID  | i-0c837343e3ded4133         |
| IP publique  | 34.199.149.247              |
| Région       | us-east-1                   |
| Repo GitHub  | https://github.com/yasserzanari/HackIaThon-Quarter.zip.git |
| Plugins path | /opt/pedagolens/plugins/    |
| WP path      | /var/www/html               |

## Workflow de déploiement

### 1. Modifier le code dans Kiro
### 2. Bump les versions dans les headers PHP + constantes `define()`
### 3. Commit + push

```bash
git add -A
git commit -m "fix: description des changements"
git push origin main
```

### 4. Déployer sur EC2 via SSM (TOUJOURS utiliser le fichier JSON)

Créer/écraser `cdk/tmp-deploy-fix.json` :

```json
{
  "InstanceIds": ["i-0c837343e3ded4133"],
  "DocumentName": "AWS-RunShellScript",
  "Parameters": {
    "commands": [
      "export HOME=/root && git config --global --add safe.directory /opt/pedagolens && cd /opt/pedagolens && git pull origin main 2>&1",
      "echo '=== Syntaxe ===' && php -l /opt/pedagolens/plugins/pedagolens-api-bridge/includes/class-api-bridge.php 2>&1",
      "systemctl restart apache2 && echo 'Apache restarted OK'"
    ]
  }
}
```

Puis envoyer :

```bash
aws ssm send-command --cli-input-json file://cdk/tmp-deploy-fix.json --region us-east-1 --output json
```

Récupérer le `CommandId` dans la réponse, puis vérifier :

```bash
aws ssm get-command-invocation --command-id "COMMAND_ID_ICI" --instance-id "i-0c837343e3ded4133" --region us-east-1 --output json
```

## Règles importantes

- **TOUJOURS** `export HOME=/root` en premier dans les commandes SSM — sinon `fatal: $HOME not set`
- **TOUJOURS** `git config --global --add safe.directory /opt/pedagolens` avant le pull — sinon `dubious ownership`
- **JAMAIS** passer les commandes directement en `--parameters commands=[...]` sur Windows (problèmes d'échappement) — utiliser `--cli-input-json file://...`
- Attendre ~10 secondes avant de vérifier le résultat avec `get-command-invocation`

## Vérification post-déploiement

```json
{
  "InstanceIds": ["i-0c837343e3ded4133"],
  "DocumentName": "AWS-RunShellScript",
  "Parameters": {
    "commands": [
      "echo '=== WP DEBUG ===' && tail -50 /var/www/html/wp-content/debug.log 2>/dev/null || echo 'NO_DEBUG_LOG'",
      "echo '=== APACHE ERROR ===' && tail -20 /var/log/apache2/error.log 2>/dev/null || echo 'NO_APACHE_LOG'"
    ]
  }
}
```

## Bump de version — convention

- Patch (bugfix) : `1.0.0` → `1.0.1`
- Minor (nouvelle feature) : `1.0.x` → `1.1.0`
- Modifier le header PHP `* Version:` ET la constante `define('PL_XXX_VERSION', '...')`

## Plugins et leurs constantes de version

| Plugin                        | Constante               |
|-------------------------------|-------------------------|
| pedagolens-core               | `PEDAGOLENS_VERSION`    |
| pedagolens-api-bridge         | `PL_BRIDGE_VERSION`     |
| pedagolens-teacher-dashboard  | `PL_DASHBOARD_VERSION`  |
| pedagolens-course-workbench   | `PL_WORKBENCH_VERSION`  |
| pedagolens-student-twin       | `PL_TWIN_VERSION`       |
| pedagolens-landing            | `PL_LANDING_VERSION`    |
