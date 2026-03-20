# PédagoLens — Infra AWS (us-east-1)

## Ressources créées

| Ressource         | ID / Valeur                          |
|-------------------|--------------------------------------|
| Instance EC2      | i-0c837343e3ded4133                  |
| Type              | t3.small, Ubuntu 22.04, 20 GB gp3    |
| Elastic IP        | 34.199.149.247                       |
| Security Group    | sg-0b879c7b802c91e0e                 |
| IAM Role          | pedagolens-ec2-role                  |
| Key Pair          | pedagolens-key                       |
| Région            | us-east-1                            |

## Accès

### SSH
```bash
ssh -i ~/.ssh/pedagolens-key.pem ubuntu@34.199.149.247
```

### WordPress
http://34.199.149.247  (disponible ~5 min après démarrage)

## Déploiement des mises à jour

### Depuis ton PC (Kiro fait le commit/push)
Les plugins sont dans `/opt/pedagolens/plugins/` sur l'EC2,
liés par symlink vers `/var/www/html/wp-content/plugins/`.

### Sur l'EC2 (après SSH)
```bash
pl-deploy   # = git pull origin main
```

### Workflow complet
1. Modifier les plugins dans Kiro
2. Kiro commit + push automatiquement
3. SSH sur l'EC2 et lancer `pl-deploy`

## Base de données WordPress
- DB: pedagolens
- User: pedagolens
- Password: PedagoLens2024!
- Host: localhost

## IAM Role — Bedrock
L'instance a accès à Bedrock via IAM Role (pas de credentials dans le code).
Modèles autorisés :
- anthropic.claude-3-5-sonnet-20241022-v2:0
- anthropic.claude-3-haiku-20240307-v1:0
- anthropic.claude-3-7-sonnet-20250219-v1:0
- anthropic.claude-sonnet-4-20250514-v1:0

## Logs bootstrap
```bash
ssh -i ~/.ssh/pedagolens-key.pem ubuntu@34.199.149.247
sudo tail -f /var/log/pedagolens-setup.log
```
