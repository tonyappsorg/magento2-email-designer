<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Model;

use Magento\Framework\Component\ComponentRegistrar;

/**
 * Magento blocks for the editor: each one inserts a directive (an items table, an address, a loop) that a
 * design cannot break, because the canvas shows it as one box.
 *
 * Modules register blocks for their emails in di.xml:
 *
 *     <type name="Tonyapps\EmailDesigner\Model\BlockPool">
 *         <arguments><argument name="blocks" xsi:type="array">
 *             <item name="cart_items" xsi:type="array">
 *                 <item name="label" xsi:type="string">Cart items</item>
 *                 <item name="templates" xsi:type="array"><item name="0" xsi:type="string">module_email_id</item></item>
 *                 <item name="directive" xsi:type="string">{{for item in items}}…{{/for}}</item>
 *             </item>
 *         </argument></arguments>
 *     </type>
 *
 * A module can also give an email a starter design: the `starters` argument maps the email template ID to an MJML
 * file in the module (`Vendor_Module::path/file.mjml`) holding the content of the main column. Its blocks are written
 * as `<mj-raw ta-block="block_id"></mj-raw>`.
 *
 * The di.xml entry is ignored when this module is not installed, so modules need no dependency on it.
 */
class BlockPool
{
    /**
     * @param array<string, array{label: string, templates: string[], directive: string}> $blocks
     * @param array<string, string> $starters email template ID => "Vendor_Module::path/file.mjml"
     */
    public function __construct(
        private readonly array $blocks = [],
        private readonly array $starters = [],
        private readonly ComponentRegistrar $componentRegistrar = new ComponentRegistrar()
    ) {
    }

    /** The registered starter content for one email, or null. */
    public function getStarter(string $baseCode): ?string
    {
        if (!isset($this->starters[$baseCode]) || !str_contains($this->starters[$baseCode], '::')) {
            return null;
        }
        [$module, $file] = explode('::', $this->starters[$baseCode], 2);
        $path = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, $module) . '/' . $file;
        return is_file($path) ? (string)file_get_contents($path) : null;
    }

    /** @return array<int, array{id: string, label: string, directive: string}> the blocks for one email */
    public function getFor(string $baseCode): array
    {
        $result = [];
        foreach ($this->blocks as $id => $block) {
            if (in_array($baseCode, (array)($block['templates'] ?? []), true)) {
                $result[] = ['id' => (string)$id, 'label' => (string)__($block['label']), 'directive' => (string)$block['directive']];
            }
        }
        return $result;
    }

    /** The label of the block that inserts this directive, for placeholders in previews. */
    public function getLabel(string $directive): ?string
    {
        foreach ($this->blocks as $block) {
            if (str_contains((string)$block['directive'], $directive)) {
                return (string)__($block['label']);
            }
        }
        return null;
    }
}
