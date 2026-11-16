# Visual Email Designer for Magento 2

Design Magento's transactional emails with drag and drop, without editing template HTML.

In Magento Open Source, changing what an order confirmation or shipment email looks like means editing template
HTML and directives by hand in *Marketing > Email Templates*. This module adds a visual designer to the admin.
Staff build an email from sections, columns, text, images and buttons. The designer saves it as an ordinary
Magento email template, which you select in the email's setting the same way as any custom template.

## Features

- **Drag-and-drop editor**: lay out an email from sections, columns, text, images, buttons, dividers, spacers and
  social links, style them, switch between desktop and mobile view, and use the code view if you need it.
- **Responsive, email-client-safe output**: designs are written in MJML and compiled to table-based HTML with
  inlined CSS, the kind of markup Outlook and Gmail display reliably.
- **Any transactional email**: start a design for any email template known to the store, core Magento or a
  Tonyapps module.
- **Ready-made starting points**: every Tonyapps email opens as a ready-made copy of its current design (text,
  buttons, product lists), so you restyle rather than rebuild. Other emails open with a header with your logo, a
  content area with the email's blocks, a button and a footer.
- **Store blocks**: drop in the store logo (each store sends its own), store name and store contact details.
- **Insert values**: a menu in the text toolbar inserts the email's own variables (order number, customer name and
  so on) and the store's.
- **Sales email blocks**: order, invoice, shipment and credit memo emails (customer and guest) get ready-made
  blocks: items table, tracking numbers, billing and shipping address, payment and shipping method, customer note
  and comment. Tonyapps emails get theirs too: cart items and coupon, products to review, quote and approval
  tables, product alerts and more. Blocks stay locked in the canvas, so a design can't break an items table.
- **Your own images**: upload images or pick them from Magento's media gallery right in the image picker. They
  are stored in `pub/media/email/` and sent with their full address, so every email client shows them. Adding an
  image by URL still works.
- **Subject line**: edit the subject, with variables, in the designer.
- **Preview with real data**: preview the email at desktop or mobile width with a real recent order, invoice,
  shipment, cart, quote, approval request or alert from your store, or with sample data, and send yourself a test
  of exactly that email. Nothing is sent to the customer or changed.
- **Brand theme**: set your colours, font, button corners and footer once under *Stores › Configuration ›
  Advanced › Email Designer*. New designs start with it and **Apply brand theme** brings an existing design up to
  date; deliberate colours such as a green "approved" button stay.
- **Version history**: every save keeps the previous version (the last 10). Restore one with a click.
- **Safe to switch off**: designs are normal email templates. Disabling the module leaves them working, you just
  can't edit them visually any more.
- **No external calls**: the editor (GrapesJS) and MJML are bundled with the module. Nothing loads from a CDN,
  and the editor's telemetry is switched off.

## How it works

1. Open **Marketing > Communications > Email Designer** and click **New design**. Pick the email it is for.
2. Build the email in the editor, set the subject, preview it and send yourself a test.
3. Save. The design is stored as a template in **Marketing > Email Templates**.
4. Select it in that email's setting, for example *Stores > Configuration > Sales > Sales Emails > Order > New
   Order Confirmation Template*. The designer grid shows which settings use each design, and the template's page in
   *Marketing > Email Templates* has a **Design Visually** button that opens it in the designer.

Magento sends the email as usual. Variables, translations (`{{trans}}`) and conditions keep working, because
Magento processes the template like any other.

## Good to know

- With sample data, lists such as cart items show one sample row, and core sales items tables show as labelled
  boxes (empty in a test send). Pick a real record to see them filled in.
- The brand theme is one for the whole store installation; the logo is each store's own email logo. Changing the
  theme doesn't restyle saved designs until you apply it in the designer and save.
- A real-data preview shows customer data to the admin, and a test with it goes only to the address you type.
- Templates you have customised by hand can't be converted into visual designs. Designs start from the module's
  ready-made copy or the starter layout.
- Transactional emails only: not for newsletters or marketing campaigns, and no A/B tests or open and click
  tracking.
- A designed template can still be edited by hand in *Marketing > Email Templates*, but the next save in the
  designer replaces those edits. Use **Design Visually** on that page to go back to the designer.
- Uploading images needs the *Media Gallery* permission; without it, add images by URL.

## Requirements

- Magento Open Source 2.4 (developed on 2.4.8)
- PHP 8.3 or 8.4
- Magento modules: Backend, Cms, Email, Store, Theme, User

## Installation

```
composer require tonyapps/module-email-designer
bin/magento module:enable Tonyapps_EmailDesigner
bin/magento setup:upgrade
```

## Configuration

- **Marketing > Communications > Email Designer**: designs grid, editor.
- **Stores › Configuration › Advanced › Email Designer › Brand Theme**: colours, font, button corners, footer.
- **System > Permissions > User Roles**: *Marketing > Communications > Email Designer* grants access.
- Use a design in the email's own setting in **Stores > Configuration** (for example *Sales > Sales Emails*).

## For developers

Architecture, decisions, data model and tests are in [AGENTS.md](AGENTS.md). Third-party licences are in
[THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md).

## License

Free and open source under the [GNU General Public License v3.0 or later](LICENSE). Bundled third-party libraries keep their own licences, listed in [THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md).
