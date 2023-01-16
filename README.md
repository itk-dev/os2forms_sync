# OS2Forms sync

## Installation

```sh
composer require os2forms/os2forms_sync
drush pm:enable os2forms_sync
```

## Usage

Publish a webform by checking “Publish” under webform setting » Third party
settings » OS2Forms » OS2Forms sync

All published webforms are listed on `/os2forms/sync/webform`.

Imported webforms are listed on `/os2forms/sync/webform/imported`.

## Drush commands

```sh
drush os2forms-sync:import --help
```

## Coding standards

```sh
docker run --rm --interactive --tty --volume ${PWD}:/app itkdev/php7.4-fpm:latest composer install
docker run --rm --interactive --tty --volume ${PWD}:/app itkdev/php7.4-fpm:latest composer coding-standards-check

docker run --rm --interactive --tty --volume ${PWD}:/app node:18 yarn --cwd /app install
docker run --rm --interactive --tty --volume ${PWD}:/app node:18 yarn --cwd /app coding-standards-check
```

## Code analysis

```sh
docker run --rm --interactive --tty --volume ${PWD}:/app itkdev/php7.4-fpm:latest composer install
docker run --rm --interactive --tty --volume ${PWD}:/app itkdev/php7.4-fpm:latest composer code-analysis
```
