<?php

namespace Tamara\Checkout\Controller;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;
use Magento\Framework\Exception\NotFoundException;

class Router implements RouterInterface
{
    private const
        MODULE_NAME = 'tamara',
        CONTROLLER_NAME = 'payment';

    private $actions = ['success', 'cancel', 'failure'];

    /**
     * @var \Magento\Framework\App\ActionFactory
     */
    protected $actionFactory;

    /**
     * Event manager
     *
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $_eventManager;

    /**
     * Store manager
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;


    /**
     * Config primary
     *
     * @var \Magento\Framework\App\State
     */
    protected $_appState;

    /**
     * Url
     *
     * @var \Magento\Framework\UrlInterface
     */
    protected $_url;

    /**
     * Response
     *
     * @var \Magento\Framework\App\ResponseInterface
     */
    protected $_response;

    public function __construct(
        \Magento\Framework\App\ActionFactory $actionFactory,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Framework\UrlInterface $url,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\ResponseInterface $response
    ) {
        $this->actionFactory = $actionFactory;
        $this->_eventManager = $eventManager;
        $this->_url = $url;
        $this->_storeManager = $storeManager;
        $this->_response = $response;
    }

    public function match(RequestInterface $request)
    {
        $identifier = trim($request->getPathInfo(), '/');
        $urlParts = explode('/', $identifier);

        if ($this->isCorrectUrl($urlParts)) {
            $request->setModuleName(self::MODULE_NAME)
                ->setControllerName(self::CONTROLLER_NAME)
                ->setActionName($urlParts[3])
                ->setParam('order_id', $urlParts[2]);

            $request->setAlias(\Magento\Framework\Url::REWRITE_REQUEST_PATH_ALIAS, $identifier);
            return $this->actionFactory->create(\Magento\Framework\App\Action\Forward::class);
        }
        return;
    }

    private function isCorrectUrl($urlParts): bool
    {
        if (empty($urlParts[0]) || empty($urlParts[1]) || empty($urlParts[2]) || empty($urlParts[3])) {
            return false;
        }

        if ($urlParts[0] !== self::MODULE_NAME
            || $urlParts[1] !== self::CONTROLLER_NAME
            || (!is_numeric($urlParts[2]))
            || (!in_array($urlParts[3], $this->actions, true))
        ) {
            return false;
        }

        return true;
    }

}