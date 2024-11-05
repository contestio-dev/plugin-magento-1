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
        try {
            $helper = Mage::helper('contestio_connect/api');
            $userAgent = Mage::helper('core/http')->getHttpUserAgent();

            // Check if user is from Contestio
            $checkUser = $helper->callApi($userAgent, 'v1/users/final/me', "GET");

            if ($checkUser === false) {
                Mage::log("Contestio: Impossible de vérifier l'utilisateur", null, 'contestio.log');
                return;
            }

            $orderData = array(
                'order_id' => $order->getIncrementId(),
                'amount' => $order->getGrandTotal(),
                'currency' => $order->getOrderCurrencyCode(),
            );

            // Send order to Contestio
            $result = $helper->callApi($userAgent, 'v1/users/final/new-order', "POST", $orderData);
            
            if ($result === false) {
                Mage::log("Contestio: Échec de l'envoi de la commande " . $order->getIncrementId(), null, 'contestio.log');
            }
        } catch (Exception $e) {
            Mage::log("Contestio Observer Exception: " . $e->getMessage(), null, 'contestio.log');
        }
    }
}