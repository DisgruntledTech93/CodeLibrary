=== Reference Code Library ===
Contributors: disgruntledtech93
Tags: code library, documentation, snippets, knowledge base, developer tools
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build, brand, import, export, and publish a searchable reference library of documented code examples without executing stored code.

== Description ==

Reference Code Library turns WordPress into a portable, theme-independent code knowledge base.

Code entries can include:

* A plain-language summary
* When to use the code
* Implementation and testing notes
* Technology and code language
* Inert code text with a copy button
* A reference URL
* Collections, statuses, and tags

Version 2 installs without bundled organization-specific code or source archives. Administrators can import and export validated JSON library packs, customize the library title and logo, choose colors and layout presets, and publish the library using a block or shortcode.

Stored code is never evaluated, injected, or executed by the plugin.

== Installation ==

1. Upload and activate the plugin.
2. Add entries through Code Library > Add Code, or import a JSON pack through Code Library > Import / Export.
3. Add the Code Library block to a page, or use `[code_library]`.
4. Customize branding under Code Library > Appearance.

== Frequently Asked Questions ==

= Does the plugin execute imported or stored code? =

No. Code is escaped and displayed as inert reference text.

= Can I move a library to another site? =

Yes. Export the entire library, one collection, or selected entries as a JSON pack and import it on another WordPress installation.

= Can I rebrand the front end? =

Yes. The title, introduction, logo, colors, layout preset, content width, and visible sections can be customized without editing plugin files.

= Will version 2 preserve Missouri Accessibility Library v1 entries? =

Yes. Version 2 retains the original internal content identifiers and supports the legacy shortcodes and block rendering.

== Changelog ==

= 2.0.0 =
* Rebuilt the plugin as a generic Reference Code Library.
* Removed bundled source archives, source files, logos, and hardcoded content.
* Added validated JSON and TXT import with preview.
* Added update, skip, and duplicate conflict handling.
* Added full-library, collection, and selected-entry exports.
* Added downloadable blank and example pack templates.
* Added custom logo selection through the WordPress Media Library.
* Added colors, layout presets, sizing, and section visibility controls.
* Renamed the editor screens to Code Library, Code Entries, and Add Code.
* Added code tags and a generic Code Library block.
* Added generic shortcodes while preserving v1 shortcodes.
* Preserved existing v1 content through legacy internal identifiers.
