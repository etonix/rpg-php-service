# tbrpg-php-service
PHP web-service for RPG project

## How to setup
* Set config in `./configs/config.ini`
* `db_prefix` is table name prefix, if you set this to "rpg_" your table name will be like "rpg_player"
* `db_host` database server address
* `db_port` database server port
* `db_name` database name
* `db_user` database username
* `db_pass` database password
* Then browse `sql-importer.php` from web-browser, if you see `Done ;)` with no errors, that is done the web-service is ready to use
* You should remove `sql-importer.php` after imported

## How to update
* Browse `sql-importer.php?update={{version}}`, for example `sql-importer.php?update=1.10`

## Client-side implemented project
* [rpg-web-service-client](https://github.com/insthync/rpg-web-service-client) - Client for project [Turnbase RPG Template](https://assetstore.unity.com/packages/templates/systems/turnbase-rpg-template-107578) which selling at Unity Asset Store

**I might make implement for UNREAL blueprint project later :)**
