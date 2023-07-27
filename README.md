# OS2Forms sync

## Installation

```sh
composer require os2forms/os2forms_sync
drush pm:enable os2forms_sync
```

Edit settings on `/admin/os2forms_sync/settings`.

## API

See [API](docs/API.md) for details on the API.

## Usage

Publish a webform by checking “Publish” under webform setting » Third party
settings » OS2Forms » OS2Forms sync

All published webforms are listed on `/admin/os2forms/sync/webform` (API data on
`/os2forms/sync/jsonapi/webform`).

Webforms available for import are listed on `/admin/os2forms/sync/webform`.

## Drush commands

```sh
drush os2forms-sync:import --help
```

## Coding standards

```sh
docker run --rm --interactive --tty --volume ${PWD}:/app itkdev/php8.1-fpm:latest composer install
docker run --rm --interactive --tty --volume ${PWD}:/app itkdev/php8.1-fpm:latest composer coding-standards-check

docker run --rm --interactive --tty --volume ${PWD}:/app node:18 yarn --cwd /app install
docker run --rm --interactive --tty --volume ${PWD}:/app node:18 yarn --cwd /app coding-standards-check
```

## Code analysis

```sh
docker run --rm --interactive --tty --volume ${PWD}:/app itkdev/php8.1-fpm:latest composer install
docker run --rm --interactive --tty --volume ${PWD}:/app itkdev/php8.1-fpm:latest composer code-analysis
```
