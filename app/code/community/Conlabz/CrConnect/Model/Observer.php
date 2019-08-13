<?php
class Conlabz_CrConnect_Model_Observer
{
    // 14 days
    const SESSION_LIFE = 1209600;

    public function customerSaveAfter($observer)
    {
        if (!Mage::registry('cr_aftersave_called')) {
            Mage::register('cr_aftersave_called', true);
        } else {
            return true;
        }

        $controller = Mage::app()->getRequest()->getControllerName();
        $action = Mage::app()->getRequest()->getActionName();

        $customer = $observer->getCustomer();

        $email = $customer->getEmail();

        $subscriber = Mage::getModel("newsletter/subscriber")->loadByEmail($email);
        $subscriber->setEmail($email);
        if (Mage::app()->getStore()->isAdmin()) {
            return true;
        }

        $subscriptionCheckbox1 = Mage::app()->getRequest()->getParam('subscription');
        $subscriptionCheckbox2 = Mage::app()->getRequest()->getParam('is_subscribed');

        if ($subscriptionCheckbox1 !== null || $subscriptionCheckbox2 !== null) {
            if (!$subscriber->isSubscribed()) {
                $status = Mage::getModel("newsletter/subscriber")->subscribe($email);
                if (Mage::helper("crconnect")->isDoubleOptInEnabled()) {
                    Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper("core")->__('Confirmation request has been sent.'));
                } else {
                    Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper("core")->__('Thank you for your subscription.'));
                }
            }

        }

        $gsubscription = Mage::app()->getRequest()->getParam('gsubscription');
        $groupId = $customer->getGroupId();

        if ($gsubscription !== null) {
            if (!$subscriber->isSubscribed($groupId)) {
                $status = Mage::getModel("newsletter/subscriber")->subscribe($email, $groupId);
                if (Mage::helper("crconnect")->isDoubleOptInEnabled()) {
                    Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper("core")->__('Confirmation request has been sent.'));
                } else {
                    Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper("core")->__('Thank you for your subscription.'));
                }
            }
        }

        return true;
    }

    public function trackingCodesSet()
    {
        $mailingId = Mage::getSingleton('core/app')->getRequest()->getParam('crmailing');

        $cookie = Mage::getSingleton('core/cookie');
        if ($mailingId) {
            $cookie->set('crmailing', $mailingId, time()+self::SESSION_LIFE, '/');
        }

        $customerId = Mage::getSingleton('core/app')->getRequest()->getParam('crcustomer');
        $cookie = Mage::getSingleton('core/cookie');
        if ($customerId) {
            $cookie->set('crcustomer', $customerId, time()+self::SESSION_LIFE, '/');
        }
    }

    public function checkoutSuccess()
    {
        if (!Mage::registry('order_track_start')) {
            Mage::register('order_track_start', true);
        } else {
            return true;
        }

        if (Mage::helper("crconnect")->isTrackingEnabled()) {
            $lastOrderId = Mage::getSingleton('checkout/session')->getLastOrderId();
        } else {
            Mage::helper("crconnect")->log("CleverReach_CrConnect: order sycing deactivated");
            return false;
        }

        $order = Mage::getModel('sales/order')->load($lastOrderId);
        $email = $order->getCustomerEmail();

        if (Mage::helper("crconnect")->isTrackingEnabled()) {
            $items = $order->getAllItems();
            if ($items) {
                foreach ($items as $item) {
                    $tmpItem = array();
                    $tmpItem["order_id"] = $lastOrderId;
                    $tmpItem["product"] = $item->getName();
                    $tmpItem["product_id"] = $item->getProductId();
                    $tmpItem["price"] = round($item->getPrice(), 2);
                    $tmpItem["quantity"] = (integer)$item->getQtyOrdered();
                    $tmpItem["purchase_date"] = time();
                    $tmpItem["currency"] = $order->getData('order_currency_code');
                    $tmpItem["source"] = "MAGENTO Order";

                    $cookie = Mage::getSingleton('core/cookie');
                    if ($cookie->get('crmailing')) {
                        $tmpItem['mailings_id'] = $cookie->get('crmailing');
                    }
                    Mage::getModel("crconnect/api")->receiverAddOrder($email, $tmpItem);
                }
            }
        }
    }

    public function orderPlacedAfter($observer)
    {
        try {
            $order = $observer->getOrder();
            if (Mage::helper("crconnect")->isM2eExclude()) {
                $shippingMethod = $order->getShippingMethod();
                Mage::helper("crconnect")->log("M2E sync disabled -> shipping method: " . $shippingMethod);
                if (in_array($shippingMethod, Mage::helper("crconnect")->getM2eShippingMethods())) {
                    Mage::helper("crconnect")->log("Its M2E order -> Skip");
                    return true;
                }
            }

            $customer = Mage::getModel('customer/customer')->load($order->getCustomerId());
            $email = $order->getCustomerEmail();

            if (Mage::helper("crconnect")->isForceSyncEnabled()) {
                Mage::helper("crconnect")->log("Force sync orders enabled");

                if ($customer->getEmail()) {
                    Mage::helper("crconnect")->log("Force sync orders for logged in customer");
                    $crReceiver = Mage::helper('crconnect')->prepareUserdata($customer);
                    $result = Mage::getModel("crconnect/api")->receiverAdd($crReceiver, $customer->getGroupId());
                    Mage::helper("crconnect")->log($result);
                } else {
                    Mage::helper("crconnect")->log("Force sync orders for guest customer");
                    $billingAddress = $order->getBillingAddress();
                    if ($billingAddress) {
                        Mage::helper("crconnect")->log("Prepare info based on billing address");
                        $crReceiver = array (
                            'email' => $email,
                            'source' => 'MAGENTO',
                            'attributes' => array(
                                array("key" => "firstname", "value" => $billingAddress->getFirstname()),
                                array("key" => "lastname", "value" => $billingAddress->getLastname()),
                                array("key" => "street", "value" => $billingAddress->getStreet()),
                                array("key" => "zip", "value" => $billingAddress->getPostcode()),
                                array("key" => "city", "value" => $billingAddress->getCity()),
                                array("key" => "country", "value" => $billingAddress->getCountryId()),
                                array("key" => "salutation", "value" => $billingAddress->getPrefix()),
                                array("key" => "title", "value" => $billingAddress->getSuffix()),
                                array("key" => "company", "value" => $billingAddress->getCompany())
                            )
                        );

                        $cookie = Mage::getSingleton('core/cookie');
                        if ($cookie->get('crmailing')) {
                            $crReceiver['orders'][0]['mailings_id'] = $cookie->get('crmailing');
                        }
                        Mage::helper("crconnect")->log($crReceiver);

                        $result = Mage::getModel("crconnect/api")->receiverAdd($crReceiver);
                        Mage::helper("crconnect")->log($result);
                    }
                }
            }
        } catch (Exception $ex) {
            Mage::helper("crconnect")->log("order_save_after Exception");
            Mage::helper("crconnect")->log($ex->getMessage());
        }
        return true;
    }

    public function customerDeleted($observer)
    {
        $event = $observer->getEvent();
        $customer = $event->getCustomer();
        $email = $customer->getEmail();
        $groupId = $customer->getGroupId();

        Mage::getModel("crconnect/subscriber")->unsubscribe($email);
        Mage::getModel("crconnect/subscriber")->unsubscribe($email, $groupId);
    }

    public function configSave()
    {
        $currentSection = Mage::app()->getRequest()->getParam('section');
        if (in_array($currentSection, array('newsletter', 'crroot')) &&
            Mage::helper("crconnect")->isDoubleOptInEnabled()
        ) {
            $groupsIds = Mage::helper("crconnect")->getGroupsIds();
            $formsIds = Mage::helper("crconnect")->getFormsIds();
            $allow = true;
            foreach ($groupsIds as $groupsId) {
                if (!$groupsId) {
                    $allow = false;
                }
            }
            foreach ($formsIds as $formsId) {
                if (!$formsId) {
                    $allow = false;
                }
            }
            $formId = Mage::helper("crconnect")->getDefaultFormId();
            if (!$formId) {
                $allow = false;
            }
            if (!$allow) {
                Mage::getSingleton('adminhtml/session')->addError(Mage::helper('catalog')->__('Double Opt-In is enabled, please select form(s) and group(s) for your customer groups.'));
            }
        }
    }

    public function initCleverReach()
    {
        $session = Mage::getSingleton('adminhtml/session');

        $setupResult = Mage::getModel('crconnect/api')->setupDefaultCleverReachList();
        if (!$setupResult) {
            $session->addError("Could not connect to or receive any data from CleverReach. Please check your API key, selected group(s) and form(s).");
        }
    }
}
