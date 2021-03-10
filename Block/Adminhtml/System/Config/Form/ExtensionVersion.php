<?php

declare(strict_types=1);

namespace Tamara\Checkout\Block\Adminhtml\System\Config\Form;

use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Module\Dir;
use Tamara\Checkout\Gateway\Config\BaseConfig;

class ExtensionVersion extends \Magento\Config\Block\System\Config\Form\Field
{

    /**
     * @var BaseConfig
     */
    private $config;

    /**
     * @var Dir
     */
    private $moduleDir;

    /**
     * @param Context $context
     * @param Dir $moduleDir
     * @param BaseConfig $config
     * @param array $data
     */
    public function __construct(
        Context $context,
        Dir $moduleDir,
        BaseConfig $config,
        array $data = []
    ) {
        $this->moduleDir = $moduleDir;
        $this->config = $config;
        parent::__construct($context, $data);
    }

    protected function _renderValue(AbstractElement $element)
    {
        $version = $this->getVersion();
        return '<td class="value">' . $version . '</td>';
    }

    public function getVersion()
    {
        $modulePath = $this->moduleDir->getDir('Tamara_Checkout');
        $pathToNeededModule = $modulePath . DIRECTORY_SEPARATOR . "composer.json";
        if (file_exists($pathToNeededModule)) {
            $content = file_get_contents($pathToNeededModule);
            if ($content) {
                $jsonContent = json_decode($content, true);
                if (!empty($jsonContent['version'])) {
                    return $jsonContent['version'];
                }
            }
        }

        return 'Cannot get extension version from composer';
    }

    protected function _renderInheritCheckbox(AbstractElement $element)
    {
        return '<td class="use-default"></td>';
    }
}