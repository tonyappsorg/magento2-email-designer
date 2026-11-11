<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Test\Unit\Model;

use Magento\Email\Model\ResourceModel\Template as TemplateResource;
use Magento\Email\Model\TemplateFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Tonyapps\EmailDesigner\Model\DesignRepository;
use Tonyapps\EmailDesigner\Model\TemplateVariables;

class DesignRepositoryTest extends TestCase
{
    /**
     * @dataProvider incompleteDesigns
     */
    public function testAnIncompleteDesignIsRefusedBeforeTouchingTheDatabase(string $name, string $html, string $project): void
    {
        $resource = $this->createMock(ResourceConnection::class);
        $resource->expects(self::never())->method('getConnection');
        $repository = new DesignRepository(
            $resource,
            $this->createMock(TemplateFactory::class),
            $this->createMock(TemplateResource::class),
            $this->createMock(TemplateVariables::class)
        );

        $this->expectException(LocalizedException::class);
        $repository->save(null, $name, 'sales_email_order_template', 'Subject', $project, '<mjml/>', $html, 1);
    }

    public static function incompleteDesigns(): array
    {
        return [
            'no name' => ['  ', '<html></html>', '{}'],
            'no content' => ['Autumn', ' ', '{}'],
            'broken project' => ['Autumn', '<html></html>', '{oops'],
        ];
    }
}
