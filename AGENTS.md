# Tonyapps_EmailDesigner — notes for AI agents and developers

Read this before changing the module. Workspace-wide rules are in ../AGENTS.md.

A drag-and-drop email designer in the Magento admin. Store staff build or restyle transactional emails visually
(Tonyapps modules and core Magento: orders, invoices, shipments, accounts) instead of editing template HTML.
Package `tonyapps/module-email-designer` 0.1.0.

## Status

- **2026-11-11**: scope approved. Phase 1 built (unit, integration and browser checks green). Manual QA in the
  admin passed (owner).
- **Phase 2 (rich blocks): in progress.** Built 2026-11-11: block registry and core sales blocks (see
  Architecture). Next: blocks and starter designs for the Tonyapps emails, then the *Design visually* button and
  image upload.
- **Phase 3 (brand theme, real-data preview): not started.**
- Note: the old README status line still said "Phases 2–3 not started" after phase 2 work began; the phase list
  below is the current truth.

### Phases

1. **Editor and storage — built 2026-11-11:**
   - **Marketing › Communications › Email Designer:** a grid and a **New design** form.
   - **GrapesJS + MJML editor:** starter layout; Magento blocks *Store logo*, *Store name* and *Store contact*; an
     **Insert value** menu in the text toolbar with the email's own variables and the store's.
   - **Save:** writes to `email_template`. **Subject:** edited in the designer.
   - **Preview:** with generated sample data, desktop and mobile width. **Send test:** to an address.
   - **Versions:** the last 10, with **Restore**.
   - Not in phase 1, moved to phase 2: the **Design visually** button on core's *Email Templates* page; image
     upload through the media gallery (images are added by URL for now).
2. **Rich blocks — in progress:**
   - Built 2026-11-11: the block registry (`Model/BlockPool`, filled from `di.xml`) and blocks for core's order,
     invoice, shipment and credit memo emails (customer and guest): items table, tracking numbers, billing and
     shipping address, payment and shipping method, customer note, comment. They appear in the *This email*
     category of the block panel. The preview shows `{{layout}}` blocks as labelled boxes.
   - Next: blocks and starter designs for the Tonyapps emails, then the *Design visually* button and image upload.
   - Original scope: core sales emails (order, invoice, shipment, credit memo, plus account emails), then the 13
     Tonyapps emails. A **starter design** is provided for each Tonyapps email, built from today's redesigned
     templates. Phase 2 also adds the *Design visually* button and image upload.
3. **Brand theme:** per scope, applied to new designs, Re-apply theme. Preview with real data (pick an order, cart
   or quote).

## Architecture

### Core idea: the designer outputs ordinary email templates

A design is saved as a normal row in core `email_template` (Marketing > Email Templates). The HTML is generated
from the visual design, and the design itself (the GrapesJS project) is kept next to it. Consequences:

- No module that sends email changes. A store uses a design the same way it uses any custom template: it picks
  the template in that email's setting (e.g. *Sales Emails > Order > New Order Confirmation Template*,
  *Abandoned Cart > Email Template*).
- Template variables, `{{trans}}`, `{{layout}}` and `{{depend}}`/`{{if}}`/`{{for}}` keep working, because the
  output is processed by core's template filter like any other template.
- Turning the module off breaks nothing. Saved designs stay valid templates; they just can't be edited visually.

### Classes and files

| Piece | What it does |
|---|---|
| `Controller/Adminhtml/Design/{Index,Edit,Save,Preview,SendTest,Restore}` | Grid, editor page (new and existing), AJAX save / preview / test send / restore. All use ACL `Tonyapps_EmailDesigner::designs`. |
| `Block/Adminhtml/Designs` + `templates/designs.phtml` | Grid: Design, Email, Used in settings, Last saved. New design lists `Magento\Email\Model\Template\Config::getAvailableTemplates()` (any template from `email_templates.xml`, core or Tonyapps). |
| `Block/Adminhtml/Editor` + `templates/editor.phtml` | Editor page config (project, starter, variables, blocks, logo URL). |
| `Model/DesignRepository` | Tables `tonyapps_email_design` / `_version`; `KEEP_VERSIONS = 10`, prunes older versions; `restore()`. |
| `Model/Renderer` | Preview and test send through core's template filter. |
| `Model/StarterLayout` | Starter MJML (header with logo, content, button, footer). |
| `Model/TemplateVariables` | The email's variables from the template's `@vars` (the list core shows in *Insert Variable*) plus store variables; also the sample data. |
| `Model/BlockPool` | Rich-block registry, `blocks` argument filled from `etc/di.xml`. |
| `view/adminhtml/web/js/designer.js` | GrapesJS init (`telemetry: false`, `storageManager: false`, `grapesjs-mjml` plugin), Magento blocks, the `ta-block` component type, Insert value toolbar action, save/preview/test/versions UI. |
| `view/adminhtml/web/js/directives.js` | Directive protection before/after MJML compile; store-logo swap. |
| `view/adminhtml/web/{js/lib,css}` | Bundled libraries (see below). |

### Compiling and protecting directives

- **Compiling:** the browser compiles MJML to HTML with the `mjml-code-to-html` command of `grapesjs-mjml`
  (which includes `mjml-browser`). No Node.js on the server: the compiled HTML goes to `email_template`, the MJML
  source to the design.
- **Protecting directives:** MJML breaks attribute values that contain quotes, e.g. `href="{{store url="..."}}"`.
  Before compiling, `directives.js` swaps every `{{...}}` directive for a plain token and decodes it. After
  compiling, it restores the directives.
- **Logo:** the *Store logo* block shows the default email logo in the editor. On save it becomes
  `{{var logo_url}}`, so each store sends its own logo.

### Editor

- **Telemetry:** off (`telemetry: false`). The admin never contacts grapesjs.com.
- **Bundled libraries:** pinned, unmodified copies in `view/adminhtml/web/{js/lib,css}` (no CDN). Licences are in
  `THIRD-PARTY-NOTICES.md` (grapesjs 0.23.8 BSD-3-Clause; grapesjs-mjml 1.0.8 incl. mjml-browser, BSD-3/MIT).
- MJML adds about 1 MB of JS in the admin, loaded only on the designer page.

### Preview and test send

- **Sample data:** both use sample data built from the email's `@vars`: `var order.increment_id` →
  `[Order Id]`, and a getter such as `order.getCustomerName()` fills `customer_name`.
- **Variables core fills itself:** `store`, `logo_url` and so on keep their real values.
- **`{{layout}}` blocks:** the preview shows a labelled box instead (they load the real order by ID). A test send
  renders them with the sample ID, so the items table comes out empty. Real data comes in phase 3.

### Magento blocks (phase 2)

- **In the canvas:** a block (component type `ta-block`, extends `mj-raw`) is one box with its name; it cannot be
  edited or styled, so a design cannot break an items table or a loop. It can only be dropped into a column.
- **In the email:** the block's directive in a table row of the column (`<mj-raw><tr><td>…</td></tr></mj-raw>`),
  styled like the starter's text.
- **Registering blocks for a module's emails:** add an item to the `blocks` argument of
  `Tonyapps\EmailDesigner\Model\BlockPool` in the module's `etc/di.xml` (`label`, `templates`: the email template
  IDs, `directive`). See this module's `etc/di.xml` for the core sales blocks. The entry is ignored when this module
  is not installed.

## Data model

Actual schema (`etc/db_schema.xml`):

- `tonyapps_email_design`: `design_id`, `template_id` (unique, FK `email_template.template_id`, cascade),
  `base_template_code` (the email the design is for), `project_data` (GrapesJS project JSON), `mjml`,
  `created_at`, `updated_at`.
- `tonyapps_email_design_version`: `version_id`, `design_id` (FK, cascade), `project_data`, `mjml`, `html`
  (compiled), `subject`, `admin_user_id` (FK `admin_user`, set null), `created_at`.

Planned in the scope but not in the schema yet:

- On `tonyapps_email_design`: design name (the name currently lives on the `email_template` row), theme snapshot,
  created and updated by. The scope called the base-template column `orig_template_code`; it is
  `base_template_code`.
- `tonyapps_email_theme` (phase 3): id, scope (default/website/store), scope id, logo, colours, font, button
  style, footer HTML.

Rich blocks are not stored: they are registered in `di.xml` (`Tonyapps\EmailDesigner\Model\BlockPool`), keyed by
template code. The scope also allows a group key like "all sales emails". Each block has a label, an icon, a
placeholder preview and the directive it inserts. Other Tonyapps modules add theirs in their own `etc/di.xml`,
which only does anything when EmailDesigner is installed.

## Configuration and ACL

- Menu: **Marketing › Communications › Email Designer** (`Tonyapps_EmailDesigner::designs`, parent
  `Magento_Backend::marketing_communications`, route `tonyapps_emaildesigner/design/index`).
- ACL: `Tonyapps_EmailDesigner::designs`.
- No `system.xml`. Designs are used through each email's existing template setting.

## Decisions and constraints

Owner decisions 2026-11-11:

- A shared module, not part of AbandonedCart (13 Tonyapps templates in 7 modules plus core emails would all use
  it). Replaces WORKPLAN item 8, "AbandonedCart email designer".
- Editor = **GrapesJS** (BSD-3, self-hosted, no API key) with **MJML** output.
- Scope = **Tonyapps and core Magento emails**.

Scope table (approved 2026-11-11; status per row in brackets):

| Feature | Decision |
|---|---|
| Where | **Marketing > Email Designer**: a grid of designs (name, based-on template, used in which settings, modified). **New design** asks for the email it is for (any template from `email_templates.xml`, core or Tonyapps). A **Design visually** button on the core *Email Templates* edit page for templates created by the designer [phase 2, not built]. ACL `Tonyapps_EmailDesigner::designs`. |
| Editor | GrapesJS: canvas, blocks to drag in (sections, columns, text, image, button, divider, spacer, social links), style panel (colours, fonts, spacing, borders), undo/redo, desktop and mobile view, code view. The scope said "newsletter preset"; the build uses the `grapesjs-mjml` plugin. Assets bundled in the module (no CDN), pinned version. |
| Magento blocks | Each inserts a directive and shows a readable placeholder in the canvas (a "Product list" box rather than raw `{{layout ...}}`); the directive is not editable as text. **Common:** store logo, store name, store address and phone [built]; *View in browser* is out of scope. **Per email:** a **Variable** block listing that email's own variables from `@vars` [built as the Insert value toolbar menu], and rich blocks registered by modules: order items table, totals, billing and shipping address, payment and shipping method (core sales emails) [built; no separate totals block, core's items layout handles render the totals]; cart items with images, subtotal, coupon, restore button, promotion block (AbandonedCart); review link (ReviewReminder); product and back-in-stock link (StockNotification); quote lines and totals (B2bQuotes); cart table (B2bOrderApproval); and so on [not built]. |
| Starting point | A new design starts from a **starter layout** (header with logo, content, button, footer) with the email's essential blocks in place, e.g. order items and totals for an order email. No import from existing HTML templates: arbitrary template HTML does not convert reliably into editor blocks. |
| Brand theme | One **brand theme** per store configuration: logo, primary and secondary colour, font, button style, footer text. New designs use it, and a design can **Re-apply theme**. Changing the theme does not silently restyle saved designs. [phase 3] |
| Subject | Edited in the designer (with variables), saved to the template's subject. |
| Preview | Live preview with **sample data** per email type: a real recent order, cart, quote etc. picked by the admin [phase 3], or a generated sample when there is none [built]. Rendered through core's template filter, so it shows exactly what will be sent. Desktop and mobile widths switchable. |
| Test send | **Send test** to an address (default: the admin's own, `adminEmail` in `Block/Adminhtml/Editor`), with the chosen sample data. Unsaved changes are saved first. [built] |
| Email-client safety | **MJML** (owner decision 2026-11-11). The editor works on MJML through `grapesjs-mjml`; on save the browser compiles it with `mjml-browser` into responsive, table-based HTML with inlined CSS (Outlook, Gmail). Server needs no Node.js. Directives sit inside `mj-text` (variables) or `mj-raw` (rich blocks), which MJML passes through unchanged. |
| Store views | Same as core: a template is global and the per-store choice happens in configuration. One design can be assigned to several store views, and a store view can use its own design. `{{trans}}` text blocks stay translatable via core translations; free text typed in the editor is per design. |
| Versions | Each save keeps the previous version (last 10). **Restore** puts a previous version back. |
| Safety | Only admins with the ACL can design. Generated HTML passes core's template filter in strict mode: only `{{var obj.field}}` and allow-listed directives, no method calls, the same rules core applies to custom templates. Image uploads go through the core media gallery (WYSIWYG image uploader) into `pub/media/email/` [phase 2, not built; images by URL for now]. |

Out of scope:

- Marketing campaigns and newsletters (core newsletter queue): transactional emails only.
- Drag-and-drop editing of storefront pages or CMS blocks.
- Converting existing hand-written HTML templates into visual designs.
- A/B tests, open and click tracking.

## Known limitations and open points

- Images by URL only until media-gallery upload (phase 2).
- No rich blocks or starter designs for Tonyapps emails yet.
- `{{layout}}` blocks: labelled box in preview; empty items table in a test send (sample ID). Real data: phase 3.
- No brand theme (phase 3).

Open points recorded before code (scope stage):

- **MJML and directives:** check that `mjml-browser` leaves `{{...}}` directives intact in attributes such as
  `mj-button href` and `mj-image src`, not only in text; if not, protect them with placeholders before compiling
  and restore them afterwards. Cost: about 1 MB more JS in the admin, loaded only on the designer page.
  → Resolved in phase 1: it does break quoted attribute values, so `directives.js` protects them.
- **Loops:** Magento's `{{for}}` loops (e.g. the AbandonedCart items) are placed only as whole rich blocks, not
  built in the editor, so a design cannot break a loop's markup. → Implemented by the locked `ta-block` type.
- **Licences:** GrapesJS is BSD-3 and `grapesjs-mjml` and MJML are MIT. Their notices must ship with the module
  and appear in the Composer package metadata. → Notices ship in `THIRD-PARTY-NOTICES.md`; `composer.json` still
  only says `"license": "proprietary"` (Composer metadata part open).

## Tests

- Unit: `vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Tonyapps/EmailDesigner/Test/Unit`
  (BlockPool, DesignRepository, Renderer, StarterLayout, TemplateVariables).
- Integration (DDEV setup in the workspace WORKPLAN, item 3):
  `…/phpunit -c phpunit.xml ../../../app/code/Tonyapps/EmailDesigner/Test/Integration`. Covers:
  - saving to `email_template`;
  - keeping 10 versions, and restore;
  - the cascade when the template is deleted in core;
  - which settings use a design;
  - preview and test send through core;
  - the admin pages, AJAX actions and ACL;
  - end to end, a design picked in ReviewReminder's and AbandonedCart's template setting is what their cron sends
    (skipped when the module is not installed).
- JS: `node Test/Js/directives.test.js [path/to/node_modules/mjml-browser]`. With the optional path it also
  compiles through real MJML.

## Change history

- **2026-11-11** — Phase 2 started: `Model/BlockPool`, `etc/di.xml` core sales blocks, `ta-block` component,
  preview shows `{{layout}}` blocks as labelled boxes (uncommitted at the time of the README split).
- **2026-11-11** — Manual QA of phase 1 passed (owner).
- **2026-11-11** — Phase 1 built; scope approved; owner decisions (shared module, GrapesJS + MJML, Tonyapps and
  core emails).
