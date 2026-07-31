# Reference Code Library

Reference Code Library is a generic WordPress plugin for publishing a searchable, theme-independent library of documented code examples.

Developed and maintained by **ReTechX LLC**.

The plugin stores code entries as inert text. It does not evaluate PHP, inject JavaScript, apply CSS stored in entries, or execute imported snippets. Version 2.2 adds a separate administrator-controlled Advanced CSS setting for the library presentation itself.

## Current Version: 2.2.1

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
- Adds explicit content, hero, and card alignment controls
- Adds standard or relaxed theme-style isolation for Colibri and other theme builders
- Adds plugin, inherited-theme, and custom typography modes
- Adds local font-stack selectors for body, headings, accent/buttons, and code
- Adds base font-size and unitless line-height controls
- Adds a native WordPress Advanced CSS editor with an enable switch and one-version recovery
- Includes typography, alignment, sizing, and optional Advanced CSS in portable branding data

## Installation

1. When a legacy predecessor version is installed, deactivate it first. Do not delete its content until the migration has been verified.
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

Legacy predecessor shortcodes remain supported for existing installations.

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

## Typography and theme compatibility

Open **Code Library → Appearance** to choose how the library interacts with the active theme:

- **Use Code Library typography** keeps the packaged font stacks.
- **Inherit typography from the active theme** deliberately follows the surrounding site.
- **Custom typography** lets an administrator select local font stacks or enter a stack already loaded by the theme.
- **Standard isolation** explicitly controls library headings, controls, alignment, and text behavior.
- **Allow more theme styling** relaxes those protections.

General content, hero content, and cards have separate start/center alignment controls. The default is `start`, which prevents a centered shortcode container from silently centering the entire library.

## Advanced CSS

The Appearance screen includes a CSS editor powered by the WordPress code-editor assets when syntax highlighting is enabled for the current user. CSS loads after the packaged frontend stylesheet only when **Enable Advanced CSS on the frontend** is checked.

Scope rules to `.rcl-library`, omit `<style>` tags, and keep site-specific CSS out of portable exports unless the export option is deliberately selected. The plugin keeps the immediately previous saved CSS version so an administrator can restore it.

## Upgrade compatibility

Reference Code Library retains legacy internal post-type, taxonomy, metadata, shortcode, and block identifiers so existing libraries remain available after an upgrade.

These identifiers are implementation details retained for data compatibility. All public-facing labels, documentation, branding, and package formats use the generic Reference Code Library name.

## Development notes

- WordPress 6.0 or later
- PHP 7.4 or later
- No build process is required
- Run `php -l` against PHP files before packaging

## Publisher

Reference Code Library is developed and maintained by **ReTechX LLC**.

Copyright © 2026 ReTechX LLC.

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
