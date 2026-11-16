<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Model;

use Magento\Email\Model\Template\Config as EmailConfig;
use Magento\Email\Model\TemplateFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\TemplateTypesInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Mail\Template\TransportBuilder;

/**
 * Preview and test send through core's template filter, so both show what the email will really look like: with
 * generated sample data, or with a real record's variables from the email's preview provider (PreviewPool).
 */
class Renderer
{
    public function __construct(
        private readonly TemplateFactory $templateFactory,
        private readonly TemplateVariables $templateVariables,
        private readonly EmailConfig $emailConfig,
        private readonly TransportBuilder $transportBuilder,
        private readonly BlockPool $blockPool,
        private readonly PreviewPool $previewPool
    ) {
    }

    /** @return array{subject: string, html: string} */
    public function render(string $html, string $subject, string $baseCode, int $storeId, string $recordId = ''): array
    {
        $variables = $this->getRecordVariables($baseCode, $recordId);
        if ($variables === null) {
            // Sample data has no real order to load, so {{layout}} blocks become labelled boxes.
            $html = $this->withPlaceholders($html);
            $variables = $this->templateVariables->getSampleData($baseCode, $html);
        } else {
            $storeId = $this->previewPool->getStoreId($baseCode, $recordId, $variables) ?? $storeId;
        }
        $template = $this->templateFactory->create();
        $template->setTemplateText($html)
            ->setTemplateSubject($subject)
            ->setTemplateType(TemplateTypesInterface::TYPE_HTML)
            ->setDesignConfig(['area' => $this->getArea($baseCode), 'store' => $storeId]);

        try {
            return [
                'subject' => (string)$template->getProcessedTemplateSubject($variables),
                'html' => (string)$template->getProcessedTemplate($variables),
            ];
        } catch (\Throwable $exception) {
            throw new LocalizedException(__('The design could not be rendered: %1', $exception->getMessage()), $exception);
        }
    }

    /**
     * {{layout}} blocks (order items and the like) load the real order, quote or shipment by ID, which sample
     * data does not have, so the preview shows a labelled box in their place.
     */
    private function withPlaceholders(string $html): string
    {
        return (string)preg_replace_callback('/\{\{layout\b[^}]*\}\}/', function (array $match): string {
            $label = $this->blockPool->getLabel($match[0]) ?? (string)__('Magento block');
            return '<div style="border:1px dashed #b3b3b3;padding:16px;text-align:center;color:#777777;'
                . 'font-family:Helvetica,Arial,sans-serif;font-size:13px">'
                . htmlspecialchars((string)__('%1 (shown with real data in the email)', $label)) . '</div>';
        }, $html);
    }

    /** Sends the saved template of a design with sample data or a real record's variables. */
    public function sendTest(int $templateId, string $baseCode, string $email, int $storeId, string $recordId = ''): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new LocalizedException(__('Enter a valid email address for the test.'));
        }
        $variables = $this->getRecordVariables($baseCode, $recordId);
        if ($variables === null) {
            $variables = $this->templateVariables->getSampleData(
                $baseCode,
                (string)$this->templateFactory->create()->load($templateId)->getTemplateText()
            );
        } else {
            $storeId = $this->previewPool->getStoreId($baseCode, $recordId, $variables) ?? $storeId;
        }
        $this->transportBuilder
            ->setTemplateIdentifier((string)$templateId)
            ->setTemplateOptions(['area' => $this->getArea($baseCode), 'store' => $storeId])
            ->setTemplateVars($variables)
            ->setFromByScope('general', $storeId)
            ->addTo($email)
            ->getTransport()
            ->sendMessage();
    }

    /** @return array<string, mixed>|null null for sample data */
    private function getRecordVariables(string $baseCode, string $recordId): ?array
    {
        if ($recordId === '') {
            return null;
        }
        try {
            $variables = $this->previewPool->getVariables($baseCode, $recordId);
        } catch (LocalizedException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new LocalizedException(__('This record can not be previewed: %1', $exception->getMessage()), $exception);
        }
        if ($variables === null) {
            throw new LocalizedException(__('This email has no real-data preview.'));
        }
        return $variables;
    }

    private function getArea(string $baseCode): string
    {
        try {
            return $this->emailConfig->getTemplateArea($baseCode);
        } catch (\Exception) {
            return Area::AREA_FRONTEND;
        }
    }
}
