<?php
class Contestio_Connect_Model_Observer
{
    public function onAfterOrderSave(Varien_Event_Observer $observer)
    {        
        $order = $observer->getEvent()->getOrder();
        
        // Check if order exists and is processing
        if ($order && $order->getId() && $order->getState() == Mage_Sales_Model_Order::STATE_PROCESSING) {
            // Avoid multiple calls if order is already processed
            if (!$order->getData('contestio_notified')) {
                $this->notifyApi($order);
                $order->setData('contestio_notified', 1)->save();
            }
        }
    }

    private function notifyApi($order)
    {
        try {
            $helper = Mage::helper('contestio_connect/api');
            $userAgent = Mage::helper('core/http')->getHttpUserAgent();
            $storeOrderInContestio = false;

            // Get user id
            $userId = $order->getCustomerId();

            // Check if user is from the club
            $checkUser = $helper->callApi($userAgent, 'v1/users/me', "GET", null, $userId); // Send user id to check if user is from the club

            if ($checkUser && isset($checkUser['storeOrder']) && $checkUser['storeOrder'] === true) {
                $orderData = array(
                    'order_id' => $order->getIncrementId(),
                    'amount' => $order->getGrandTotal(),
                    'currency' => $order->getOrderCurrencyCode(),
                );

                // Send order to Contestio
                $helper->callApi($userAgent, 'v1/users/final/new-order', "POST", $orderData, $userId); // Send user id to send order
            }
        } catch (Exception $e) {
            Mage::log("Contestio Observer Exception: " . $e->getMessage(), null, 'contestio.log');
        }
    }
}