# [Euradionantes.eu](http://www.euradionantes.eu) Web Platform


Built atop [Symfony2](http://symfony.com) & [Sonata Project](http://sonata-project.org).

Initiated [here, 2 years ago](https://github.com/DILL44/euradio), currently in a new WIP state,

## Installation

### 1/3 - Install via GitHub

```bash
$ git clone https://github.com/polypodes/EuradioNantes.eu.git
$ cd EuradioNantes.eu
$ composer install
$ php app/console doctrine:database:create
$ php app/console doctrine:schema:update --force
```

Then create a local vhost: An Apache 2.4 vhost configuration is given in `/doc`.

### 2/3 - Create users

Create a `admin`/`admin` + `user`/`user` logins.

```bash
$ php app/console fos:user:create admin admin@domain.com admin --super-admin
$ php app/console fos:user:create user user@domain.com user
$ php app/console fos:user:promote user SONATA
```


Check out [FOSUser documentation](https://github.com/FriendsOfSymfony/FOSUserBundle/blob/v1.3.6/Resources/doc/command_line_tools.md)

### 3/3 - Create website

Command lines to create a website on a `euradionantes2015` local vhost, with `fr` locale.

```bash
$ php app/console  sonata:page:create-site --enabled=true --name=EuradioNantes --locale=fr --host=euradionantes2015 --relativePath=/ --enabledFrom=now --enabledTo="+20 years" --default=true

Creating website with the following information :
  name : EuradioNantes
  site : http(s)://euradionantes2015
  enabled :  from Wed, 01 Jul 2015 10:05:09 +0200 => to Tue, 01 Jul 2025 10:05:09 +0200

Confirm site creation ?yes

Site created !

You can now create the related pages and snapshots by running the followings commands:

$ php app/console sonata:page:update-core-routes --site=1
$ php app/console sonata:page:create-snapshots --site=1
```

## Install from previous instance

### 1/2 - Prepare the db

Fetch ans sql dump from production env, name it `euradionantes_prod.v1.sql` and place it into `./doc/backups/prod`

Create a new empty database with a new working name

Reference this new database name inside the `app/config/parameters.yml` file

Make sure your app can access this databse with a valid SQL user lgin and password

### 2/2 Run the upgrade script:

From the root folder of your instance:

```bash
$ make mysqlUpgrade
```

## License

MIT Licensed.

You can find a copy of this software here: [polypodes/EuradioNantes.eu](https://github.com/polypodes/EuradioNantes.eu)

