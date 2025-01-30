<?php
class Contestio_Connect_Block_React extends Mage_Core_Block_Template
{
    public function getReactAppUrl()
    {
        $baseUrl = Mage::getStoreConfig('contestio_connect/api_settings_advanced/base_url_react');
        return $baseUrl ? $baseUrl : "https://react.contestio.fr";
    }

    public function getMetaTags()
    {
        // Get current url
        $currentUrl = Mage::helper('core/url')->getCurrentUrl();
        $userAgent = $this->getRequest()->getHeader('User-Agent');

        // Default meta data
        $metaData = array(
            'title' => null,
            'image' => null,
            'siteName' => null,
            'description' => $currentUrl,
            'version' => null,
            'currentUrl' => $currentUrl,
        );

        try {
            // Get composer.json version
            $composerJson = Mage::getConfig()->getModuleConfig('Contestio_Connect')->version;
            if ($composerJson) {
                $metaData['version'] = $composerJson;
            }

            // Utiliser le helper API pour faire l'appel
            $helper = Mage::helper('contestio_connect/api');
            $endpoint = 'v1/org/meta-tags/' . urlencode($currentUrl);
            $method = 'GET';
    
            $response = $helper->callApi($userAgent, $endpoint, $method, null);
    
            if ($response && is_array($response)) {
                $metaData = array_merge($metaData, $response);
            }
        } catch (Exception $e) {
            // echo $e->getMessage();
        }

        return $metaData;
    }

    public function getIframeUrl()
    {
        $baseUrl = Mage::getStoreConfig('contestio_connect/api_settings_advanced/base_url_iframe');
        return $baseUrl ? $baseUrl : "https://plugin.contestio.fr";
    }

    public function getQueryParams()
    {
        // Get shop and access token
        $shop = Mage::getStoreConfig('contestio_connect/api_settings/api_key');
        $accessToken = Mage::getStoreConfig('contestio_connect/api_settings/access_token');
        
        // Get customer data
        $customer = Mage::getSingleton('customer/session')->getCustomer();
        $customerId = $customer->getId();
        $customerEmail = $customer->getEmail();

        $params = "";

        // Get l parameter from the current url
        $l = $this->getRequest()->getParam('l');
        if ($l && strlen($l) > 0 && $l !== "/") {
            if ($l[0] !== "/") {
                $params .= "/";
            }
            $params .= $l;
        }

        $params .= "?";

        if ($shop) {
            $params .= "shop=" . urlencode($shop);
        }

        if ($customerId && $customerEmail && $accessToken) {
            // Hash customer id and email with access token
            $helper = Mage::helper('contestio_connect/api');
            $params .= "&customer_id=" . urlencode($helper->encryptDataBase64($customerId, $accessToken));
            $params .= "&customer_email=" . urlencode($helper->encryptDataBase64($customerEmail, $accessToken));
        }

        // Get current query params
        $currentQueryParams = $this->getRequest()->getParams();
        if ($currentQueryParams) {
            foreach ($currentQueryParams as $key => $value) {
                if ($key !== 'l' && $key !== 'shop' && $key !== 'customer_id' && $key !== 'customer_email') {
                    $params .= "&" . urlencode($key) . "=" . urlencode($value);
                }
            }
        }

        return $params === "?" ? "" : $params;
    }
}