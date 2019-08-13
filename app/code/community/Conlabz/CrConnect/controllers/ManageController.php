<?php

include "Mage/Newsletter/controllers/ManageController.php";

class Conlabz_CrConnect_ManageController extends Mage_Newsletter_ManageController
{
    public function saveAction()
    {
        if (!$this->_validateFormKey()) {
            return $this->_redirect('customer/account');
        }
        //retrieve subscriber and update subscription
        /** @var $subscriber  Conlabz_CrConnect_Model_Subscriber */
        $subscriber = Mage::getModel("crconnect/subscriber");
        $request = $this->getRequest();
        $subscriber->updateSubscription($request);
        $this->_redirect('customer/account');
    }
}
