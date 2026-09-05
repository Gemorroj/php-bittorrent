# AGENTS.md

This is a small PHP library for working with BitTorrent data (bencode encode/decode, torrent files).

## Requirements

- PHP >= 8.4

## Project layout

- `src/` — library sources (`BitTorrent\` namespace, PSR-4)
- `tests/` — PHPUnit tests
- `composer.json` — dependencies and autoloading
- `phpunit.xml.dist` — PHPUnit configuration
- `phpstan.neon` — PHPStan configuration
- `.php-cs-fixer.dist.php` — code style configuration

## Commands

Install dependencies:

```bash
composer install
```

Run tests:

```bash
vendor/bin/phpunit
```

Static analysis:

```bash
vendor/bin/phpstan analyse
```

Check code style:

```bash
vendor/bin/php-cs-fixer fix --dry-run --diff
```

Apply code style fixes:

```bash
vendor/bin/php-cs-fixer fix
```

## Conventions

- Follow PSR-4 autoloading (`BitTorrent\` maps to `src/`).
- Keep the public API of `Encoder`, `Decoder`, and `Torrent` backward compatible unless there is a good reason to break it.
- Add or update tests in `tests/` for behavior changes.
- Keep code style clean; run php-cs-fixer before committing.
- Ensure PHPStan reports no errors and all tests pass before finishing a task.
