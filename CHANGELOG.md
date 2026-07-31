# Changelog

## 2.2.1

- Changed the plugin publisher to ReTechX LLC.
- Updated the repository README and WordPress plugin readme.
- Removed public-facing references to the predecessor organization.
- Reworded legacy migration messaging using generic terminology.
- Preserved all existing entries, settings, shortcodes, blocks, and internal compatibility identifiers.

## 2.2.0

- Added explicit start/left or centered alignment controls for general content, hero content, and cards.
- Added standard and relaxed theme-style isolation modes for improved consistency across Colibri and other themes.
- Added plugin, inherited-theme, and custom typography modes.
- Added body, heading, accent/button, and code font-stack selectors with optional custom local stacks.
- Added base font-size and unitless line-height controls.
- Added an Advanced CSS editor using the native WordPress code editor with CSS linting and syntax highlighting when enabled for the user.
- Added a frontend enable/disable switch and one-version recovery for Advanced CSS.
- Added typography, alignment, sizing, and optional Advanced CSS to branding imports and exports.
- Preserved all 2.1.0 screenshot-gallery, portable-pack, legacy-content, block, and shortcode behavior.

## 2.1.0

- Added a Working Examples screenshot gallery to every code entry.
- Added multi-image selection through the native WordPress Media Library.
- Added screenshot type, alternative text, caption, ordering, and removal controls.
- Added responsive frontend figures with full-size image links and no custom modal dependency.
- Added portable ZIP library packs containing `library.json` and an `images/` directory.
- Added screenshot import from ZIP files and optional HTTPS image URLs.
- Added screenshot export for full libraries, collections, and selected entries.
- Preserved backward compatibility with existing JSON/TXT packs and entries without screenshots.
- Added ZIP path, file-count, expanded-size, and real-image validation safeguards.

## 2.0.0

- Rebuilt the plugin as a generic Reference Code Library.
- Removed bundled source archives, source files, organization logos, and hardcoded library entries.
- Added validated JSON and TXT imports using the `reference-code-library/v2` schema.
- Added an import preview with new and existing entry counts.
- Added update, skip, and duplicate conflict policies.
- Added complete-library, collection, and selected-entry JSON exports.
- Added downloadable blank and example pack templates.
- Added custom library branding, Media Library logo selection, colors, layout presets, sizing, and section controls.
- Renamed the WordPress editing experience to Code Library, Code Entries, Add Code, and Code Details.
- Added tags, a generic block, and generic shortcodes.
- Preserved legacy v1 content identifiers, shortcodes, and dynamic block rendering.
- Added a conflict guard when the original v1 plugin remains active.
- Kept imported and manually entered code inert and escaped on output.
