# Deployment der Dienstplan-Anwendung

Kurzanleitung zum Deployen der PHP-Anwendung auf einem Remote-Server. Die Anwendung kann **im Root** einer (Sub-)Domain oder **in einem Unterverzeichnis** neben anderen Anwendungen betrieben werden.

## Voraussetzungen auf dem Server

- **PHP** 8.0+ (mit PDO MySQL/MariaDB)
- **MariaDB** oder **MySQL** 5.7+
- **Webserver**: Apache (mod_rewrite) oder Nginx

---

## 1. Datenbank einrichten

```bash
# Datenbank anlegen
mysql -u root -p -e "CREATE DATABASE dienstplan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Benutzer anlegen (Beispiel)
mysql -u root -p -e "CREATE USER 'dienstplan'@'localhost' IDENTIFIED BY 'SICHERES_PASSWORT';"
mysql -u root -p -e "GRANT ALL ON dienstplan.* TO 'dienstplan'@'localhost';"
mysql -u root -p -e "FLUSH PRIVILEGES;"

# Schema importieren
mysql -u dienstplan -p dienstplan < sql/schema.sql

# Optional: Migrationen anwenden (falls vorhanden)
mysql -u dienstplan -p dienstplan < sql/migration_employee_name.sql
mysql -u dienstplan -p dienstplan < sql/migration_employee_allowed_weekday_shift.sql
mysql -u dienstplan -p dienstplan < sql/migration_rule_required_count_exact.sql
```

---

## 2. Anwendung auf den Server bringen

### Option A: Git (empfohlen)

```bash
# Auf dem Server – je nach Setup z.B.:
# Eigenes Verzeichnis (eigene Domain/Subdomain):
cd /var/www
git clone <URL-DES-REPOS> dienstplan
cd dienstplan

# Oder Unterverzeichnis auf bestehendem Server (z.B. neben anderen Apps):
cd /var/www/html
git clone <URL-DES-REPOS> dienstplan
cd dienstplan
```

### Option B: rsync / SCP

```bash
# Von deinem Rechner (ohne vendor/ – wird auf dem Server installiert)
# Zielpfad = gewünschtes Unterverzeichnis auf dem Server, z.B. dienstplan
rsync -avz --exclude 'vendor' --exclude '.git' \
  ./ user@server:/var/www/html/dienstplan/
```

---

## 3. Auf dem Server ausführen

```bash
cd /var/www/dienstplan

# Composer-Abhängigkeiten installieren (Produktion)
composer install --no-dev --optimize-autoloader

# Konfiguration anpassen (siehe Abschnitt „Konfiguration“)
```

---

## 4. Konfiguration

### Konfigurationsdatei

Kopiere oder bearbeite `config/config.php`. **Wichtig:** Die Datei enthält Zugangsdaten – sie darf nicht öffentlich lesbar sein und gehört nicht ins Git.

Für Produktion können Umgebungsvariablen genutzt werden (siehe `config/config.php`). Beispiel:

```bash
export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_NAME=dienstplan
export DB_USER=dienstplan
export DB_PASSWORD=SICHERES_PASSWORT
```

**Deployment in einem Unterverzeichnis:** Wenn die Anwendung z. B. unter `https://example.com/dienstplan/` erreichbar sein soll, muss der Basis-Pfad gesetzt werden (ohne abschließenden Schrägstrich):

```bash
export BASE_PATH=/dienstplan
```

Oder in `config/config.php`: `'base_path' => '/dienstplan'`.

Falls keine Umgebungsvariablen gesetzt sind, werden die Werte aus der `config/config.php` verwendet. Dort solltest du für den Server die echten Zugangsdaten eintragen und den Zugriff einschränken:

```bash
chmod 600 config/config.php
```

---

## 5. Webserver einrichten

**DocumentRoot** bzw. der Ort, den der Webserver für die App ausliefert, muss auf das Verzeichnis **`public`** zeigen, nicht auf das Projektroot.

### Variante A: Eigene (Sub-)Domain (App im Root)

#### Apache

```apache
<VirtualHost *:80>
    ServerName dienstplan.example.com
    DocumentRoot /var/www/dienstplan/public

    <Directory /var/www/dienstplan/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/dienstplan-error.log
    CustomLog ${APACHE_LOG_DIR}/dienstplan-access.log combined
</VirtualHost>
```

In `public/.htaccess` bleibt **RewriteBase /**.

#### Nginx

```nginx
server {
    listen 80;
    server_name dienstplan.example.com;
    root /var/www/dienstplan/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;  # Pfad ggf. anpassen
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

**Konfiguration:** `BASE_PATH` leer lassen (oder weglassen).

---

### Variante B: Unterverzeichnis auf bestehendem Server

Die App liegt z. B. unter `https://example.com/dienstplan/` neben anderen Anwendungen.

#### Apache

Entweder **Alias** auf das `public`-Verzeichnis der App:

```apache
# DocumentRoot ist z.B. /var/www/html
Alias /dienstplan /var/www/html/dienstplan/public

<Directory /var/www/html/dienstplan/public>
    AllowOverride All
    Require all granted
</Directory>
```

Dann in **`public/.htaccess`** die Zeile anpassen:

```apache
RewriteBase /dienstplan/
```

(statt `RewriteBase /`).

**Wichtig:** In der Konfiguration **BASE_PATH** setzen: Umgebungsvariable `BASE_PATH=/dienstplan` oder in `config/config.php`: `'base_path' => '/dienstplan'`.

#### Nginx

```nginx
server {
    listen 80;
    server_name example.com;
    root /var/www/html;

    # … andere location-Blöcke für andere Apps …

    location /dienstplan {
        alias /var/www/html/dienstplan/public;
        index index.php;
        try_files $uri $uri/ @dienstplan;
    }
    location @dienstplan {
        rewrite ^/dienstplan(.*)$ /dienstplan/index.php?$query_string last;
        # Alternativ: fastcgi mit SCRIPT_FILENAME auf .../dienstplan/public/index.php
    }
    location ~ ^/dienstplan/index\.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME /var/www/html/dienstplan/public/index.php;
        include fastcgi_params;
    }
}
```

Oder mit **location /dienstplan/** und `fastcgi_param SCRIPT_FILENAME` so setzen, dass `index.php` im App-`public`-Verzeichnis aufgerufen wird. PHP-FPM-Socket-Pfad je nach System anpassen.

**Konfiguration:** `BASE_PATH=/dienstplan` (Umgebungsvariable oder `config/config.php`).

#### Unterverzeichnis neben WordPress (Root-`.htaccess` leitet alles um)

Wenn im **DocumentRoot** (z. B. `public_html`) eine `.htaccess` alle Anfragen an WordPress (`/wp/...`) weiterleitet, muss die App explizit **ausgenommen** werden, sonst liefert der Server 404, ohne jemals `index.php` der App aufzurufen.

Vor der Regel, die auf `/wp/` umschreibt, eine Bedingung einfügen:

```apache
# Beispiel: Nur umschreiben, wenn die Anfrage NICHT unter der App liegt
RewriteCond %{REQUEST_URI} !^/apollo-dienstplan/
RewriteCond %{REQUEST_URI} !/(\w+)
RewriteRule ^(.*)$ /wp/$1 [L]
```

Ersetze `/apollo-dienstplan/` durch den tatsächlichen URL-Prefix der App (mit Schrägstrich). Dann werden Anfragen zu `https://example.com/apollo-dienstplan/public/` nicht mehr an WordPress weitergeleitet und Apache liefert die Dateien aus `public_html/apollo-dienstplan/public/` aus (inkl. `public/.htaccess` → `index.php`).

---

`AllowOverride All` (Apache) wird für die mitgelieferte `public/.htaccess` benötigt (Weiterleitung aller Anfragen an `index.php`).

---

## 6. Rechte und Sicherheit

```bash
# Webserver-Benutzer (z.B. www-data) muss lesen können
sudo chown -R www-data:www-data /var/www/dienstplan

# config/ nicht über das Web erreichbar machen (Apache/Nginx root = public/)
# Berechtigungen für Konfiguration
chmod 600 /var/www/dienstplan/config/config.php
```

Da der DocumentRoot auf `public/` zeigt, sind `config/`, `src/` und `sql/` nicht direkt über den Browser erreichbar.

---

## 7. Nach dem Deployment prüfen

- **Eigene Domain:** Startseite `https://dienstplan.example.com/`, Bereiche `/shifts`, `/roles`, usw.
- **Unterverzeichnis:** Startseite `https://example.com/dienstplan/`, Bereiche `.../dienstplan/shifts`, usw.
- Keine PHP-Fehler und keine Anzeige von Pfaden/Passwörtern (in Produktion `display_errors` aus).

---

## Kurz-Checkliste

- [ ] Datenbank angelegt, Schema und Migrationen importiert
- [ ] Code auf Server (git clone oder rsync)
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `config/config.php` mit Server-DB-Zugangsdaten (oder Umgebungsvariablen)
- [ ] **Unterverzeichnis:** `BASE_PATH` gesetzt (z. B. `/dienstplan`), in Apache `RewriteBase /dienstplan/` in `public/.htaccess`
- [ ] DocumentRoot/Alias zeigt auf `public/`, Rewrite auf `index.php`
- [ ] Rechte für Webserver-Benutzer, `config/config.php` geschützt (z. B. chmod 600)
