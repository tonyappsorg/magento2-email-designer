<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Model;

/**
 * Real-data preview providers. A module registers one for its emails in di.xml, without depending on this module:
 *
 *     <type name="Tonyapps\EmailDesigner\Model\PreviewPool">
 *         <arguments><argument name="providers" xsi:type="array">
 *             <item name="module_emails" xsi:type="array">
 *                 <item name="templates" xsi:type="array"><item name="0" xsi:type="string">module_email_id</item></item>
 *                 <item name="provider" xsi:type="object">Vendor\Module\Model\EmailPreview</item>
 *             </item>
 *         </argument></arguments>
 *     </type>
 *
 * The provider has two methods (duck-typed):
 * - getRecords(string $templateId, int $limit): array<string, string>, record ID => label, newest first;
 * - getVariables(string $templateId, string $recordId): array, the template variables the module's sender uses for
 *   that record, built by the sender's own code. It must not change anything: no rows, no tokens, no coupons;
 * - optionally getStoreId(string $templateId, string $recordId): ?int, the store the record's email goes out from,
 *   when the variables have no `store` object (otherwise the preview uses the default store view).
 */
class PreviewPool
{
    /** @param array<string, array{templates: string[], provider: object}> $providers */
    public function __construct(private readonly array $providers = [])
    {
    }

    /** @return array<string, string> */
    public function getRecords(string $templateId, int $limit = 20): array
    {
        $provider = $this->find($templateId);
        return $provider ? $provider->getRecords($templateId, $limit) : [];
    }

    /** @return array<string, mixed>|null null when the email has no provider */
    public function getVariables(string $templateId, string $recordId): ?array
    {
        return $this->find($templateId)?->getVariables($templateId, $recordId);
    }

    /** The record's store, from the provider or the variables' `store` object; null when unknown. */
    public function getStoreId(string $templateId, string $recordId, array $variables): ?int
    {
        $store = $variables['store'] ?? null;
        if ($store instanceof \Magento\Store\Api\Data\StoreInterface) {
            return (int)$store->getId();
        }
        $provider = $this->find($templateId);
        return $provider && method_exists($provider, 'getStoreId') ? $provider->getStoreId($templateId, $recordId) : null;
    }

    private function find(string $templateId): ?object
    {
        foreach ($this->providers as $entry) {
            if (in_array($templateId, (array)($entry['templates'] ?? []), true) && is_object($entry['provider'] ?? null)) {
                return $entry['provider'];
            }
        }
        return null;
    }
}
