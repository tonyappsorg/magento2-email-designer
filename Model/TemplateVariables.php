<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Model;

use Magento\Email\Model\Template;
use Magento\Email\Model\TemplateFactory;
use Magento\Framework\DataObject;

/**
 * What a design can use for the email it is built for: the variables the base template declares in its
 * <!--@vars @--> block, and sample values for previews and test sends.
 */
class TemplateVariables
{
    /** Variables core adds to every email (AbstractTemplate::addEmailVariables); samples must not shadow them. */
    private const CORE_ROOTS = ['this', 'store', 'logo_url', 'logo_alt', 'store_phone', 'store_hours', 'store_email'];

    private const STORE_VARIABLES = [
        '{{var store.frontend_name}}' => 'Store Name',
        '{{var store_email}}' => 'Store Email',
        '{{var store_phone}}' => 'Store Phone',
        '{{var store_hours}}' => 'Store Hours',
    ];

    /** @var array<string, Template> */
    private array $defaults = [];

    public function __construct(private readonly TemplateFactory $templateFactory)
    {
    }

    /** @return array<int, array{label: string, value: string}> the email's variables, then the store's */
    public function getOptions(string $baseCode): array
    {
        $options = [];
        foreach ($this->getDeclared($baseCode) as $directive => $label) {
            $options[] = ['label' => $label, 'value' => '{{' . $directive . '}}'];
        }
        foreach (self::STORE_VARIABLES as $value => $label) {
            $options[] = ['label' => (string)__($label), 'value' => $value];
        }
        return $options;
    }

    /** @return array<string, string> directive => label, e.g. "var order.increment_id" => "Order Id" */
    public function getDeclared(string $baseCode): array
    {
        $declared = json_decode((string)$this->loadDefault($baseCode)?->getData('orig_template_variables'), true);
        return is_array($declared) ? array_map('strval', $declared) : [];
    }

    public function getDefaultSubject(string $baseCode): string
    {
        return (string)$this->loadDefault($baseCode)?->getData('template_subject');
    }

    /** @return array<string, mixed> template variables filled with "[Label]" placeholders */
    public function getSampleData(string $baseCode): array
    {
        return self::buildSampleData($this->getDeclared($baseCode));
    }

    /**
     * "var order.increment_id" => "Order Id" becomes ['order' => DataObject(increment_id: '[Order Id]')].
     * A getter such as order.getCustomerName() is filled as the customer_name field, which DataObject returns
     * for that getter. Other method calls, filters and non-var directives are skipped.
     *
     * @param array<string, string> $declared
     * @return array<string, mixed>
     */
    public static function buildSampleData(array $declared): array
    {
        $tree = [];
        foreach ($declared as $directive => $label) {
            if (!preg_match('/^var\s+([^|]+)/', trim($directive), $matches)) {
                continue;
            }
            $path = [];
            foreach (explode('.', trim($matches[1])) as $segment) {
                if (preg_match('/^get([A-Z]\w*)\(\)$/', $segment, $getter)) {
                    $segment = strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $getter[1]));
                }
                if (!preg_match('/^\w+$/', $segment)) {
                    continue 2;
                }
                $path[] = $segment;
            }
            if (in_array($path[0], self::CORE_ROOTS, true)) {
                continue;
            }
            $node = &$tree;
            foreach (array_slice($path, 0, -1) as $segment) {
                if (!isset($node[$segment]) || !is_array($node[$segment])) {
                    $node[$segment] = [];
                }
                $node = &$node[$segment];
            }
            $leaf = end($path);
            if (!isset($node[$leaf]) || !is_array($node[$leaf])) {
                $node[$leaf] = '[' . $label . ']';
            }
            unset($node);
        }

        return array_map([self::class, 'toObjects'], $tree);
    }

    private static function toObjects(mixed $value): mixed
    {
        return is_array($value) ? new DataObject(array_map([self::class, 'toObjects'], $value)) : $value;
    }

    private function loadDefault(string $baseCode): ?Template
    {
        if (!array_key_exists($baseCode, $this->defaults)) {
            try {
                $template = $this->templateFactory->create();
                $template->setForcedArea($baseCode);
                $template->loadDefault($baseCode);
                $this->defaults[$baseCode] = $template;
            } catch (\Exception) {
                $this->defaults[$baseCode] = null;
            }
        }
        return $this->defaults[$baseCode];
    }
}
