# Library Pack Schema

Reference Code Library 2.0 accepts `.json` and `.txt` files whose contents are valid JSON and whose root `schema` value is:

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
| `entries` | Yes | One or more code entries. |

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
