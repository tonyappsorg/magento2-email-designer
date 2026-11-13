<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Model;

/**
 * The layout a new design starts from: logo, a content card, and a footer. The card holds the email's registered
 * starter content, else a heading, a text, the email's Magento blocks and a button.
 * Tags are closed explicitly: the editor parses components as HTML, where <mj-x/> would swallow its siblings.
 */
class StarterLayout
{
    /**
     * @param array<int, array{id: string}> $blocks the email's Magento blocks, used when there is no $content
     */
    public static function mjml(string $logoUrl, ?string $content = null, array $blocks = []): string
    {
        $logo = htmlspecialchars($logoUrl, ENT_QUOTES);
        $content ??= self::defaultContent($blocks);
        return <<<MJML
<mjml>
  <mj-head>
    <mj-attributes>
      <mj-all font-family="Helvetica, Arial, sans-serif"></mj-all>
      <mj-text font-size="14px" line-height="1.6" color="#333333"></mj-text>
    </mj-attributes>
  </mj-head>
  <mj-body background-color="#f5f5f5">
    <mj-section padding="24px 0 12px">
      <mj-column>
        <mj-image src="{$logo}" css-class="ta-store-logo" alt="{{var logo_alt}}" width="180px" align="left"></mj-image>
      </mj-column>
    </mj-section>
    <mj-section background-color="#ffffff" padding="32px 24px" border-radius="4px">
      <mj-column>
{$content}
      </mj-column>
    </mj-section>
    <mj-section padding="16px 24px">
      <mj-column>
        <mj-text font-size="12px" color="#999999" align="center">{{var store.frontend_name}} · {{var store_email}}</mj-text>
      </mj-column>
    </mj-section>
  </mj-body>
</mjml>
MJML;
    }

    /** @param array<int, array{id: string}> $blocks */
    private static function defaultContent(array $blocks): string
    {
        $rows = ['<mj-text font-size="24px" font-weight="600">Your heading</mj-text>',
            '<mj-text>Write your message here. In a text block, use the { } menu in the text toolbar to insert values such as the customer\'s name.</mj-text>'];
        foreach ($blocks as $block) {
            $rows[] = '<mj-raw ta-block="' . htmlspecialchars($block['id'], ENT_QUOTES) . '"></mj-raw>';
        }
        $rows[] = '<mj-button background-color="#1979c3" border-radius="3px" font-weight="600" href="{{store url=\'\'}}">Visit the store</mj-button>';
        return '        ' . implode("\n        ", $rows);
    }
}
