<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Tonyapps\EmailDesigner\Model\Config\Source\Font;

/**
 * The brand theme (one per install, default scope). Values that are not valid fall back to the default theme,
 * the one starter designs and module blocks are written in.
 */
class Theme
{
    public const DEFAULTS = [
        'primary' => '#1979c3',
        'background' => '#f5f5f5',
        'font' => 'Helvetica, Arial, sans-serif',
        'radius' => '3px',
        'footer' => '{{var store.frontend_name}} · {{var store_email}}',
    ];

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    /** @return array{primary: string, background: string, font: string, radius: string, footer: string} */
    public function get(): array
    {
        $value = fn (string $field): string => trim((string)$this->scopeConfig->getValue('tonyapps_emaildesigner/theme/' . $field));
        $theme = self::DEFAULTS;
        foreach (['primary', 'background'] as $colour) {
            if (preg_match('/^#[0-9a-f]{6}$/i', $value($colour))) {
                $theme[$colour] = strtolower($value($colour));
            }
        }
        if (in_array($value('font'), Font::FONTS, true)) {
            $theme['font'] = $value('font');
        }
        if (preg_match('/^\d{1,2}$/', $value('radius'))) {
            $theme['radius'] = $value('radius') . 'px';
        }
        if ($value('footer') !== '') {
            $theme['footer'] = $value('footer');
        }
        return $theme;
    }
}
