# Contributing

Thank you for your interest in Bulk Actions Manager.

## Reporting issues

Open a [GitHub issue](https://github.com/ProgrammerNomad/Bulk-Actions-Manager/issues) with:

* WordPress and PHP versions
* Steps to reproduce
* Expected vs actual behavior
* Screenshots or error messages if relevant

For **Cannot load** admin errors, include the full URL from the browser address bar.

## Pull requests

1. Fork the repository and create a feature branch from `main`.
2. Keep changes focused - one fix or feature per PR when possible.
3. Match existing code style (WordPress coding standards, native admin UI patterns).
4. Test on a local WordPress install (copy `bulk-actions-manager/` to `wp-content/plugins/`).
5. Update [`CHANGELOG.md`](/docs/CHANGELOG.md) for user-visible changes.
6. Open a PR with a clear description and test notes.

Please open an issue before large features or architectural changes so approach can be discussed first.

## Development setup

```bash
git clone https://github.com/ProgrammerNomad/Bulk-Actions-Manager.git
# Copy the inner plugin folder to WordPress:
# bulk-actions-manager/ → wp-content/plugins/bulk-actions-manager/
```

Enable in `wp-config.php` for debugging:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

## Documentation

* User-facing overview: [`README.md`](../README.md)
* Feature reference: [`FEATURES.md`](FEATURES.md)
* Configuration: [`CONFIGURATION.md`](CONFIGURATION.md)
* Releases: [`CHANGELOG.md`](CHANGELOG.md)
* WordPress.org readme: [`bulk-actions-manager/readme.txt`](../bulk-actions-manager/readme.txt)

## License

Contributions are licensed under the same terms as the project: GPL-2.0-or-later.
