# Colibri: Align PDF/Excel file icons with document-link text

**Language:** CSS  
**Category:** WordPress / Colibri / Accessibility / File links  
**Problem:** File-type SVG icons injected as direct children of document links appear below the link text because Colibri wraps the link text in its own `.list-item-text-wrapper` block.  
**Solution:** Use a narrowly scoped flex layout for Colibri list links containing a direct `.afa-file-icon` child.  
**Status:** Tested by site editor on an Office of Administration, Division of Personnel WordPress page (October 8, 2026).  

## Code

```css
/* Colibri: keep PDF/Excel file icons beside document links.
 * Applies only to .ul-list-icon links with a direct .afa-file-icon child.
 * The file icon is injected after Colibri's .list-item-text-wrapper.
 */

/* Keep document text and file icons together */
.ul-list-icon li.list-item a.item-link:has(> .afa-file-icon) {
    display: flex !important;
    flex-wrap: wrap;
    align-items: baseline;
    column-gap: 5px;
}

/* Allow text wrapper to share space with the file icon */
.ul-list-icon li.list-item a.item-link:has(> .afa-file-icon)
.list-item-text-wrapper {
    display: inline-flex !important;
    flex: 0 1 auto;
    width: auto !important;
    max-width: 100%;
}

/* Keep document icons alongside the link text */
.ul-list-icon li.list-item a.item-link > .afa-file-icon {
    display: inline-block !important;
    flex: 0 0 auto;
    width: 16px;
    height: 16px;
    vertical-align: baseline;
    margin: 0;
}
```

## Implementation Notes

1. Add the CSS through **Appearance → Customize → Additional CSS**, or the site's approved custom stylesheet.
2. Remove earlier conflicting CSS attempted for these same icons.
3. Apply only where the markup includes `.ul-list-icon li.list-item a.item-link` with a direct child `svg.afa-file-icon`.
4. This targets document links that have the injected accessibility file icon, and does not intentionally restyle other Colibri lists.
5. Confirm PDF and spreadsheet link icons align correctly in desktop and mobile layouts; long labels can still wrap based on available width.
6. The more structural long-term approach is to inject the file icon inside the inline text span so it follows the last word. This is not required for the tested CSS fix.

## Relevant HTML structure

```html
<li class="list-item no-gutters">
  <a class="item-link no-gutters">
    <div class="list-item-text-wrapper">
      <div><!-- briefcase icon --></div>
      <span class="list-text d-block"><span>Document title</span></span>
    </div>
    <svg class="afa-file-icon" aria-hidden="true"><!-- PDF/XLS icon --></svg>
  </a>
</li>
```

**Note:** File type detection and accessible link labels are handled by the site's existing script. This CSS only adjusts visual positioning.
