<?php
class Contestio_Connect_Model_Observer
{
    /**
     * Replace every canonical registered by Magento/theme code with the single
     * canonical returned by Contestio, only on the Contestio storefront route.
     */
    public function replaceContestioCanonical(Varien_Event_Observer $observer)
    {
        $block = $observer->getEvent()->getBlock();
        if (!$block || $block->getNameInLayout() !== 'head') {
            return $this;
        }

        $request = Mage::app()->getRequest();
        if (!$request || $request->getFullActionName() !== 'contestio_connect_index_index') {
            return $this;
        }

        $canonical = Mage::helper('contestio_connect/meta')->getCanonical();
        if (empty($canonical)) {
            return $this;
        }

        $items = $block->getData('items');
        if (is_array($items)) {
            foreach ($items as $key => $item) {
                if (
                    $this->isCanonicalLinkItem($key, $item)
                    && is_array($item)
                    && isset($item['name'])
                ) {
                    $block->removeItem('link_rel', $item['name']);
                }
            }
        }

        $block->addLinkRel('canonical', $canonical);
        return $this;
    }

    protected function isCanonicalLinkItem($key, $item)
    {
        $type = is_array($item) && isset($item['type']) ? strtolower($item['type']) : '';
        $isLinkRel = $type === 'link_rel' || strpos((string) $key, 'link_rel/') === 0;
        if (!$isLinkRel) {
            return false;
        }

        $params = is_array($item) && isset($item['params']) ? $item['params'] : '';
        if (!is_string($params)) {
            return false;
        }

        return preg_match(
            "/(?:^|\\s)rel\\s*=\\s*(?:\"canonical\"|'canonical'|canonical)(?:\\s|$)/i",
            $params
        ) === 1;
    }

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
