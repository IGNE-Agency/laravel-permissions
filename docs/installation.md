# Installation

## Requirements

- PHP 8.4
- Laravel 12 or 13 (`illuminate/support` and `illuminate/contracts` `^12.0|^13.0`)
- spatie/laravel-permission 7 or 8, installed and migrated in the app

## Add the repository

The package lives in the private GitHub repository `IGNE-Agency/laravel-permissions` and is not on Packagist. Composer needs it as a `vcs` repository:

```bash
composer config repositories.igne-permissions vcs https://github.com/IGNE-Agency/laravel-permissions.git
composer require igne/laravel-permissions:^1.0
```

This adds to `composer.json`:

```json
{
    "repositories": {
        "igne-permissions": {
            "type": "vcs",
            "url": "https://github.com/IGNE-Agency/laravel-permissions.git"
        }
    },
    "require": {
        "igne/laravel-permissions": "^1.0"
    }
}
```

Composer resolves versions from the repository's tags. Releases follow semantic versioning: any change to a generated permission name is a major version, because the names are stored in every installing app's database.

## GitHub token

Every machine that runs `composer install` or `composer update` needs read access to the repository: developers, CI and deploy targets. Use a fine-grained personal access token with **Contents: read** on `IGNE-Agency/laravel-permissions`, or a classic token with the `repo` scope. Never commit a token.

**Local machine.** Store it once in Composer's global auth file:

```bash
composer config --global github-oauth.github.com <token>
```

**CI (GitHub Actions).** Put the token in a repository secret and expose it through `COMPOSER_AUTH`:

```yaml
env:
  COMPOSER_AUTH: '{"github-oauth": {"github.com": "${{ secrets.COMPOSER_GITHUB_TOKEN }}"}}'
```

**Deploys (Forge and similar).** Add the same `github-oauth` entry to the server's `~/.composer/auth.json`, or set `COMPOSER_AUTH` in the deploy environment. Forge also accepts the token on the server's Composer credentials screen.

## Service provider

Laravel discovers `Igne\Permissions\PermissionsServiceProvider` through package discovery. Nothing to register.

## Publish the config

```bash
php artisan vendor:publish --tag=permissions-config
```

This copies `config/permissions.php` into the app. The package merges its defaults underneath, so the app only needs to set the keys it changes.

## Config keys

| Key | Default | Meaning |
|-----|---------|---------|
| `types` | `[]` | The permission enums the package builds names for. Each implements `Igne\Permissions\Contracts\PermissionEnum`. |
| `separator` | `':'` | The character between the segments of a name: `nova:document:view`. |
| `normalizer` | `'camel'` | The casing applied to every segment: `camel`, `snake`, `kebab` or `studly`. Any other value throws. |
| `prefix` | `null` | An optional first segment for every name. `null` omits it. |

The registry is rebuilt from config on every `permissions()` call, so a test can change these keys at runtime.

Changing `separator`, `normalizer` or `prefix` changes every generated name. Once permissions exist in the database, that is a data migration in the app, not a config change.
