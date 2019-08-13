<?php

class Conlabz_CrConnect_Model_Subscriber extends Mage_Core_Model_Abstract
{
    /**
     *  Subscribe cusotmer
     */
    public function subscribe($customer = false, $groupId = 0)
    {
        return Mage::getModel("crconnect/api")->subscribe($customer, $groupId);
    }

    /**
     * Send activation email for customer
     */
    public function formsSendActivationMail($customer = false, $groupId = 0)
    {
        return Mage::getModel("crconnect/api")->formsSendActivationMail($customer, $groupId);
    }

    /**
     *  Subscribe cusotmer
     */
    public function unsubscribe($email = false, $groupId = 0)
    {
        return Mage::getModel("crconnect/api")->unsubscribe($email, $groupId);
    }

    public function updateCustomer($customer, $groupId = 0)
    {
        return Mage::getModel("crconnect/api")->update($customer, $groupId);
    }

    public function updateSubscription(Mage_Core_Controller_Request_Http $request)
    {
        try {
            $email = Mage::getSingleton('customer/session')->getCustomer()->getEmail();
            $subscriber = Mage::getModel("newsletter/subscriber")->loadByEmail($email);
            $subscriber->setEmail(Mage::getSingleton('customer/session')->getCustomer()->getEmail());

            if ((boolean) $request->getParam('is_subscribed', false)) {
                if (!$subscriber->isSubscribed()) {
                    $status = Mage::getModel("newsletter/subscriber")->subscribe($email);
                    if (Mage::helper("crconnect")->isDoubleOptInEnabled()) {
                        Mage::getSingleton('core/session')->addSuccess(Mage::helper('crconnect')->__('Confirmation request has been sent.'));
                    } else {
                        Mage::getSingleton('core/session')->addSuccess(Mage::helper('crconnect')->__('Thank you for your subscription.'));
                    }

                }
            } else {
                if ($subscriber->isSubscribed()) {
                    $status = Mage::getModel("crconnect/subscriber")->unsubscribe($email);
                    Mage::getSingleton('core/session')->addSuccess(Mage::helper('crconnect')->__('The subscription has been removed.'));
                }
            }

            $groupId = Mage::getSingleton('customer/session')->getCustomerGroupId();
            if ($groupId > 1) {
                if ((boolean) $request->getParam('is_gsubscribed', false)) {
                    if (!$subscriber->isSubscribed($groupId)) {
                        $status = Mage::getModel("newsletter/subscriber")->subscribe(Mage::getSingleton('customer/session')->getCustomer()->getEmail(), $groupId);
                        if (Mage::helper("crconnect")->isDoubleOptInEnabled()) {
                            Mage::getSingleton('core/session')->addSuccess(Mage::helper('crconnect')->__('Confirmation request has been sent.'));
                        } else {
                            Mage::getSingleton('core/session')->addSuccess(Mage::helper('crconnect')->__('Thank you for your subscription.'));
                        }
                    }
                } else {
                    if ($subscriber->isSubscribed($groupId)) {
                        $status = Mage::getModel("crconnect/subscriber")->unsubscribe($email, $groupId);
                        Mage::getSingleton('core/session')->addSuccess(Mage::helper('crconnect')->__('The subscription has been removed.'));
                    }
                }
            }
        } catch (Exception $e) {
            Mage::getSingleton('core/session')->addError($e->getMessage());
            Mage::getSingleton('core/session')->addError(Mage::helper('crconnect')->__('An error occurred while saving your subscription.'));
        }
    }
}
