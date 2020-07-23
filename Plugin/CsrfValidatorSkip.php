<?php

namespace Tamara\Checkout\Plugin;

class CsrfValidatorSkip
{
    private const SKIP_CSRF_URLS = [
        '/tamara/payment/notification',
        '/tamara/payment/webhook',
    ];

    /**
     * @param \Magento\Framework\App\Request\CsrfValidator $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\App\ActionInterface $action
     */
    public function aroundValidate(
        $subject,
        \Closure $proceed,
        $request,
        $action
    ) {
        /* Magento 2.1.x, 2.2.x */
        if ($request->getModuleName() === 'Tamara_Checkout') {
            return; // Skip CSRF check
        }

        /* Magento 2.3.x */
        if (in_array($request->getOriginalPathInfo(), self::SKIP_CSRF_URLS)) {
            return; // Skip CSRF check
        }

        $proceed($request, $action); // Proceed Magento 2 core functionalities
    }
}