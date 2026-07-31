# Reference Code Library

Reference Code Library is a generic WordPress plugin for publishing a searchable, theme-independent library of documented code examples.

The plugin stores code as inert text. It does not evaluate PHP, inject JavaScript, apply CSS, or execute imported snippets.

## Version 2.1.0

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
- Adds multiple working-example screenshots to each code entry
- Uses the native Media Library for screenshot selection and responsive image output
- Imports and exports portable ZIP packs containing `library.json` plus screenshot files
- Retains JSON/TXT compatibility for content-only library packs

## Installation

1. When Missouri Accessibility Library v1 is installed, deactivate it first. Do not uninstall its content.
2. Upload the `reference-code-library` folder to `/wp-content/plugins/`, or install the ZIP through WordPress.
3. Activate **Reference Code Library**.
4. Open **Code Library → Add Code** to create entries manually, attach working-example screenshots, or **Code Library → Import / Export** to import a JSON, TXT, or portable ZIP pack.
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

A JSON/TXT pack uses this root structure. A portable ZIP pack places the same manifest in `library.json` and stores referenced screenshots under `images/`:

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

## Working examples

Each code entry has a **Working Examples** panel beneath Code Details. Use **Add screenshots** to select one or more images from the WordPress Media Library. Every screenshot can store:

- An example type such as Before, After, Working result, Configuration, Inspector, Mobile, or Accessibility test result
- Entry-specific alternative text
- A visible caption
- A manual display order

The frontend renders responsive WordPress attachment images inside semantic figures and provides a normal full-size image link. The plugin does not add a lightbox or modal.

## Portable screenshot packs

Content-only exports remain JSON. When the export includes working-example screenshots, the plugin creates a ZIP with this structure:

```text
library-pack.zip
├── library.json
└── images/
    └── example-result.png
```

The importer accepts JSON/TXT files up to 5 MB and ZIP packs up to 25 MB. ZIP contents are preflighted and then validated again after extraction. Only `library.json` and validated JPEG, PNG, GIF, or WebP files under `images/` are accepted.
