#
# Makefile
# Les Polypodes, 2014
# Licence: MIT
# Source: https://github.com/polypodes/Build-and-Deploy/blob/master/build/Makefile

# To enable this quality-related tasks, add these dependencies to your composer.json:
# they'll be available in the ./bin dir :
#
#    "require-dev": {
#	     (...)
#        "phpunit/phpunit":             "~3.7",
#        "squizlabs/php_codesniffer":   "2.0.x-dev",
#        "sebastian/phpcpd":            "*",
#        "phploc/phploc" :              "*",
#        "phpmd/phpmd" :                "2.0.*",
#        "pdepend/pdepend" :            "2.0.*",
#        "fabpot/php-cs-fixer":         "@stable"
#    },


# Usage:

# me@myserver$~: make help
# me@myserver$~: make install
# me@myserver$~: make reinstall
# me@myserver$~: make update
# me@myserver$~: make tests
# me@myserver$~: make quality
# etc.

############################################################################
# Vars

# some lines may be useless for now, but these are nice tricks:
PWD         := $(shell pwd)
# Retrieve db connection params, triming white spaces
DB_USER	    := $(shell if [ -f app/config/parameters.yml ] ; then cat app/config/parameters.yml | grep 'database_user' | sed 's/database_user: //' | sed 's/^ *//;s/ *$$//' ; fi)
DB_PASSWORD := $(shell if [ -f app/config/parameters.yml ] ; then cat app/config/parameters.yml | grep 'database_password' | sed 's/database_password: //' | sed 's/null//' | sed 's/^ *//;s/ *$$//' ; fi)
DB_NAME     := $(shell if [ -f app/config/parameters.yml ] ; then cat app/config/parameters.yml | grep 'database_name' | sed 's/database_name: //' | sed 's/^ *//;s/ *$$//' ; fi)
DB_v1DUMPSQL := doc/backups/prod/euradionantes_prod.v1.sql
DB_v1PARTIALDUMPSQL := doc/backups/prod/euradionantes_prod_partial.v1.sql
DB_SQLDIR   := doc/upgrade/sql
DB_SQLQUIET :=  2>/dev/null
VENDOR_PATH := $(PWD)/vendor
BIN_PATH    := $(PWD)/bin
WEB_PATH    := $(PWD)/web
NOW         := $(shell date +%Y-%m-%d--%H-%M-%S)
REPO        := "https://github.com/..."
BRANCH      := 'master'
# Colors
YELLOW      := $(shell tput bold ; tput setaf 3)
GREEN       := $(shell tput bold ; tput setaf 2)
RESETC      := $(shell tput sgr0)

# Custom MAKE options
ifndef VERBOSE
  MAKEFLAGS += --no-print-directory
endif

############################################################################
# Mandatory tasks:

all: .git/hook/pre-commit vendor/autoload.php check help ftp

vendor/autoload.php:
	@composer self-update
	@composer install --optimize-autoloader

.git/hook/pre-commit:
	@curl -s -o .git/hooks/pre-commit https://raw.githubusercontent.com/polypodes/Build-and-Deploy/master/hooks/pre-commit
	@chmod +x .git/hooks/pre-commit

############################################################################
# Specific, project-related sf2 tasks:

integration:
	@echo
	@cd front-end
	@npm install
	@cd ../

dumps:
	@echo "Creating dump folder for SQL exports..."
	@mkdir ./dumps

mysqldump: dumps
	@echo "Dumping existing db into ./dumps ..."
	@mysqldump --user=${DB_USER} --password=${DB_PASSWORD} ${DB_NAME} > dumps/${NOW}.sql 2>/dev/null

mysqlinfo: dumps
	@echo "mysql --user=${DB_USER} --password=${DB_PASSWORD} ${DB_NAME}"

data: vendor/autoload.php
#	@echo "Install initial datas..."
#	@php app/console dbal:data:initialize --purge

fixtures: vendor/autoload.php
#	@echo "Install fixtures in db..."
#	@php app/console dbal:fixtures:load --purge

track:
	@echo "Trying to process and save the current broadcasted track on air:"
	@php app/console euradionantes:track

tracks:
	@echo "Each 10 seconds, try to process and save any new broadcasted track on air:"
	@echo "CTRL+C to stop this."
	@while true; do php app/console euradionantes:track; sleep 10; done;

############################################################################
# v1 to v2 MySQL upgrade


importRemote: dropDb
	@echo
	@echo "Importing remote v1 sql backup into a new empty db..."
	mysql --user=${DB_USER} --password=${DB_PASSWORD} -e 'CREATE DATABASE ${DB_NAME}' ${DB_SQLQUIET}
	mysql --user=${DB_USER} --password=${DB_PASSWORD} ${DB_NAME} < ${DB_v1DUMPSQL} ${DB_SQLQUIET}

mysqlUpgrade: importRemote
	@echo
	@echo "Dumping tables before update for news__post__tag, news__tag & news__post..."
	mysqldump --user=${DB_USER} --password=${DB_PASSWORD} ${DB_NAME} -t news__post_tag > ${DB_SQLDIR}/v1_news__post_tag.sql ${DB_SQLQUIET}
	mysqldump --user=${DB_USER} --password=${DB_PASSWORD} ${DB_NAME} -t news__tag > ${DB_SQLDIR}/v1_news__tag.sql ${DB_SQLQUIET}
	mysqldump --user=${DB_USER} --password=${DB_PASSWORD} ${DB_NAME} news__post > ${DB_SQLDIR}/v1_news__post.sql ${DB_SQLQUIET}

	@echo
	@echo "Exporting collections from news__post..."
	mysql --user=${DB_USER} --password=${DB_PASSWORD} ${DB_NAME} -e "SELECT CONCAT('UPDATE news__post SET collection_id=', COALESCE(category_id, 'NULL'), ' WHERE id=', id, ';') from news__post" > ${DB_SQLDIR}/v1_news__post_tmp.sql ${DB_SQLQUIET}

	@echo
	@echo "Removing first line & trailing comma from news__post dump..."
	tail -n +2 ${DB_SQLDIR}/v1_news__post_tmp.sql > ${DB_SQLDIR}/v2_news__post_collection.sql

	@echo
	@echo "Preparing v1 db to upgrade (a/c)"
	mysql --user=${DB_USER} --password=${DB_PASSWORD} ${DB_NAME} < ${DB_SQLDIR}/v1tov2-a.sql ${DB_SQLQUIET}

	@echo
	@echo "Applying new schema updates using Doctrine..."
	$(MAKE) schemaDb

	@echo
	@echo "Preparing updated v1 db to upgrade (b/c)"
	mysql --user=${DB_USER} --password=${DB_PASSWORD} ${DB_NAME} < ${DB_SQLDIR}/v1tov2-b.sql ${DB_SQLQUIET}

	@echo
	@echo "Preparing classification__tag SQL script"
	sed -e "s/news__tag/classification__tag/g" ${DB_SQLDIR}/v1_news__tag.sql > ${DB_SQLDIR}/v2_classification__tag.sql

	@echo
	@echo "Re-importing news__tag rows into classification__tag prepared dump..."
	mysql --user=${DB_USER} --password=${DB_PASSWORD} ${DB_NAME} < ${DB_SQLDIR}/v2_classification__tag.sql ${DB_SQLQUIET}

	@echo
	@echo "Re-importing news__post_tag using raw dump..."
	mysql --user=${DB_USER} --password=${DB_PASSWORD} ${DB_NAME} < ${DB_SQLDIR}/v1_news__post_tag.sql ${DB_SQLQUIET}

	@echo
	@echo "Importing classification_collection using prepared SQL upgrade file..."
	mysql --user=${DB_USER} --password=${DB_PASSWORD} ${DB_NAME} < ${DB_SQLDIR}/v2_classification__collection.sql ${DB_SQLQUIET}

	@echo
	@echo "Restoring context column in classification__tag and __collection tables (c/c)..."
	mysql --user=${DB_USER} --password=${DB_PASSWORD} ${DB_NAME} < ${DB_SQLDIR}/v1tov2-c.sql ${DB_SQLQUIET}

	@echo
	@echo "Re-importing news__post collection joins using prepared dump..."
	mysql --user=${DB_USER} --password=${DB_PASSWORD} ${DB_NAME} < ${DB_SQLDIR}/v2_news__post_collection.sql ${DB_SQLQUIET}

	@echo
	@echo "Applying new schema updates using Doctrine..."
	$(MAKE) schemaDb
	$(MAKE) updateHost

	@echo
	@echo "Done."

updateHost:
	@echo
	@echo "Updating vhost in SonataPage for 'http://euradionantes/"
	mysql --user ${DB_USER} --password=${DB_PASSWORD} -e 'UPDATE `page__site` SET `host` = "euradionantes" WHERE `page__site`.`id` = 3;' ${DB_NAME} ${DB_SQLQUIET}
	@echo "Host updated."

rePublish:
	@echo
	@echo "Re-pulishing the all Sonata Page based website"
	php app/console sonata:page:update-core-routes --site=all
	php app/console sonata:page:create-snapshots --site=all

mysqlRefresh:
	$(MAKE) mysqldump

	@echo
	@echo "Importing remote v1 sql partial backup into a filled db..."
	mysql --user=${DB_USER} --password=${DB_PASSWORD} ${DB_NAME} < ${DB_v1PARTIALDUMPSQL} ${DB_SQLQUIET}

	@echo
	@echo "Removing table constraints on v2 tables"
	mysql --user=${DB_USER} --password=${DB_PASSWORD} ${DB_NAME} < ${DB_SQLDIR}/v1tov2-d.sql #${DB_SQLQUIET}

	@echo
	@php app/console doctrine:schema:update --force

	@echo
	@echo "Adapting imported content to v2 database"
	mysql --user=${DB_USER} --password=${DB_PASSWORD} ${DB_NAME} < ${DB_SQLDIR}/v1tov2-e.sql #${DB_SQLQUIET}

	@echo
	@php app/console doctrine:schema:update --force

	@echo
	@echo "Done."

############################################################################
# Generic sf2 tasks:

help:
	@echo "\n${GREEN}Usual tasks:${RESETC}\n"
	@echo "\tTo prepare install:\tmake"
	@echo "\tTo install:\t\tmake install"
	@echo "\tTo update from git:\tmake update"
	@echo "\tTo reinstall:\t\tmake reinstall (will erase all your datas)\n\n"

	@echo "${GREEN}Other specific tasks:${RESETC}\n"
	@echo "\tTo check code quality:\tmake quality"
	@echo "\tTo fix code style:\tmake cs-fix"
	@echo "\tTo clear all caches:\tmake clear"
	@echo "\tTo run tests:\t\tmake tests (will erase all your datas)\n"

check:
	@php app/check.php

pull:
	@git pull origin $(BRANCH)


dropDb: vendor/autoload.php mysqldump
	@echo
	@echo "Drop database..."
	@php app/console doctrine:database:drop --force

createDb: vendor/autoload.php
	@echo
	@echo "Create database..."
	@php app/console doctrine:database:create
	@php app/console doctrine:schema:update --force

schemaDb: vendor/autoload.php mysqldump
	@echo
	@echo "Configure database schema..."
	@php app/console doctrine:schema:update --force

assets:
	@echo "\nPublishing assets..."
	@php app/console assets:install --symlink

clear: vendor/autoload.php
	@echo
	@echo "Resetting caches..."
	@php app/console cache:clear --env=prod --no-debug
	@php app/console cache:clear --env=dev

explain:
	@echo "git pull origin master + update db schema + build integration + copy new assets + rebuild prod cache"
	@echo "Note you can change the git remote repo username in .git/config"

behavior: vendor/autoload.php
	@echo "Run behavior tests..."
	@bin/behat --lang=fr  "@AcmeDemoBundle"

unit: vendor/autoload.php
	@echo "Run unit tests..."
	@php bin/phpunit -c build/phpunit.xml -v

codecoverage: vendor/autoload.php
	@echo "Run coverage tests..."
	@bin/phpunit -c build/phpunit.xml -v --coverage-html ./build/codecoverage

continuous: vendor/autoload.php
	@echo "Starting continuous tests..."
	@while true; do bin/phpunit -c build/phpunit.xml -v; done

sniff: vendor/autoload.php
	@bin/phpcs --standard=PSR2 src -n

dry-fix:
	@bin/php-cs-fixer fix . --config=sf23 --dry-run -vv

cs-fix:
	@bin/phpcbf --standard=PSR2 src
	@bin/php-cs-fixer fix . --config=sf23 -vv

#quality must remain quiet, as far as it's used in a pre-commit hook validation
quality: sniff dry-fix

build:
	@mkdir -p build/pdepend

# packagist-based dev tools to add to your composer.json. See http://phpqatools.org
stats: quality build
	@echo "Some stats about code quality"
	@bin/phploc src
	@bin/phpcpd src
	@bin/phpmd src text codesize,unusedcode
	@bin/pdepend --summary-xml=build/pdepend/summary.xml --jdepend-chart=build/pdepend/jdepend.svg --overview-pyramid=build/pdepend/pyramid.svg src

deploy: vendor/autoload.php update
	@composer install --optimize-autoloader
	@$(MAKE) schemaDb
	@$(MAKE) clear
	@$(MAKE) done

update: vendor/autoload.php
	@$(MAKE) explain
	@$(MAKE) pull

robot:
	@echo "User-agent: *" > $(WEB_PATH)/robots.txt
	@echo "Disallow: " >> $(WEB_PATH)/robots.txt

ftp:
	@mkdir -p web/uploads/ftp

unrobot:
	@echo "User-agent: *" > $(WEB_PATH)/robots.txt
	@echo "Disallow: /" >> $(WEB_PATH)/robots.txt

done:
	@echo
	@echo "${GREEN}Done.${RESETC}"

# Tasks sets:

all: vendor/autoload.php check

prepareDb: createDb schemaDb

purgeDb: dropDb createDb schemaDb

install: prepareDb data assets clear done

reinstall: dropDb install

tests: reinstall fixtures behavior unit codecoverage

############################################################################
# .PHONY tasks list

.PHONY: integration data fixtures help check all pull dropDb createDb myqldump
.PHONY: schemaDb assets clear explain behavior unit codecoverage
.PHONY: continuous sniff dry-fix cs-fix quality stats deploy done prepareDb purgeDb
.PHONY: install reinstall test update robot unrobot ftp
# vim:ft=make
