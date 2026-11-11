<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Controller\Adminhtml\Design;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Store\Model\StoreManagerInterface;
use Tonyapps\EmailDesigner\Model\DesignRepository;
use Tonyapps\EmailDesigner\Model\Renderer;

/** Sends the saved design to an address with sample data. */
class SendTest extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Tonyapps_EmailDesigner::designs';

    public function __construct(
        Context $context,
        private readonly DesignRepository $designs,
        private readonly Renderer $renderer,
        private readonly StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        /** @var Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $email = trim((string)$this->getRequest()->getParam('email'));
        try {
            $design = $this->designs->get((int)$this->getRequest()->getParam('id'));
            $this->renderer->sendTest(
                (int)$design['template_id'],
                (string)$design['base_template_code'],
                $email,
                (int)$this->storeManager->getDefaultStoreView()?->getId()
            );
            return $result->setData(['success' => true, 'message' => (string)__('Test email sent to %1.', $email)]);
        } catch (\Exception $exception) {
            return $result->setData(['success' => false, 'message' => $exception->getMessage()]);
        }
    }
}
