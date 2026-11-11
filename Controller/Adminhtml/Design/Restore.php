<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Controller\Adminhtml\Design;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Tonyapps\EmailDesigner\Model\DesignRepository;

class Restore extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Tonyapps_EmailDesigner::designs';

    public function __construct(Context $context, private readonly DesignRepository $designs)
    {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $designId = (int)$this->getRequest()->getParam('id');
        try {
            $this->designs->restore(
                $designId,
                (int)$this->getRequest()->getParam('version'),
                $this->_auth->getUser() ? (int)$this->_auth->getUser()->getId() : null
            );
            $this->messageManager->addSuccessMessage(__('The earlier version is back and is now the current design.'));
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/edit', ['id' => $designId]);
    }
}
