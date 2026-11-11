<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Controller\Adminhtml\Design;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Tonyapps\EmailDesigner\Model\DesignRepository;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Tonyapps_EmailDesigner::designs';

    public function __construct(Context $context, private readonly DesignRepository $designs)
    {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $request = $this->getRequest();
        /** @var Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        try {
            $designId = $this->designs->save(
                (int)$request->getParam('id') ?: null,
                (string)$request->getParam('name'),
                (string)$request->getParam('base'),
                (string)$request->getParam('subject'),
                (string)$request->getParam('project'),
                (string)$request->getParam('mjml'),
                (string)$request->getParam('html'),
                $this->_auth->getUser() ? (int)$this->_auth->getUser()->getId() : null
            );
            return $result->setData([
                'success' => true,
                'id' => $designId,
                'editUrl' => $this->getUrl('*/*/edit', ['id' => $designId]),
                'versions' => $this->designs->getVersions($designId),
            ]);
        } catch (LocalizedException $exception) {
            return $result->setData(['success' => false, 'message' => $exception->getMessage()]);
        }
    }
}
