<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Model\Preview\Sales;

/** Core's credit memo email sender that only builds the variables (see CapturesVariables). */
class CreditmemoSender extends \Magento\Sales\Model\Order\Email\Sender\CreditmemoSender
{
    use CapturesVariables;
}
