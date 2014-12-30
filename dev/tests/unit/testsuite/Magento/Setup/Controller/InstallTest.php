<?php
/**
 * @copyright Copyright (c) 2014 X.commerce, Inc. (http://www.magentocommerce.com)
 */

namespace Magento\Setup\Controller;

class InstallTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|\Magento\Setup\Model\WebLogger
     */
    private $webLogger;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|\Magento\Setup\Model\Installer
     */
    private $installer;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|\Magento\Setup\Model\Installer\ProgressFactory
     */
    private $progressFactory;

    /**
     * @var Install
     */
    private $controller;

    public function setUp()
    {
        $this->webLogger = $this->getMock('\Magento\Setup\Model\WebLogger', [], [], '', false);
        $installerFactory = $this->getMock('\Magento\Setup\Model\InstallerFactory', [], [], '', false);
        $this->installer = $this->getMock('\Magento\Setup\Model\Installer', [], [], '', false);
        $this->progressFactory = $this->getMock('\Magento\Setup\Model\Installer\ProgressFactory', [], [], '', false);
        $installerFactory->expects($this->once())->method('create')->with($this->webLogger)->will(
            $this->returnValue($this->installer));
        $this->controller = new Install($this->webLogger, $installerFactory, $this->progressFactory);
    }

    public function testIndexAction()
    {
        $viewModel = $this->controller->indexAction();
        $this->assertInstanceOf('Zend\View\Model\ViewModel', $viewModel);
        $this->assertTrue($viewModel->terminate());
    }

    public function testStartAction()
    {
        $this->webLogger->expects($this->once())->method('clear');
        $this->installer->expects($this->once())->method('install');
        $this->installer->expects($this->exactly(2))->method('getInstallInfo');
        $jsonModel = $this->controller->startAction();
        $this->assertInstanceOf('\Zend\View\Model\JsonModel', $jsonModel);
        $variables = $jsonModel->getVariables();
        $this->assertArrayHasKey('key', $variables);
        $this->assertArrayHasKey('success', $variables);
        $this->assertArrayHasKey('messages', $variables);
        $this->assertTrue($variables['success']);
    }

    public function testStartActionWithError()
    {
        $this->webLogger->expects($this->once())->method('clear');
        $this->webLogger->expects($this->once())->method('logError');
        $this->installer->method('install')->will($this->throwException(new \LogicException));
        $jsonModel = $this->controller->startAction();
        $this->assertInstanceOf('\Zend\View\Model\JsonModel', $jsonModel);
        $variables = $jsonModel->getVariables();
        $this->assertArrayHasKey('success', $variables);
        $this->assertFalse($variables['success']);
    }

    public function testProgressAction()
    {
        $someNumber = 42;
        $consoleMessages = ['key1' => 'log message 1', 'key2' => 'log message 2'];
        $progress = $this->getMock('\Magento\Setup\Model\Installer\Progress', [], [], '', false);
        $this->progressFactory->expects($this->once())->method('createFromLog')->with($this->webLogger)->will(
            $this->returnValue($progress));
        $progress->expects($this->once())->method('getRatio')->will($this->returnValue($someNumber));
        $this->webLogger->expects($this->once())->method('get')->will($this->returnValue($consoleMessages));
        $jsonModel = $this->controller->progressAction();
        $this->assertInstanceOf('\Zend\View\Model\JsonModel', $jsonModel);
        $variables = $jsonModel->getVariables();
        $this->assertArrayHasKey('progress', $variables);
        $this->assertArrayHasKey('success', $variables);
        $this->assertArrayHasKey('console', $variables);
        $this->assertSame($consoleMessages, $variables['console']);
        $this->assertTrue($variables['success']);
        $this->assertSame(sprintf('%d', $someNumber * 100), $variables['progress']);
    }

    public function testProgressActionWithError()
    {
        $e = 'Some exception message';
        $this->progressFactory->expects($this->once())->method('createFromLog')
            ->will($this->throwException(new \LogicException($e)));
        $jsonModel = $this->controller->progressAction();
        $this->assertInstanceOf('\Zend\View\Model\JsonModel', $jsonModel);
        $variables = $jsonModel->getVariables();
        $this->assertArrayHasKey('success', $variables);
        $this->assertArrayHasKey('console', $variables);
        $this->assertFalse($variables['success']);
        $this->assertStringStartsWith('exception \'LogicException\' with message \'' . $e, $variables['console'][0]);
    }
}
