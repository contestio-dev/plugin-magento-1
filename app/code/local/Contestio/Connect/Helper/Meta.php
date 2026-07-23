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
        $fallbackCanonical = $this->_buildFallbackCanonical($currentUrl);

        $metaData = array(
            'title' => null,
            'image' => null,
            'siteName' => null,
            'description' => null,
            'version' => null,
            'currentUrl' => $currentUrl,
            'canonicalUrl' => $fallbackCanonical,
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

        $defaultHelper = Mage::helper('contestio_connect');
        if (empty($metaData['title'])) {
            $metaData['title'] = $defaultHelper
                ? $defaultHelper->__('Contestio customer community')
                : 'Contestio customer community';
        }

        if (empty($metaData['description'])) {
            $metaData['description'] = $defaultHelper
                ? $defaultHelper->__('Discover contests, surveys and community news powered by Contestio.')
                : 'Discover contests, surveys and community news powered by Contestio.';
        }

        if (empty($metaData['canonicalUrl'])) {
            $metaData['canonicalUrl'] = $fallbackCanonical;
        }

        return $metaData;
    }

    /**
     * Build a stable fallback canonical without crawler-controlled parameters.
     */
    protected function _buildFallbackCanonical($currentUrl)
    {
        $parts = parse_url($currentUrl);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $canonical = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $canonical .= ':' . $parts['port'];
        }

        $canonical .= isset($parts['path']) && $parts['path'] !== '' ? $parts['path'] : '/';
        return $canonical;
    }
}
