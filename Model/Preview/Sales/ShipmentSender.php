<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Model\Preview\Sales;

/** Core's shipment email sender that only builds the variables (see CapturesVariables). */
class ShipmentSender extends \Magento\Sales\Model\Order\Email\Sender\ShipmentSender
{
    use CapturesVariables;
}
