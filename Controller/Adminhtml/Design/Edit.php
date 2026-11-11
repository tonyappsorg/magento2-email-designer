<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Controller\Adminhtml\Design;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Email\Model\Template\Config as EmailConfig;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Tonyapps\EmailDesigner\Model\DesignRepository;

/** Opens the editor for a saved design (id) or a new one (base email code and name). */
class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Tonyapps_EmailDesigner::designs';

    public function __construct(
        Context $context,
        private readonly DesignRepository $designs,
        private readonly EmailConfig $emailConfig
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $designId = (int)$this->getRequest()->getParam('id');
        if ($designId) {
            try {
                $title = (string)$this->designs->get($designId)['template_code'];
            } catch (NoSuchEntityException $exception) {
                return $this->back((string)$exception->getMessage());
            }
        } else {
            $title = trim((string)$this->getRequest()->getParam('name'));
            try {
                $this->emailConfig->getTemplateLabel((string)$this->getRequest()->getParam('base'));
            } catch (\UnexpectedValueException) {
                return $this->back((string)__('Choose the email the design is for.'));
            }
            if ($title === '') {
                return $this->back((string)__('Enter a name for the design.'));
            }
        }

        /** @var \Magento\Backend\Model\View\Result\Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu(self::ADMIN_RESOURCE);
        $page->getConfig()->getTitle()->prepend($title);
        return $page;
    }

    private function back(string $message): ResultInterface
    {
        $this->messageManager->addErrorMessage($message);
        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
