<?php
class Contestio_Connect_Model_Observer
{
    public function onAfterOrderSave(Varien_Event_Observer $observer)
    {        
        $order = $observer->getEvent()->getOrder();

        $this->notifyApi($order);
        
        // // Check if order exists and is processing
        // if ($order && $order->getId() && $order->getState() == Mage_Sales_Model_Order::STATE_PROCESSING) {
        //     // Avoid multiple calls if order is already processed
        //     if (!$order->getData('contestio_notified')) {
        //         $this->notifyApi($order);
        //         $order->setData('contestio_notified', 1)->save();
        //     }
        // }
    }

    private function notifyApi($order)
    {
        try {
            $helper = Mage::helper('contestio_connect/api');
            $userAgent = Mage::helper('core/http')->getHttpUserAgent();

            // Get user id and check if we store order
            $userId = $order->getCustomerId();
            $userEmail = $order->getCustomerEmail();
            $checkUser = $helper->callApi($userAgent, 'v1/users/me', "GET", null, $userId, $userEmail); // Send user id to check if user is from the club

            // If storeOrder === true, send order to Contestio
            if ($checkUser && isset($checkUser['storeOrder']) && $checkUser['storeOrder'] === true) {
                $orderData = array(
                    'order_id' => $order->getIncrementId(),
                    'amount' => $order->getGrandTotal(),
                    'currency' => $order->getOrderCurrencyCode(),
                );

                // Send order to Contestio
                $helper->callApi($userAgent, 'v1/users/final/new-order', "POST", $orderData, $userId, $userEmail); // Send user id to send order
            }
        } catch (Exception $e) {
            // Mage::log("Contestio Observer Exception: " . $e->getMessage(), null, 'contestio.log');
        }
    }
}