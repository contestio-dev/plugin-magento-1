<?php
class Contestio_Connect_Helper_Meta extends Mage_Core_Helper_Abstract
{
    const REGISTRY_KEY = 'contestio_meta_data';

    public function getMetaData()
    {
        $metaData = Mage::registry(self::REGISTRY_KEY);
        if ($metaData !== null) {
            return $metaData;
        }

        $metaData = $this->_buildMetaData();
        Mage::register(self::REGISTRY_KEY, $metaData);
        return $metaData;
    }

    public function getTitle()
    {
        $meta = $this->getMetaData();
        return isset($meta['title']) ? $meta['title'] : null;
    }

    public function getDescription()
    {
        $meta = $this->getMetaData();
        return isset($meta['description']) ? $meta['description'] : null;
    }

    public function getCanonical()
    {
        $meta = $this->getMetaData();
        return isset($meta['canonicalUrl']) ? $meta['canonicalUrl'] : null;
    }

    protected function _buildMetaData()
    {
        $currentUrl = Mage::helper('core/url')->getCurrentUrl();
        $userAgent = Mage::app()->getRequest()->getHeader('User-Agent');

        $metaData = array(
            'title' => null,
            'image' => null,
            'siteName' => null,
            'description' => $currentUrl,
            'version' => null,
            'currentUrl' => $currentUrl,
            'canonicalUrl' => $currentUrl,
        );

        try {
            $composerVersion = Mage::getConfig()->getModuleConfig('Contestio_Connect')->version;
            if ($composerVersion) {
                $metaData['version'] = $composerVersion;
            }

            $helper = Mage::helper('contestio_connect/api');
            $endpoint = 'v1/org/meta-tags/' . urlencode($currentUrl);
            $response = $helper->callApi($userAgent, $endpoint, 'GET', null);

            if (is_array($response)) {
                $metaData = array_merge($metaData, $response);
            }
        } catch (Exception $e) {
            // Silent fail – keep default metadata
        }

        return $metaData;
    }
}
