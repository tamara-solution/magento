<?php

namespace Tamara\Checkout\Controller\Adminhtml\Whitelist;

use Magento\Backend\App\Action;
use Magento\Framework\Exception\LocalizedException;
use Tamara\Checkout\Model\EmailWhiteListFactory;

class Save extends Action
{
    /**
     * @var RuleRepositoryInterface
     */
    private $whitelistFactory;

    protected $timezone;

    public function __construct(
        Action\Context $context,
        EmailWhiteListFactory $whiteListFactory,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone
    )
    {
        parent::__construct($context);

        $this->whitelistFactory = $whiteListFactory;
        $this->timezone = $timezone;
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        /** @var int[]|null $ids */
        $email = $this->getRequest()->getPost('customer_email');
        $id = $this->getRequest()->getPost('whitelist_id');
        $model = $this->whitelistFactory->create();
        $updateAt = $createAt = date('Y-m-d H:i:s', $this->timezone->date()->getTimestamp());
        if ($id) {
            $model->load($id);
            $createAt = $model->getCreatedAt();
        }
        if ($email) {
            $collections = $model->getCollection()->addFieldToFilter('customer_email', $email)->getFirstItem();
            if ($collections->getId() > 0) {
                $this->messageManager->addErrorMessage(__('The email are existed!'));
                return $this->_redirect('*/*/');
            }
        }
        try {
            $model->setCustomerEmail($email)
                ->setUpdatedAt($updateAt)
                ->setCreatedAt($createAt)
                ->save();

            $this->messageManager->addSuccessMessage(__('You have created success!.'));

            return $this->_redirect('*/*/');
        } catch (LocalizedException $exception) {
            $this->messageManager->addExceptionMessage($exception);
        } catch (\Exception $exception) {
            $this->messageManager->addExceptionMessage(
                $exception,
                __('We can\'t update the email right now. Please review the log and try again.')
            );
        }

        return $this->_redirect('*/*/');
    }

}
