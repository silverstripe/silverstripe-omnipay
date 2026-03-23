# Agent / contributor notes

## After code changes

Run the project checks so style and behaviour stay consistent:

- **`composer lint`** — PHPCS on `src/` and `tests/`.
- **`composer test`** — PHPUnit suite.

Use these after any substantive edit; fix failures before finishing.

## Database schema (`$db` fields)

If you add or change **`DataObject::$db`** (or other model/schema-affecting config), the database must be updated before you can rely on the result in a running app.

From a SilverStripe project that includes this module, run:

```bash
sake db:build --flush
```

(or the equivalent `vendor/bin/sake` invocation your project uses). The `--flush` rebuilds caches so the updated schema is loaded.

Then re-check behaviour (and still run **`composer lint`** and **`composer test`** where applicable).
