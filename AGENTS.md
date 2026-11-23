# Tonyapps_EmailDesigner — notes for AI agents and developers

Read this before changing the module. Workspace-wide rules are in ../AGENTS.md.

A drag-and-drop email designer in the Magento admin. Store staff build or restyle transactional emails visually
(Tonyapps modules and core Magento: orders, invoices, shipments, accounts) instead of editing template HTML.
Package `tonyapps/module-email-designer` 1.0.0.

## Status

- **2026-11-11**: scope approved. Phase 1 built (unit, integration and browser checks green). Manual QA in the
  admin passed (owner).
- **Phase 2 (rich blocks): built.** 2026-11-11: block registry and core sales blocks. 2026-11-13: starter designs
  and blocks for all 17 Tonyapps emails (see Architecture). 2026-11-14: *Design Visually* button and image upload
  through core's media browser. Admin QA of phase 2 by the owner pending.
- **Phase 3 (brand theme, real-data preview): built 2026-11-15** (scope detail below, owner decisions 2026-11-15).
  Owner's admin QA pending.
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
   - Built 2026-11-13: starter designs and blocks for the Tonyapps emails, registered by each module (17 emails
     in 7 modules; the scope's "13" missed B2b's 3 company emails and NotifyOnNewBrowserLogin). Emails without a
     registered starter (core sales emails) start from the starter layout with all their blocks in the card. The
     preview fills each `{{for}}` loop with one sample row.
   - Built 2026-11-14: the *Design Visually* button on core's template edit page and image upload through core's
     media browser into `pub/media/email/` (see Architecture).
   - Original scope: core sales emails (order, invoice, shipment, credit memo, plus account emails), then the 13
     Tonyapps emails. A **starter design** is provided for each Tonyapps email, built from today's redesigned
     templates. Phase 2 also adds the *Design visually* button and image upload.
3. **Brand theme and real-data preview** (owner decisions 2026-11-15: one theme for the whole install; real-data
   preview for core sales emails and all 17 Tonyapps emails):
   - **3a Brand theme.** *Stores › Configuration › Advanced › Email Designer › Brand theme*, default scope only:
     primary colour (buttons, links, accents; default `#1979c3`), background colour (`#f5f5f5`), font (email-safe
     list), button corner radius (`3px`), footer text (variables allowed; default store name · store email). The
     logo stays core's per-store email logo. A new design starts with the theme; **Apply brand theme** in the
     editor re-applies it to an existing design (after a confirm). Saved designs never change by themselves: the
     theme is applied in the editor and stored in the design, which remembers the theme it last got, so a
     re-apply swaps exactly those values (colours in attributes, text and blocks; font; button radius; the
     footer marked `ta-footer`). Starter designs and module blocks are written in the default colours, so they
     follow the theme. Semantic colours (green "approved", red "declined"/security) are left alone.
   - **3b Real-data preview.** The preview gets a "Sample data / real record" picker listing recent records for
     the email (orders for sales emails, carts for AbandonedCart, shipped orders for ReviewReminder, approval
     requests, quotes, companies, alerts…). The preview and test send then use the variables the module itself
     sends with, so they show the real email. Each module registers a preview provider for its emails in
     `di.xml` (`Tonyapps\EmailDesigner\Model\PreviewPool`, like blocks; duck-typed, no dependency on this
     module): `getRecords(string $templateId, int $limit): array<string, string>` and
     `getVariables(string $templateId, string $recordId): array`. Modules build these variables with the same
     code their sender uses (refactored out of the sender where needed), never a copy. This module provides the
     provider for core's order, invoice, shipment and credit memo emails (customer and guest).
   - Safety: only admins with the designer ACL; real customer data is shown only in the admin preview and in a
     test sent to the address the admin types. Nothing is stored.

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
| `Model/StarterLayout` | Starter MJML: header with logo, a card holding the email's registered starter content (else a heading, a text, the email's blocks and a button), footer. |
| `Model/TemplateVariables` | The email's variables from the template's `@vars` (the list core shows in *Insert Variable*) plus store variables; also the sample data. |
| `Model/BlockPool` | Rich-block and starter registry: `blocks` and `starters` arguments filled from `etc/di.xml` of this and other modules. |
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
- **CSS lines in sent email:** `directives.js` prefixes a space to CSS selectors that start with `.` at the start
  of a line inside a `<style>` tag. SMTP dot-stuffing doubled the first `.email-items` selector in a demo order
  email, leaving the order table narrow in Mailpit. The space preserves the selector and its intended width.
- **Logo:** the *Store logo* block shows the default email logo in the editor. On save it becomes
  `{{var logo_url}}`, so each store sends its own logo.

### Editor

- **Telemetry:** off (`telemetry: false`). The admin never contacts grapesjs.com.
- **Bundled libraries:** pinned, unmodified copies in `view/adminhtml/web/{js/lib,css}` (no CDN). Licences are in
  `THIRD-PARTY-NOTICES.md` (grapesjs 0.23.8 BSD-3-Clause; grapesjs-mjml 1.0.8 incl. mjml-browser, BSD-3/MIT).
- MJML adds about 1 MB of JS in the admin, loaded only on the designer page.

### Preview and test send

- **Sample data:** both use sample data built from the email's `@vars`: `var order.increment_id` →
  `[Order Id]`, and a getter such as `order.getCustomerName()` fills `customer_name`. Each `{{for item in items}}`
  in the design gets one sample row whose fields are named after what the loop reads (`{{var item.name}}` →
  `[name]`); for a test send the loops are read from the saved template.
- **Variables core fills itself:** `store`, `logo_url` and so on keep their real values.
- **`{{layout}}` blocks:** the sample preview shows a labelled box instead (they load a real order or another
  record by ID). Since a sample test email would contain an empty table, the editor asks for a real record before
  sending a design with a `{{layout}}` block. The test prompt names the selected record.

### Brand theme (phase 3a)

- Config `tonyapps_emaildesigner/theme/{primary,background,font,radius,footer}` (`etc/adminhtml/system.xml`, tab
  Advanced, default scope only, ACL `Tonyapps_EmailDesigner::config`; colours use the browser's colour input,
  `Block/Adminhtml/System/Config/Color`; fonts from `Model/Config/Source/Font`). `Model/Theme::get()` validates
  every value and falls back to `Theme::DEFAULTS`, the values starters and module blocks are written in.
- `view/adminhtml/web/js/theme.js` (`apply(component, from, to)`, tested by `Test/Js/theme.test.js`): swaps the
  primary colour (any case) and font stack in attributes, inline styles, text and block directives; the body
  background and button radius only where they equal the old theme's; replaces the text of the `ta-footer`
  footer. Colours that aren't the old primary are left alone.
- `designer.js`: a new design gets the theme over the default; **Apply brand theme** (confirm) re-applies it;
  blocks dropped from the panel are themed on `block:drag:stop`; the theme the design last got is saved in the
  project JSON as `taTheme` and is the `from` of the next apply. `ta-block` cells use the theme font.

### Real-data preview (phase 3b)

- `Model/PreviewPool` (`providers` argument from `di.xml`, contract in its docblock: `getRecords`,
  `getVariables`, optional `getStoreId`). The editor lists `getRecords()` in a *Sample data / record* select;
  `Renderer::render()`/`sendTest()` take the record ID, use the provider's variables instead of sample data, the
  record's store (`store` variable or `getStoreId()`), and leave `{{layout}}` blocks to render for real.
- Core sales emails: `Model/Preview/SalesEmails` lists recent orders, invoices, shipments, credit memos (guest or
  customer to match the template). Its variables come from core's own senders: `Model/Preview/Sales/*Sender`
  subclass each core sender with `CapturesVariables`, whose `checkAndSend()` throws `CapturedVariables` with the
  container's template vars right after they are built, so nothing is sent or saved even with a stale config;
  `send()` is called with `$forceSyncMode = true` (the async branch would save `email_sent`). Other modules'
  `email_*_set_template_vars_before` observers still apply.
- Tonyapps modules register their own providers (`Model/EmailPreview` in AbandonedCart, ReviewReminder,
  StockNotification, AdvancedReports, B2b, B2bOrderApproval, B2bQuotes, NotifyOnNewBrowserLogin), built on the
  same variable code their senders use and read-only (placeholders where a send would create a token or coupon;
  see each module's AGENTS.md).

### Design Visually and images (phase 2)

- **Design Visually:** `Plugin/DesignVisuallyButton` (`etc/adminhtml/di.xml`), `beforeSetLayout` on
  `Magento\Email\Block\Adminhtml\Template\Edit`: core pushes its buttons to the toolbar while preparing the
  layout, so the button is added before that. Shown only for a template with a design
  (`DesignRepository::findIdByTemplate`) and with `Tonyapps_EmailDesigner::designs`.
- **Images:** GrapesJS's image picker gets an *Upload or choose from the media gallery* button (`addMediaGallery`
  in `designer.js`) that opens core's media browser (`cms/wysiwyg_images/index`, `MediabrowserUtility` from
  `mage/adminhtml/browser`) on folder `email` (`current_tree_path` = core id encoding of "email", created on
  first use). `etc/config.xml` adds `email` to the media gallery's allowed folders
  (`system/media_storage_configuration/allowed_resources/media_gallery_image_folders`). The browser writes into a
  hidden field (`ta-designer-image`, `data-force_static_path="1"`, so it returns a plain `/media/...` path, never
  core's admin directive URL); the editor makes it absolute with the default store view's media URL and sets it on
  the image. GrapesJS's own upload dropzone is off (no endpoint) and hidden in `designer.css`; adding by URL stays.
  Needs `Magento_Cms::media_gallery`; without it the config has `images: null` and no button.

### Magento blocks (phase 2)

- **In the canvas:** a block (component type `ta-block`, extends `mj-raw`) is one box with its name; it cannot be
  edited or styled, so a design cannot break an items table or a loop. It can only be dropped into a column.
- **In the email:** the block's directive in a table row of the column (`<mj-raw><tr><td>…</td></tr></mj-raw>`),
  styled like the starter's text.
- **Registering blocks for a module's emails:** add an item to the `blocks` argument of
  `Tonyapps\EmailDesigner\Model\BlockPool` in the module's `etc/di.xml` (`label`, `templates`: the email template
  IDs, `directive`). See this module's `etc/di.xml` for the core sales blocks. The entry is ignored when this module
  is not installed.
- **Starter designs:** the `starters` argument maps an email template ID to `Vendor_Module::path/file.mjml`, the
  content of the card's column (by convention `view/adminhtml/email_designer/`). Blocks in it are written
  `<mj-raw ta-block="block_id"></mj-raw>`; the editor turns them into `ta-block` components (unknown IDs stay plain
  `mj-raw`). Text keeps the original template's `{{trans}}` strings, so untouched text is still translated.
  Example: `../AbandonedCart/etc/di.xml`.

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
- `tonyapps_email_theme` (planned in the scope): not built. The owner chose one theme per install (2026-11-15), so
  it lives in configuration (`tonyapps_emaildesigner/theme/*`); the theme a design last got is stored in its
  project JSON (`taTheme`).

Rich blocks are not stored: they are registered in `di.xml` (`Tonyapps\EmailDesigner\Model\BlockPool`), keyed by
template code. The scope also allows a group key like "all sales emails". Each block has a label, an icon, a
placeholder preview and the directive it inserts. Other Tonyapps modules add theirs in their own `etc/di.xml`,
which only does anything when EmailDesigner is installed.

## Configuration and ACL

- Menu: **Marketing › Communications › Email Designer** (`Tonyapps_EmailDesigner::designs`, parent
  `Magento_Backend::marketing_communications`, route `tonyapps_emaildesigner/design/index`).
- ACL: `Tonyapps_EmailDesigner::designs`. Image upload also needs core's `Magento_Cms::media_gallery`.
- `etc/config.xml`: media gallery allowed folder `email` (see Design Visually and images).
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
| Where | **Marketing > Email Designer**: a grid of designs (name, based-on template, used in which settings, modified). **New design** asks for the email it is for (any template from `email_templates.xml`, core or Tonyapps). A **Design visually** button on the core *Email Templates* edit page for templates created by the designer [built 2026-11-14]. ACL `Tonyapps_EmailDesigner::designs`. |
| Editor | GrapesJS: canvas, blocks to drag in (sections, columns, text, image, button, divider, spacer, social links), style panel (colours, fonts, spacing, borders), undo/redo, desktop and mobile view, code view. The scope said "newsletter preset"; the build uses the `grapesjs-mjml` plugin. Assets bundled in the module (no CDN), pinned version. |
| Magento blocks | Each inserts a directive and shows a readable placeholder in the canvas (a "Product list" box rather than raw `{{layout ...}}`); the directive is not editable as text. **Common:** store logo, store name, store address and phone [built]; *View in browser* is out of scope. **Per email:** a **Variable** block listing that email's own variables from `@vars` [built as the Insert value toolbar menu], and rich blocks registered by modules: order items table, totals, billing and shipping address, payment and shipping method (core sales emails) [built; no separate totals block, core's items layout handles render the totals]; cart items with images, subtotal, coupon, restore button, promotion block (AbandonedCart); review link (ReviewReminder); product and back-in-stock link (StockNotification); quote lines and totals (B2bQuotes); cart table (B2bOrderApproval); and so on [not built]. |
| Starting point | A new design starts from a **starter layout** (header with logo, content, button, footer) with the email's essential blocks in place, e.g. order items and totals for an order email. No import from existing HTML templates: arbitrary template HTML does not convert reliably into editor blocks. |
| Brand theme | One **brand theme** per store configuration: logo, primary and secondary colour, font, button style, footer text. New designs use it, and a design can **Re-apply theme**. Changing the theme does not silently restyle saved designs. [built 2026-11-15 as one theme per install, owner decision; see phase 3a] |
| Subject | Edited in the designer (with variables), saved to the template's subject. |
| Preview | Live preview with **sample data** per email type: a real recent order, cart, quote etc. picked by the admin [built 2026-11-15, phase 3b], or a generated sample when there is none [built]. Rendered through core's template filter, so it shows exactly what will be sent. Desktop and mobile widths switchable. |
| Test send | **Send test** to an address (default: the admin's own, `adminEmail` in `Block/Adminhtml/Editor`), with the chosen sample data. Unsaved changes are saved first. [built] |
| Email-client safety | **MJML** (owner decision 2026-11-11). The editor works on MJML through `grapesjs-mjml`; on save the browser compiles it with `mjml-browser` into responsive, table-based HTML with inlined CSS (Outlook, Gmail). Server needs no Node.js. Directives sit inside `mj-text` (variables) or `mj-raw` (rich blocks), which MJML passes through unchanged. |
| Store views | Same as core: a template is global and the per-store choice happens in configuration. One design can be assigned to several store views, and a store view can use its own design. `{{trans}}` text blocks stay translatable via core translations; free text typed in the editor is per design. |
| Versions | Each save keeps the previous version (last 10). **Restore** puts a previous version back. |
| Safety | Only admins with the ACL can design. Generated HTML passes core's template filter in strict mode: only `{{var obj.field}}` and allow-listed directives, no method calls, the same rules core applies to custom templates. Image uploads go through the core media gallery (WYSIWYG image uploader) into `pub/media/email/` [built 2026-11-14; images by URL still possible]. |

Out of scope:

- Marketing campaigns and newsletters (core newsletter queue): transactional emails only.
- Drag-and-drop editing of storefront pages or CMS blocks.
- Converting existing hand-written HTML templates into visual designs.
- A/B tests, open and click tracking.

## Known limitations and open points

- Images use the default store view's media URL, so a store on another domain loads them from the default one.
- Hand edits in core's template form are overwritten by the next designer save (the design holds the MJML).
- `{{layout}}` blocks: labelled box in preview with sample data; with a real record they render for real.
- Stripe's plugin on the sales email identity containers loads the admin "create order" session quote in its
  constructor; a stale quote id there makes sales email sends (and previews) fail until the session is cleared.
  Seen only in the integration test (`RealDataPreviewTest` clears it); not ours to fix.
- Canvas font may only change after reopening a design when the theme's font changes (the compiled email is
  right straight away).
- Theme colours are swapped as exact hex values; a colour written differently (e.g. `rgb()`) is not themed.

Open points recorded before code (scope stage):

- **MJML and directives:** check that `mjml-browser` leaves `{{...}}` directives intact in attributes such as
  `mj-button href` and `mj-image src`, not only in text; if not, protect them with placeholders before compiling
  and restore them afterwards. Cost: about 1 MB more JS in the admin, loaded only on the designer page.
  → Resolved in phase 1: it does break quoted attribute values, so `directives.js` protects them.
- **Loops:** Magento's `{{for}}` loops (e.g. the AbandonedCart items) are placed only as whole rich blocks, not
  built in the editor, so a design cannot break a loop's markup. → Implemented by the locked `ta-block` type.
- **Licences:** GrapesJS is BSD-3 and `grapesjs-mjml` and MJML are MIT. Their notices must ship with the module
  and appear in the Composer package metadata. → Notices ship in `THIRD-PARTY-NOTICES.md`; the module itself
  is GPL-3.0-or-later since 2026-11-16 (BSD-3 and MIT are GPL-compatible).

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

- **2026-11-23** — Sample test sends with Magento `{{layout}}` blocks are stopped in the editor with a prompt
  to choose a recent record. The record picker identifies sample values as placeholders, and the preview and
  send-test dialogs describe the selected mode. CSS class selectors at the start of a `<style>` line get a safe
  prefix before saving so SMTP dot-stuffing cannot break their rules. `directives.test.js` covers both cases.
- **2026-11-16** — Released as free software under GPL-3.0-or-later (owner decision): `LICENSE` added,
  `composer.json` license changed from `proprietary`, License section in the README.
- **2026-11-16** — Fix: `BlockPool`'s constructor had `ComponentRegistrar $componentRegistrar = new
  ComponentRegistrar()`. `setup:di:compile` writes that default into `generated/metadata` as
  `ComponentRegistrar::__set_state()`, so every request on a compiled (production) install fatalled, with or
  without this module enabled (compile scans all of `app/code`). The registrar is now a required first argument.
  `BlockPoolTest` checks that no constructor in the module has an object default. Found by compiling with the
  module disabled; developer mode never compiles, so the earlier tests missed it.
- **2026-11-15** — Phase 3: brand theme (config, `Model/Theme`, `theme.js`, Apply brand theme, `taTheme` in the
  project, `ta-footer` on the starter footer) and real-data preview (`PreviewPool`, `Preview/SalesEmails` with
  capturing core senders, record select, `Renderer` record support; `magento/module-sales` and
  `magento/module-config` added to `composer.json`). Tests: unit `ThemeTest`, `PreviewPoolTest`, `RendererTest`
  (record), `StarterLayoutTest` (footer class); JS `theme.test.js`; integration `DesignControllerTest` (theme
  config page and editor), `RealDataPreviewTest` (real order and invoice; nothing sent or saved with async on or
  the email off); browser checks of apply/re-apply and the record select.

- **2026-11-14** — *Design Visually* button on core's template edit page (`Plugin/DesignVisuallyButton`,
  `DesignRepository::findIdByTemplate`); image upload through core's media browser into `pub/media/email/`
  (`etc/config.xml` allowed folder, `Editor::getImagesConfig`, `addMediaGallery`); `magento/module-cms` added to
  `composer.json`. Tests: `DesignVisuallyButtonTest`, `DesignControllerTest` (button on core's page, none for a
  hand-written template, media browser URL, core media browser opens on `email`, `onInsert` returns a plain
  path), browser check with a stubbed media browser (selected image and a freshly dropped unselected one; the
  picker callback from `AssetManager.__getBehaviour().select` sets the image). Not tested: a real upload and pick in
  the admin dialog (owner QA).
- **2026-11-13** — Starter designs and blocks for the Tonyapps emails: `BlockPool` `starters` argument and
  `getStarter()`, `StarterLayout` takes the content or places the email's blocks, the editor recognises
  `<mj-raw ta-block>`, loop sample rows in preview and test send. Integration test `RegisteredStartersTest` checks
  every enabled Tonyapps email: starter exists, uses only its email's blocks, renders in strict mode.

- **2026-11-11** — Phase 2 started: `Model/BlockPool`, `etc/di.xml` core sales blocks, `ta-block` component,
  preview shows `{{layout}}` blocks as labelled boxes (uncommitted at the time of the README split).
- **2026-11-11** — Manual QA of phase 1 passed (owner).
- **2026-11-11** — Phase 1 built; scope approved; owner decisions (shared module, GrapesJS + MJML, Tonyapps and
  core emails).
