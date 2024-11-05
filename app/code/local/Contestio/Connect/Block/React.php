<?php
class Contestio_Connect_Block_React extends Mage_Core_Block_Template
{
    public function getReactAppUrl()
    {
        return "https://d36h2ac42341sx.cloudfront.net";
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
}