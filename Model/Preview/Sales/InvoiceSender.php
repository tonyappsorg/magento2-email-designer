<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Model\Preview\Sales;

/** Core's invoice email sender that only builds the variables (see CapturesVariables). */
class InvoiceSender extends \Magento\Sales\Model\Order\Email\Sender\InvoiceSender
{
    use CapturesVariables;
}
