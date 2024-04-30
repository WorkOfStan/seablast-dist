# seablast-dist
Distribution of Seablast for PHP - a seed application

## Deployment
Create database with `Collation=utf8_general_ci` (create also separate testing database so that phinxlog migration_name doesn't overlap)

Run [assemble.sh](assemble.sh) to

- create `conf/phinx.local.php` based on [conf/phinx.dist.php](conf/phinx.dist.php) including the name of the database (and testing database) created above
- create `conf/app.conf.local.php` based on [conf/app.conf.local.dist.php](conf/app.conf.local.dist.php) including the phinx environment to be used and change any settings you like. (OPTIONAL)

Edit these two configuration files; then re-run assemble.sh

Note: the current configuration is in the `conf/phinx.local.php` so that it is automatically NOT commited to git

### Folders, where web can write
- cache and log (and also e.g. app specific uploads)
- rights: 2775
- owner: server user, e.g. composerit
- group: web user, e.g. www-data
- run [permissions.sh](permissions.sh) to set up these permissions
- upload_max_filesize in php.ini set to 8M

### Security
- enforcing HTTP to HTTPS MUST happen on the server-side, e.g. like this in `/etc/apache2/sites-enabled/www.plysonika.cz.conf`
```htaccess
<VirtualHost *:80>
        RewriteEngine On
        RewriteCond %{REQUEST_URI} !^/server-status.*
        RewriteRule ^/?(.*) https://%{SERVER_NAME}/$1 [R,L]
```

## Examples

- try /redir to see how it is redirected to /kontakt
- try /use-mirror to see how API call works

## App directory description
| Directory | Description |
|-----|------|
| .github/ | Automations |
| assets/ | Frontend assets. When dealing with numerous assets, categorize them into specific subdirectories. |
| cache/ | Latte cache |
| conf/ | All configuration files: Seablast app, PHPStan, phinx |
| log/ | All kind of logs |
| models/ | If not all classes are models, change it to src/Models, src/Data, src/Exceptions... |
| views/ | Latte templates |
