<?php
class Conlabz_CrConnect_Model_Observer{
    
    // 14 days
    const SESSION_LIFE = 1209600;
    
    private $_allowedControllers = array("account");
    private $_allowedActions = array("createpost");
    
    public function customerSaveAfter($observer){
        
        if (!Mage::registry('cr_aftersave_called')){
            Mage::register('cr_aftersave_called', true);
        }else{
            return true;
        }
        
        $controller = Mage::app()->getRequest()->getControllerName();
        $action = Mage::app()->getRequest()->getActionName();
        
        $customer = $observer->getCustomer();
        
        $email = $customer->getEmail();    
        
        Mage::getModel("crconnect/subscriber")->updateCustomer($customer);
        
        $subscriber = Mage::getModel("newsletter/subscriber")->loadByEmail($email);
        $subscriber->setEmail($email);
        if (Mage::app()->getStore()->isAdmin()){
            return true;
//            $website = Mage::getModel('core/website')->load($customer->getWebsiteId())->getCode();
//            Mage::getSingleton("adminhtml/session")->setCrCustomerWebsite($website);
        }
        
        $subscriptionCheckbox1 = Mage::app()->getRequest()->getParam('subscription');
        $subscriptionCheckbox2 = Mage::app()->getRequest()->getParam('is_subscribed');
        
        if ($subscriptionCheckbox1 !== null || $subscriptionCheckbox2 !== null){

            if (!$subscriber->isSubscribed()) {
                $status = Mage::getModel("newsletter/subscriber")->subscribe($email);
                if (Mage::helper("crconnect")->isDoubleOptInEnabled()) {
                    Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper("core")->__('Confirmation request has been sent.'));
                } else {
                    Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper("core")->__('Thank you for your subscription.'));
                }
            }

        }else{
           
           if (in_array($controller, $this->_allowedControllers) && in_array($action, $this->_allowedActions)){
                if ($subscriber->isSubscribed()) {
                     $status = Mage::getModel("crconnect/subscriber")->unsubscribe($email);
                     Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper("core")->__('The subscription has been removed.'));
                }
           }
            
        }
        
        $gsubscription = Mage::app()->getRequest()->getParam('gsubscription');
        $groupId = $customer->getGroupId();
            
        if ($gsubscription !== null){
            if (!$subscriber->isSubscribed($groupId)) {
                $status = Mage::getModel("newsletter/subscriber")->subscribe($email, $groupId);
                if (Mage::helper("crconnect")->isDoubleOptInEnabled()) {
                    Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper("core")->__('Confirmation request has been sent.'));
                } else {
                    Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper("core")->__('Thank you for your subscription.'));
                }
            }    
        }else{
            
            if (in_array($controller, $this->_allowedControllers) && in_array($action, $this->_allowedActions)){
                if ($subscriber->isSubscribed($groupId)) {
                    $status = Mage::getModel("crconnect/subscriber")->unsubscribe($email, $groupId);
                    Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper("core")->__('The subscription has been removed.'));
                }
            }
            
        }
        
//        Mage::getSingleton("adminhtml/session")->unsCrCustomerWebsite();
                
        return true;
        
    }
    
    public function trackingCodesSet($observer){
        
        $mailingId = Mage::getSingleton('core/app')->getRequest()->getParam('crmailing');
        
        $cookie = Mage::getSingleton('core/cookie');
        if ($mailingId){
            $cookie->set('crmailing', $mailingId ,time()+self::SESSION_LIFE,'/');
        }
        
    	$customerId = Mage::getSingleton('core/app')->getRequest()->getParam('crcustomer');
        $cookie = Mage::getSingleton('core/cookie');
        if ($customerId){
            $cookie->set('crcustomer', $customerId ,time()+self::SESSION_LIFE,'/');
        }
        
    }
    public function checkoutSuccess($observer){
        
        $event = $observer->getEvent();
        
        $apiKey = trim(Mage::getStoreConfig('crroot/crconnect/api_key'));
        $listID = trim(Mage::getStoreConfig('crroot/crconnect/list_id'));
        
        if(Mage::helper("crconnect")->isTrackingEnabled())
            $lastOrderId = Mage::getSingleton('checkout/session')->getLastOrderId();
        else{
            $lastOrderId = false;
            Mage::helper("crconnect")->log("CleverReach_CrConnect: order sycing deactivated");
        }
        
        $order = Mage::getModel('sales/order')->load($lastOrderId);
        $customer = Mage::getModel('customer/customer')->load($order->getCustomerId());
        $email = $order->getCustomerEmail();
        
        if(Mage::helper("crconnect")->isTrackingEnabled()){
        	
            $items = $order->getAllItems();
            if ($items){
                foreach ($items as $item){

                    $tmpItem = array();
                    $tmpItem["order_id"] = $lastOrderId;
                    $tmpItem["product"] = $item->getName();
                    $tmpItem["product_id"] = $item->getProductId();
                    $tmpItem["price"] = round($item->getPrice(),2);
                    $tmpItem["quantity"] = (integer)$item->getQtyOrdered();
                    $tmpItem["purchase_date"] = time();
                    $tmpItem["currency"] = $order->getData('order_currency_code');
                    $tmpItem["source"] = "MAGENTO Order";

                    $cookie = Mage::getSingleton('core/cookie');
                    if ($cookie->get('crmailing')){
                        $tmpItem['mailings_id'] = $cookie->get('crmailing');
                    }

                    Mage::getModel("crconnect/api")->receiverAddOrder($email, $tmpItem);
                    
                }  
            }
            
        }   	
    }
    
    public function customerDeleted($observer){
        
        $event = $observer->getEvent();
        $customer = $event->getCustomer();
        $email = $customer->getEmail();
        $groupId = $customer->getGroupId();
		
        Mage::getModel("crconnect/subscriber")->unsubscribe($email);
        Mage::getModel("crconnect/subscriber")->unsubscribe($email, $groupId);
        
    }
    public function configSave(){
	
        $postValues = Mage::app()->getRequest()->getPost();
	if (Mage::app()->getRequest()->getParam('section') == "newsletter" || Mage::app()->getRequest()->getParam('section') == "crroot"){
            
            $store = Mage::app()->getRequest()->getParam('store');
			
            if (Mage::helper("crconnect")->isDoubleOptInEnabled()){
			
                $groupsIds = Mage::helper("crconnect")->getGroupsIds();
                $formsIds = Mage::helper("crconnect")->getFormsIds();
                
                $allow = true;
                foreach ($groupsIds as $groupsId){
                    if (!$groupsId){
                        $allow = false;
                    }
                }
                
                foreach ($formsIds as $formsId){
                    if (!$formsId){
                        $allow = false;
                    }
                }
                
                $formId = Mage::helper("crconnect")->getDefaultFormId();
                if (!$formId){
                    $allow = false;
                }
                
                if (!$allow){
                    Mage::getSingleton('adminhtml/session')->addError(Mage::helper('catalog')->__('Double Opt-In enabled, please select Form(s) and Group(s) for your Customer Groups'));
                }

            }
			
        }
	
    }
    public function initCleverReach(){
        
        $session = Mage::getSingleton('adminhtml/session');
		
        $setupResult = Mage::getModel('crconnect/api')->setupDefaultClereReachList();
        if(!$setupResult){
            $session->addError("Could not connect to CleverReach. Please chech your API keys.");
        }else if($setupResult->status=="ERROR" && $setupResult->statuscode!=50){
            $session->addError("Cleverreach connection Error: ".$data->message);
        }
        
        
    }
}