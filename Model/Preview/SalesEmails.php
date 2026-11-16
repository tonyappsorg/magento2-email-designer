<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Model\Preview;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Tonyapps\EmailDesigner\Model\Preview\Sales\CreditmemoSender;
use Tonyapps\EmailDesigner\Model\Preview\Sales\InvoiceSender;
use Tonyapps\EmailDesigner\Model\Preview\Sales\OrderSender;
use Tonyapps\EmailDesigner\Model\Preview\Sales\ShipmentSender;

/**
 * Real-data preview for core's order, invoice, shipment and credit memo emails: the variables come from core's own
 * senders (see Sales\CapturesVariables), so the preview is the email core would send for that record.
 */
class SalesEmails
{
    /** Email type => its table. */
    private const TYPES = [
        'order' => 'sales_order',
        'invoice' => 'sales_invoice',
        'shipment' => 'sales_shipment',
        'creditmemo' => 'sales_creditmemo',
    ];

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly OrderRepositoryInterface $orders,
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly ShipmentRepositoryInterface $shipments,
        private readonly CreditmemoRepositoryInterface $creditmemos,
        private readonly OrderSender $orderSender,
        private readonly InvoiceSender $invoiceSender,
        private readonly ShipmentSender $shipmentSender,
        private readonly CreditmemoSender $creditmemoSender
    ) {
    }

    /** @return array<string, string> record ID => label, newest first */
    public function getRecords(string $templateId, int $limit): array
    {
        [$type, $guest] = $this->parse($templateId);
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['record' => $this->resource->getTableName(self::TYPES[$type])], ['entity_id', 'increment_id'])
            ->where('o.customer_is_guest = ?', (int)$guest)
            ->order('record.entity_id DESC')
            ->limit($limit);
        if ($type === 'order') {
            $select->join(['o' => $this->resource->getTableName('sales_order')], 'o.entity_id = record.entity_id', []);
        } else {
            $select->join(['o' => $this->resource->getTableName('sales_order')], 'o.entity_id = record.order_id', []);
        }
        $select->columns([
            'customer' => new \Magento\Framework\DB\Sql\Expression("TRIM(CONCAT_WS(' ', o.customer_firstname, o.customer_lastname))"),
            'created_at' => 'record.created_at',
        ]);

        $records = [];
        foreach ($connection->fetchAll($select) as $row) {
            $records[(string)$row['entity_id']] = implode(' · ', array_filter([
                '#' . $row['increment_id'],
                $row['customer'],
                substr((string)$row['created_at'], 0, 10),
            ]));
        }
        return $records;
    }

    /** @return array<string, mixed> */
    public function getVariables(string $templateId, string $recordId): array
    {
        [$type] = $this->parse($templateId);
        $id = (int)$recordId;
        try {
            match ($type) {
                'order' => $this->orderSender->send($this->orders->get($id), true),
                'invoice' => $this->invoiceSender->send($this->invoices->get($id), true),
                'shipment' => $this->shipmentSender->send($this->shipments->get($id), true),
                'creditmemo' => $this->creditmemoSender->send($this->creditmemos->get($id), true),
            };
        } catch (CapturedVariables $captured) {
            return $captured->variables;
        }
        throw new LocalizedException(__('The email variables for this record could not be built.'));
    }

    /** @return array{string, bool} email type and whether it is the guest version */
    private function parse(string $templateId): array
    {
        if (!preg_match('/^sales_email_(order|invoice|shipment|creditmemo)_(guest_)?template$/', $templateId, $m)) {
            throw new LocalizedException(__('No real-data preview for this email.'));
        }
        return [$m[1], ($m[2] ?? '') !== '']; // PCRE drops a trailing unmatched group
    }
}
