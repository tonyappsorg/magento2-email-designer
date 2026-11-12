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
- **Starter layout**: a new design opens with a header with your logo, a content area, a button and a footer.
- **Store blocks**: drop in the store logo (each store sends its own), store name and store contact details.
- **Insert values**: a menu in the text toolbar inserts the email's own variables (order number, customer name and
  so on) and the store's.
- **Sales email blocks**: order, invoice, shipment and credit memo emails (customer and guest) get ready-made
  blocks: items table, tracking numbers, billing and shipping address, payment and shipping method, customer note
  and comment. They stay locked in the canvas, so a design can't break an items table.
- **Subject line**: edit the subject, with variables, in the designer.
- **Preview and test send**: preview the email with sample data at desktop or mobile width, and send a test to
  any address.
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
   Order Confirmation Template*. The designer grid shows which settings use each design.

Magento sends the email as usual. Variables, translations (`{{trans}}`) and conditions keep working, because
Magento processes the template like any other.

## Good to know

- Images are added by URL. Uploading through the media gallery is not available yet.
- Ready-made blocks exist for core sales emails only. Tonyapps module emails can be designed with text, variables
  and the store blocks, but have no rich blocks or starter designs of their own yet.
- Preview and test send use generated sample data. The preview shows rich blocks such as the items table as
  labelled boxes, and in a test send the items table comes out empty. Previewing with a real order is planned.
- There is no brand theme yet (shared logo, colours, font and button style for all designs).
- Existing hand-written HTML templates can't be converted into visual designs. Designs start from the starter
  layout.
- Transactional emails only: not for newsletters or marketing campaigns, and no A/B tests or open and click
  tracking.
- There is no *Design visually* button on core's *Email Templates* page yet. Open designs from the Email
  Designer grid.

## Requirements

- Magento Open Source 2.4 (developed on 2.4.8)
- PHP 8.3 or 8.4
- Magento modules: Backend, Email, Store, Theme, User

## Installation

```
composer require tonyapps/module-email-designer
bin/magento module:enable Tonyapps_EmailDesigner
bin/magento setup:upgrade
```

## Configuration

- **Marketing > Communications > Email Designer**: designs grid, editor.
- **System > Permissions > User Roles**: *Marketing > Communications > Email Designer* grants access.
- Use a design in the email's own setting in **Stores > Configuration** (for example *Sales > Sales Emails*).

## For developers

Architecture, decisions, data model and tests are in [AGENTS.md](AGENTS.md). Third-party licences are in
[THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md).
