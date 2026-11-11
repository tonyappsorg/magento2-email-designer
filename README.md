# Tonyapps_EmailDesigner

A drag-and-drop email designer in the Magento admin. Store staff build or restyle transactional emails visually
(Tonyapps modules and core Magento: orders, invoices, shipments, accounts) instead of editing template HTML.

**Status: scope approved 2026-11-11. Phase 1 built 2026-11-11 (unit, integration and browser checks green); manual QA in the admin pending. Phases 2–3 not started.**

Owner decisions taken 2026-11-11: a shared module, not part of AbandonedCart (13 Tonyapps templates in 7 modules
plus core emails would all use it); editor = **GrapesJS** (BSD-3, self-hosted, no API key) with **MJML** output; scope = **Tonyapps and
core Magento emails**. Replaces WORKPLAN item 8, "AbandonedCart email designer".

## Core idea: the designer outputs ordinary email templates

A design is saved as a normal row in core `email_template` (Marketing > Email Templates). The HTML is generated
from the visual design, and the design itself (the GrapesJS project) is kept next to it. Because of that:

- No module that sends email changes. A store uses a design the same way it uses any custom template today:
  it picks the template in that email's setting (e.g. *Sales Emails > Order > New Order Confirmation Template*,
  *Abandoned Cart > Email Template*).
- Template variables, `{{trans}}`, `{{layout}}` and `{{depend}}`/`{{if}}`/`{{for}}` keep working, because the
  output is processed by core's template filter like any other template.
- Turning the module off breaks nothing. Already saved designs stay valid templates. You can only no longer
  edit them visually.

## Scope and decisions (proposal)

| Feature | Proposal |
|---|---|
| Where | **Marketing > Email Designer**: a grid of designs (name, based-on template, used in which settings, modified). **New design** asks for the email it is for (any template from `email_templates.xml`, core or Tonyapps). There is also a **Design visually** button on the core *Email Templates* edit page for templates created by the designer. ACL `Tonyapps_EmailDesigner::designs`. |
| Editor | GrapesJS with its newsletter preset: a canvas, blocks to drag in (sections, columns, text, image, button, divider, spacer, social links), style panel (colours, fonts, spacing, borders), undo/redo, desktop and mobile view, and a code view for advanced users. The assets are bundled in the module (no CDN), with a pinned version. |
| Magento blocks | Blocks specific to Magento, each one inserting a directive and showing a readable placeholder in the canvas (a "Product list" box rather than raw `{{layout ...}}`). The directive inside is not editable as text. **Common:** store logo, store name, store address and phone, *View in browser* is out of scope. **Per email:** a **Variable** block listing that email's own variables (read from the template's `@vars`, the same list core shows in *Insert Variable*), and rich blocks registered by modules: order items table, totals, billing and shipping address, payment and shipping method (core sales emails); cart items with images, subtotal, coupon, restore button, promotion block (AbandonedCart); review link (ReviewReminder); product and back-in-stock link (StockNotification); quote lines and totals (B2bQuotes); cart table (B2bOrderApproval); and so on. |
| Starting point | A new design starts from a **starter layout** (header with logo, content, button, footer) with the email's essential blocks already in place. Example: the order items and totals blocks for an order email. A design cannot be imported from an existing HTML template: arbitrary template HTML does not convert reliably into editor blocks. |
| Brand theme | One **brand theme** per store configuration: logo, primary and secondary colour, font, button style, footer text. New designs use it, and a design can **Re-apply theme**. Changing the theme does not silently restyle saved designs. |
| Subject | Edited in the designer (with variables), saved to the template's subject. |
| Preview | A live preview with **sample data** per email type: a real recent order, cart, quote etc. picked by the admin, or a generated sample when there is none. It is rendered through core's template filter, so it shows exactly what will be sent. The desktop and mobile widths are switchable. |
| Test send | **Send test** to an address (default: the admin's own), with the chosen sample data. |
| Email-client safety | **MJML** (owner decision 2026-11-11). The editor works on MJML through the `grapesjs-mjml` plugin, and on save the browser compiles it with `mjml-browser` into responsive, table-based HTML with inlined CSS, which works in Outlook and Gmail. The server needs no Node.js: the compiled HTML goes to `email_template` and the MJML source to the design. Magento directives sit inside `mj-text` (variables) or `mj-raw` (rich blocks), which MJML passes through unchanged. |
| Store views | Same as core: a template is global and the per-store choice happens in configuration. One design can be assigned to several store views, and a store view can use its own design. Translations: `{{trans}}` text blocks stay translatable via core translations; free text typed in the editor is per design. |
| Versions | Each save keeps the previous version (last 10). **Restore** puts a previous version back. |
| Safety | Only admins with the ACL can design. Generated HTML passes core's template filter in strict mode: only `{{var obj.field}}` and allow-listed directives, no method calls, the same rules core applies to custom templates. Image uploads go through the core media gallery (WYSIWYG image uploader) into `pub/media/email/`. |

## Data model

- `tonyapps_email_design`: id, template id (FK to `email_template`, cascade), design name, base template code (`orig_template_code`), GrapesJS project JSON (MJML components), theme snapshot, created and updated by, created and updated at.
- `tonyapps_email_design_version`: id, design id (FK, cascade), project JSON, MJML source, compiled HTML, subject, created by, created at.
- `tonyapps_email_theme`: id, scope (default/website/store), scope id, logo, colours, font, button style, footer HTML.
- Rich blocks are registered in `di.xml` (`Tonyapps\EmailDesigner\Model\BlockPool`), keyed by template code or a group like "all sales emails". Each block has a label, an icon, a placeholder preview and the directive it inserts. The other Tonyapps modules add their blocks with their own `di.xml`, in an optional `etc/di.xml` section that only does anything when EmailDesigner is installed.

## Phases

1. **Editor and storage — built 2026-11-11:**
   - **Marketing › Communications › Email Designer:** a grid and a **New design** form.
   - **GrapesJS + MJML editor:** starter layout; Magento blocks *Store logo*, *Store name* and *Store contact*; an **Insert value** menu in the text toolbar with the email's own variables and the store's.
   - **Save:** writes to `email_template`.
   - **Subject:** edited in the designer.
   - **Preview:** with generated sample data, desktop and mobile width.
   - **Send test:** to an address.
   - **Versions:** the last 10, with **Restore**.
   - **Not in phase 1:**
     - the **Design visually** button on core's *Email Templates* page;
     - image upload through the media gallery (images are added by URL for now).
   - **Next:** both move to phase 2.
2. **Rich blocks:** core sales emails (order, invoice, shipment, credit memo, plus account emails), then the 13 Tonyapps emails. A **starter design** is provided for each Tonyapps email, built from today's redesigned templates. Phase 2 also adds the *Design visually* button and image upload.
3. **Brand theme:** per scope, applied to new designs, Re-apply theme. Preview with real data (pick an order, cart or quote).

## How it works (phase 1)

**Compiling and protecting directives**

- **Compiling:** the browser compiles MJML to HTML with the `mjml-code-to-html` command of `grapesjs-mjml`. No Node.js is needed on the server.
- **Protecting directives:** MJML breaks attribute values that contain quotes, e.g. `href="{{store url="..."}}"`. Before compiling, `view/adminhtml/web/js/directives.js` swaps every `{{...}}` directive for a plain token and decodes it. After compiling, it restores the directives.
- **Logo:** the *Store logo* block shows the default email logo in the editor. On save it becomes `{{var logo_url}}`, so each store sends its own logo.

**Editor**

- **Telemetry:** off (`telemetry: false`). The admin never contacts grapesjs.com.
- **Bundled libraries:** pinned, unmodified copies in `view/adminhtml/web/{js/lib,css}`. Licences are in `THIRD-PARTY-NOTICES.md`.

**Preview and test send**

- **Sample data:** both use sample data built from the email's `@vars`: `var order.increment_id` → `[Order Id]`, and a getter such as `order.getCustomerName()` fills `customer_name`.
- **Variables core fills itself:** `store`, `logo_url` and so on keep their real values.
- **Not rendered yet:** `{{layout}}` blocks such as the order items table need real data, which comes in phase 2–3.

## Tests

- Unit: `vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Tonyapps/EmailDesigner/Test/Unit`
- Integration (DDEV setup in the workspace WORKPLAN, item 3): `…/phpunit -c phpunit.xml ../../../app/code/Tonyapps/EmailDesigner/Test/Integration`. These tests cover:
  - saving to `email_template`;
  - keeping 10 versions, and restore;
  - the cascade when the template is deleted in core;
  - which settings use a design;
  - preview and test send through core;
  - the admin pages, AJAX actions and ACL;
  - end to end, a design picked in ReviewReminder's and AbandonedCart's template setting is what their cron sends
    (skipped when the module is not installed).
- JS: `node Test/Js/directives.test.js [path/to/node_modules/mjml-browser]`. With the optional path it also compiles through real MJML.

## Out of scope

- Marketing campaigns and newsletters (core newsletter queue): transactional emails only.
- Drag-and-drop editing of storefront pages or CMS blocks.
- Converting existing hand-written HTML templates into visual designs.
- A/B tests, open and click tracking.

## Open points to settle before code

- **MJML and directives:** check at build time that `mjml-browser` leaves `{{...}}` directives intact in attributes such as `mj-button href` and `mj-image src`, not only in text; if not, protect them with placeholders before compiling and restore them afterwards. Cost: about 1 MB more JS in the admin, loaded only on the designer page.
- **Loops:** Magento's `{{for}}` loops (e.g. the AbandonedCart items) are placed only as whole rich blocks, not built in the editor, so a design cannot break a loop's markup.
- **Licences:** GrapesJS is BSD-3 and `grapesjs-mjml` and MJML are MIT. Their notices must ship with the module and appear in the Composer package metadata.
