# seablast-dist
Distribution of Seablast for PHP - a seed application

## Deployment
Create database with `Collation=utf8_general_ci` (create also separate testing database so that phinxlog migration_name doesn't overlap)

Run [assemble.sh](assemble.sh) to

- create `conf/phinx.local.php` based on [conf/phinx.dist.php](conf/phinx.dist.php) including the name of the database (and testing database) created above
- create `conf/app.conf.local.php` based on [conf/app.conf.local.dist.php](conf/app.conf.local.dist.php) including the phinx environment to be used and change any settings you like. (OPTIONAL)

Edit these two configuration files; then re-run assemble.sh

Note: the current configuration is in the `conf/phinx.local.php` so that it is automatically NOT commited to git
