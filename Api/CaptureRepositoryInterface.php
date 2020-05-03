<?php

namespace Tamara\Checkout\Api;

use Tamara\Checkout\Model\Capture;
use Tamara\Checkout\Model\CaptureItem;

interface CaptureRepositoryInterface
{
    /**
     * @param Capture $capture
     * @return mixed
     */
    public function saveCapture(Capture $capture);

    /**
     * @param CaptureItem $capture
     * @return mixed
     */
    public function saveCaptureItems(array $data);

    /**
     * @param array $conditions
     * @return mixed
     */
    public function getCaptureByConditions(array $conditions);

    /**
     * @param array $conditions
     * @return mixed
     */
    public function getCaptureItemsByConditions(array $conditions);

    /**
     * @param $captureId
     * @return Capture
     */
    public function getCaptureById($captureId);
}