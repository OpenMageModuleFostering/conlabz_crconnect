<?php
class Conlabz_CrConnect_SearchController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        $systemPassword = Mage::helper('crconnect')->getCleverReachFeedPassword();

        $store = $this->getRequest()->getParam('store');
        $password = $this->getRequest()->getParam('password');

        if ($systemPassword && $password != $systemPassword) {
            $this->getResponse()
                ->setHeader('HTTP/1.1','403 Forbidden')
                ->setBody('You have no permissions to view this page')
                ->sendResponse();
            exit;
        }

        // make sure to set the correct store, so that correct categories and products are used
        Mage::app()->setCurrentStore($store);

        $search = Mage::getModel('crconnect/search');
        $action = $this->getRequest()->getParam('get', 'filter');
        $returnData = array();
        switch ($action) {
            case 'filter':
                $returnData = $search->getFilter();
                break;
            case 'search':
                $category = $this->getRequest()->getParam('category', false);
                $product = $this->getRequest()->getParam('product', '');
                $returnData = $search->getSearch($category, $product, $store);
                break;
        }

        $this->getResponse()
            ->setHeader('Content-Type', 'application/json')
            ->setBody(Mage::helper('core')->jsonEncode($returnData));
    }
}
