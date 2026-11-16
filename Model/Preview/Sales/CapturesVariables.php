<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Model\Preview\Sales;

use Magento\Sales\Model\Order;
use Tonyapps\EmailDesigner\Model\Preview\CapturedVariables;

/**
 * For subclasses of core's sales email senders: send() builds the variables exactly as for a real email (including
 * other modules' email_*_set_template_vars_before observers), then this stops it before anything is sent or saved.
 * The stop is in the class itself, so a stale config can never turn a preview into a real email.
 * Call send() with $forceSyncMode = true: the async branch saves email_sent on the record.
 */
trait CapturesVariables
{
    /** @return never */
    protected function checkAndSend(Order $order)
    {
        // Order emails build their variables here; the other senders already did, this only sets template options.
        $this->prepareTemplate($order);
        throw new CapturedVariables($this->templateContainer->getTemplateVars());
    }
}
