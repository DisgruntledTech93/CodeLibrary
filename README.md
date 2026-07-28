# Reference Code Library

Reference Code Library is a generic WordPress plugin for publishing a searchable, theme-independent library of documented code examples.

The plugin stores code as inert text. It does not evaluate PHP, inject JavaScript, apply CSS, or execute imported snippets.

## Version 2.0.0

Version 2 separates the library engine from the content it contains:

- Installs without bundled code entries or organization-specific source archives
- Renames the editing experience to Code Library, Code Entries, and Add Code
- Imports and exports validated `reference-code-library/v2` JSON packs
- Previews imports before writing WordPress records
- Supports update, skip, and duplicate conflict policies
- Exports the full library, one collection, or selected entries
- Supports optional pack branding
- Provides custom title, introduction, logo, colors, layout presets, and display controls
- Adds generic shortcodes and a Code Library block
- Preserves legacy v1 content, identifiers, blocks, and shortcodes

## Installation

1. When Missouri Accessibility Library v1 is installed, deactivate it first. Do not uninstall its content.
2. Upload the `reference-code-library` folder to `/wp-content/plugins/`, or install the ZIP through WordPress.
3. Activate **Reference Code Library**.
4. Open **Code Library → Add Code** to create entries manually, or **Code Library → Import / Export** to import a JSON pack.
5. Create a page containing the **Code Library** block or `[code_library]` shortcode.

## Shortcodes

```text
[code_library]
[code_collection slug="css-patterns"]
[code_entry slug="visible-focus-example"]
```

Legacy v1 shortcodes remain supported:

```text
[mo_accessibility_library]
[mo_accessibility_collection slug="css-patterns"]
[mo_accessibility_pattern slug="visible-focus-example"]
```

## Library-pack schema

A pack uses this root structure:

```json
{
  "schema": "reference-code-library/v2",
  "pack": {},
  "branding": {},
  "collections": [],
  "entries": []
}
```

Use **Code Library → Import / Export** to download a blank template and a populated example.

## Upgrade compatibility

The plugin intentionally retains the original internal post type, taxonomy, and metadata identifiers from Missouri Accessibility Library v1. Existing entries remain visible when v2 replaces v1. User-facing labels and new package formats are generic.

## Development notes

- WordPress 6.0 or later
- PHP 7.4 or later
- No build process is required
- Run `php -l` against PHP files before packaging

## License

GPL-2.0-or-later
