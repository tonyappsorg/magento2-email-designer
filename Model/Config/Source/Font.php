<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/** Font stacks every email client has, so the email looks the same everywhere. */
class Font implements OptionSourceInterface
{
    public const FONTS = [
        'Helvetica, Arial, sans-serif',
        'Arial, Helvetica, sans-serif',
        'Verdana, Geneva, sans-serif',
        'Tahoma, Verdana, sans-serif',
        '"Trebuchet MS", Helvetica, sans-serif',
        'Georgia, "Times New Roman", serif',
        '"Times New Roman", Times, serif',
        '"Courier New", Courier, monospace',
    ];

    public function toOptionArray(): array
    {
        return array_map(
            static fn (string $font): array => ['value' => $font, 'label' => str_replace('"', '', strtok($font, ','))],
            self::FONTS
        );
    }
}
