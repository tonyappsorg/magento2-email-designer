<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Test\Unit\Model;

use Magento\Email\Model\Template;
use Magento\Email\Model\TemplateFactory;
use Magento\Framework\DataObject;
use PHPUnit\Framework\TestCase;
use Tonyapps\EmailDesigner\Model\TemplateVariables;

class TemplateVariablesTest extends TestCase
{
    public function testSampleDataFollowsTheDeclaredPaths(): void
    {
        $sample = TemplateVariables::buildSampleData([
            'var order.increment_id' => 'Order Id',
            'var order.getCustomerName()' => 'Customer Name',
            'var order_data.is_not_virtual' => 'Order Type',
            'var formattedBillingAddress|raw' => 'Billing Address',
            'var customer.name' => 'Customer Name',
        ]);

        self::assertInstanceOf(DataObject::class, $sample['order']);
        self::assertSame('[Order Id]', $sample['order']->getData('increment_id'));
        self::assertSame('[Customer Name]', $sample['order']->getCustomerName(), 'getters read the snake_case field');
        self::assertSame('[Order Type]', $sample['order_data']->getData('is_not_virtual'));
        self::assertSame('[Billing Address]', $sample['formattedBillingAddress'], 'filters are dropped');
        self::assertSame('[Customer Name]', $sample['customer']->getData('name'));
    }

    public function testCoreVariablesAndOtherDirectivesAreLeftAlone(): void
    {
        $sample = TemplateVariables::buildSampleData([
            'var store.frontend_name' => 'Store Name',
            'var logo_url' => 'Logo',
            'var this.getUrl($store,\'customer/account/\')' => 'Account URL',
            'store url="customer/account/"' => 'Account URL',
            'var order.getItemById(3).name' => 'Item',
            'layout handle="sales_email_order_items"' => 'Items',
        ]);

        self::assertSame([], $sample);
    }

    public function testAnObjectWinsOverAPlainValueAtTheSamePath(): void
    {
        $sample = TemplateVariables::buildSampleData([
            'var customer' => 'Customer',
            'var customer.email' => 'Customer Email',
        ]);
        self::assertSame('[Customer Email]', $sample['customer']->getData('email'));

        $sample = TemplateVariables::buildSampleData([
            'var customer.email' => 'Customer Email',
            'var customer' => 'Customer',
        ]);
        self::assertSame('[Customer Email]', $sample['customer']->getData('email'));
    }

    public function testOptionsListTheEmailsVariablesThenTheStores(): void
    {
        $variables = new TemplateVariables($this->factoryFor('{"var order.increment_id":"Order Id"}', '{{trans "Your order"}}'));

        $options = $variables->getOptions('sales_email_order_template');

        self::assertSame(['label' => 'Order Id', 'value' => '{{var order.increment_id}}'], $options[0]);
        self::assertSame('{{var store.frontend_name}}', $options[1]['value']);
        self::assertSame('{{trans "Your order"}}', $variables->getDefaultSubject('sales_email_order_template'));
    }

    public function testAnUnknownEmailHasNoVariables(): void
    {
        $template = $this->createMock(Template::class);
        $template->method('loadDefault')->willThrowException(new \UnexpectedValueException('Email template is not defined.'));
        $factory = $this->createMock(TemplateFactory::class);
        $factory->expects(self::once())->method('create')->willReturn($template);
        $variables = new TemplateVariables($factory);

        self::assertSame([], $variables->getDeclared('nope'));
        self::assertSame('', $variables->getDefaultSubject('nope'), 'the failed lookup is remembered');
    }

    private function factoryFor(string $declared, string $subject): TemplateFactory
    {
        $template = $this->getMockBuilder(Template::class)->disableOriginalConstructor()
            ->onlyMethods(['setForcedArea', 'loadDefault', 'getData'])->getMock();
        $template->method('getData')->willReturnMap([
            ['orig_template_variables', null, $declared],
            ['template_subject', null, $subject],
        ]);
        $factory = $this->createMock(TemplateFactory::class);
        $factory->expects(self::once())->method('create')->willReturn($template);
        return $factory;
    }

    public function testEachLoopGetsOneSampleRowWithTheFieldsItsBodyReads(): void
    {
        $samples = TemplateVariables::loopSamples(
            '{{for item in items}}<td>{{var item.name}} {{var item.qty}} {{var item.name}}</td>{{/for}}'
            . '{{for product in products }}{{var product.url}}{{var other.x}}{{/for}}'
        );

        self::assertSame(['items', 'products'], array_keys($samples));
        self::assertSame(['name' => '[name]', 'qty' => '[qty]'], $samples['items'][0]->getData());
        self::assertSame(['url' => '[url]'], $samples['products'][0]->getData());
        self::assertSame([], TemplateVariables::loopSamples('<p>no loops</p>'));
    }
}
