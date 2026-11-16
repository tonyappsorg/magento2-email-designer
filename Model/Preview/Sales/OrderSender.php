<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Model\Preview\Sales;

/** Core's order email sender that only builds the variables (see CapturesVariables). */
class OrderSender extends \Magento\Sales\Model\Order\Email\Sender\OrderSender
{
    use CapturesVariables;
}
