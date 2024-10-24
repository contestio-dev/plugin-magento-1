<?php
class Contestio_Connect_Model_Observer
{
    public function orderPlaced(Varien_Event_Observer $observer)
    {        
        $order = $observer->getEvent()->getOrder();
    
        if ($order && $order->getId()) {
            $this->notifyApi($order);
        }
    }

    private function notifyApi($order)
    {
        $helper = Mage::helper('contestio_connect/api');
        $userAgent = Mage::helper('core/http')->getHttpUserAgent();

        // Check if user is from Contestio
        $checkUser = $helper->callApi($userAgent, 'v1/users/final/me', "GET");

        if (!$checkUser) {
            return;
        }

        $orderData = array(
            'order_id' => $order->getIncrementId(),
            'amount' => $order->getGrandTotal(),
            'currency' => $order->getOrderCurrencyCode(),
        );

        // Send order to Contestio
        $helper->callApi($userAgent, 'v1/users/final/new-order', "POST", $orderData);
    }
}