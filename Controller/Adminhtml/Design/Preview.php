<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Controller\Adminhtml\Design;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Tonyapps\EmailDesigner\Model\Renderer;

/** Renders the unsaved editor content with sample data. */
class Preview extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Tonyapps_EmailDesigner::designs';

    public function __construct(
        Context $context,
        private readonly Renderer $renderer,
        private readonly StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $request = $this->getRequest();
        /** @var Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        try {
            return $result->setData(['success' => true] + $this->renderer->render(
                (string)$request->getParam('html'),
                (string)$request->getParam('subject'),
                (string)$request->getParam('base'),
                (int)$this->storeManager->getDefaultStoreView()?->getId()
            ));
        } catch (LocalizedException $exception) {
            return $result->setData(['success' => false, 'message' => $exception->getMessage()]);
        }
    }
}
