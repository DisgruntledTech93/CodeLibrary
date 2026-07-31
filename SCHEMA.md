# Library Pack Schema

Reference Code Library 2.1 accepts `.json`, `.txt`, and portable `.zip` files whose contents are valid JSON and whose root `schema` value is:

```json
"reference-code-library/v2"
```

## Root fields

| Field | Required | Purpose |
|---|---:|---|
| `schema` | Yes | Must be `reference-code-library/v2`. |
| `pack` | Yes | Identifies and describes the portable library pack. |
| `branding` | No | Optional title, text, colors, layout, and display settings. |
| `collections` | Yes | Collection definitions referenced by entries. |
| `entries` | Yes | One or more code entries, optionally with working-example screenshots. |

## Pack object

```json
{
  "id": "organization-library",
  "name": "Organization Code Library",
  "version": "1.0.0",
  "description": "Portable code and implementation guidance."
}
```

`name` is required. `id` is converted to a WordPress-safe slug when imported.

## Collection object

```json
{
  "id": "css-patterns",
  "name": "CSS Patterns",
  "description": "Reusable presentation-layer solutions.",
  "icon": "CSS",
  "class": "css",
  "order": 10
}
```

`id` and `name` are required. Every entry collection value must match a collection `id` in the same pack.

## Entry object

```json
{
  "id": "visible-focus-example",
  "title": "Visible Focus Example",
  "collection": "css-patterns",
  "status": "Working reference",
  "technology": "CSS",
  "code_language": "css",
  "summary": "Adds a visible keyboard focus indicator.",
  "use_when": "Use when a theme removes focus styling.",
  "implementation_notes": "Test contrast against every surrounding background.",
  "code": ":focus-visible { outline: 3px solid #ffcc05; }",
  "reference_url": "",
  "tags": ["focus", "keyboard"],
  "examples": [
    {
      "file": "images/visible-focus-result.png",
      "type": "result",
      "alt": "Keyboard focus displayed as a thick yellow outline around a button.",
      "caption": "Result after the focus CSS is applied.",
      "order": 10
    }
  ],
  "order": 10
}
```

`id` and `title` are required. IDs must be unique inside a pack. Matching IDs are used to preview and resolve updates.

## Branding object

Supported optional values include:

- `title`, `eyebrow`, and `intro`
- `primary_color`, `secondary_color`, `accent_color`, and `focus_color`
- `background_color`, `card_color`, `text_color`, `muted_color`, and `border_color`
- `layout_preset`: `classic`, `minimal`, or `documentation`
- `show_hero`, `show_stats`, `show_start_here`, and `show_collection_descriptions`

Branding is only applied when the administrator checks the branding option during import. Logos are intentionally excluded from packs and remain under local WordPress Media Library control.

## Working-example screenshots

Each entry may include an optional `examples` array. Supported fields are:

| Field | Required | Purpose |
|---|---:|---|
| `file` or `url` | Yes | Use `file` for an image inside a portable ZIP pack, or `url` for an HTTP/HTTPS image that WordPress should sideload. |
| `type` | No | `before`, `after`, `result`, `configuration`, `inspector`, `mobile`, `test`, or `other`. |
| `alt` | No | Alternative text describing the demonstrated result. An empty value creates `alt=""`. |
| `caption` | No | Visible context displayed beneath the screenshot. |
| `order` | No | Numeric display order. |

A code entry supports up to 20 screenshots. Existing JSON/TXT packs without an `examples` field remain valid and do not replace an existing entry's screenshot gallery during an update.

## Portable ZIP packs

A portable pack uses this structure:

```text
library-pack.zip
├── library.json
└── images/
    ├── visible-focus-result.png
    └── another-working-example.webp
```

`library.json` uses the same `reference-code-library/v2` schema. Local screenshot paths must begin with `images/`. ZIP packs may contain only the manifest and supported JPEG, PNG, GIF, or WebP images. The importer validates paths, real image MIME types, file counts, and expanded size before previewing the pack.

