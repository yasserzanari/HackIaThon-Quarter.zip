#!/bin/bash
set -e
exec > /var/log/pedagolens-setup.log 2>&1

echo "=== PédagoLens Bootstrap Start ==="

# Mise à jour système
apt-get update -y
apt-get upgrade -y

# PHP 8.1 + Apache + extensions WordPress
apt-get install -y software-properties-common
add-apt-repository ppa:ondrej/php -y
apt-get update -y
apt-get install -y apache2 php8.1 php8.1-mysql php8.1-curl php8.1-gd php8.1-mbstring php8.1-xml php8.1-zip php8.1-intl php8.1-bcmath libapache2-mod-php8.1

# MySQL
apt-get install -y mysql-server
systemctl start mysql
systemctl enable mysql

# Base de données WordPress
mysql -e "CREATE DATABASE IF NOT EXISTS pedagolens CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS 'pedagolens'@'localhost' IDENTIFIED BY 'PedagoLens2024!';"
mysql -e "GRANT ALL PRIVILEGES ON pedagolens.* TO 'pedagolens'@'localhost'; FLUSH PRIVILEGES;"

# WordPress
cd /var/www/html
rm -f index.html
wget -q https://wordpress.org/latest.tar.gz
tar -xzf latest.tar.gz --strip-components=1
rm latest.tar.gz

# wp-config.php
cp wp-config-sample.php wp-config.php
sed -i "s/database_name_here/pedagolens/" wp-config.php
sed -i "s/username_here/pedagolens/" wp-config.php
sed -i "s/password_here/PedagoLens2024!/" wp-config.php

# Salts WordPress (sécurité)
SALTS=$(curl -s https://api.wordpress.org/secret-key/1.1/salt/)
sed -i "/AUTH_KEY/d;/SECURE_AUTH_KEY/d;/LOGGED_IN_KEY/d;/NONCE_KEY/d;/AUTH_SALT/d;/SECURE_AUTH_SALT/d;/LOGGED_IN_SALT/d;/NONCE_SALT/d" wp-config.php
echo "$SALTS" >> wp-config.php

# Git + clone repo PédagoLens
apt-get install -y git
git clone https://github.com/yasserzanari/HackIaThon-Quarter.zip.git /opt/pedagolens

# Symlinks des 6 plugins
for plugin in pedagolens-core pedagolens-api-bridge pedagolens-teacher-dashboard pedagolens-course-workbench pedagolens-student-twin pedagolens-landing; do
  ln -sf /opt/pedagolens/plugins/$plugin /var/www/html/wp-content/plugins/$plugin
done

# Permissions
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html
chown -R ubuntu:ubuntu /opt/pedagolens

# Apache : mod_rewrite + .htaccess pour WordPress
a2enmod rewrite
cat > /etc/apache2/sites-available/000-default.conf << 'EOF'
<VirtualHost *:80>
    DocumentRoot /var/www/html
    <Directory /var/www/html>
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

systemctl restart apache2
systemctl enable apache2

# Script de déploiement rapide
cat > /usr/local/bin/pl-deploy << 'DEPLOY'
#!/bin/bash
echo "=== PédagoLens Deploy ==="
cd /opt/pedagolens
git pull origin main
echo "=== Deploy OK - $(date) ==="
DEPLOY
chmod +x /usr/local/bin/pl-deploy

# Alias pour ubuntu
echo 'alias pl-deploy="/usr/local/bin/pl-deploy"' >> /home/ubuntu/.bashrc

echo "=== PédagoLens Bootstrap Complete ==="
