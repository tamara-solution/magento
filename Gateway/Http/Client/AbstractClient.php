<?php

namespace Tamara\Checkout\Gateway\Http\Client;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;
use Tamara\Checkout\Model\Adapter\TamaraAdapterFactory;

abstract class AbstractClient implements ClientInterface
{

    /**
     * @var SubjectReader
     */
    protected $subjecReader;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var TamaraAdapterFactory
     */
    protected $adapterFactory;

    /**
     * AbstractClient constructor.
     * @param SubjectReader $subjecReader
     * @param Logger $logger
     * @param TamaraAdapterFactory $adapterFactory
     */
    public function __construct(SubjectReader $subjecReader, Logger $logger, TamaraAdapterFactory $adapterFactory)
    {
        $this->subjecReader = $subjecReader;
        $this->logger = $logger;
        $this->adapterFactory = $adapterFactory;
    }

    public function placeRequest(TransferInterface $transferObject): array
    {
        $this->logger->debug(['Tamara - Start place order client']);
        $data = $transferObject->getBody();

        $log = [
            'request' => $transferObject->getBody(),
            'client' => static::class
        ];

        try {
            $response = $this->process($data);
        } catch (\Exception $e) {
            $message = __($e->getMessage() ?: "Something went wrong during Gateway request.");
            $log['error'] = $message;
            $this->logger->debug(["Tamara" => $log]);
            throw $e;
        }

        $this->logger->debug(['Tamra - End place order client']);
        return $response;
    }

    /**x
     * Process http request
     *
     * @param array $data
     */
    abstract protected function process(array $data);
}