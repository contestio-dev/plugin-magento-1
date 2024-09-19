<?php
class Contestio_Connect_Block_React extends Mage_Core_Block_Template
{
    public function getReactAppUrl()
    {
        return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN) . 'frontend/base/default/reactjs-app/';
    }

    public function getMainCssFile()
    {
        $cssPath = Mage::getBaseDir('skin') . '/frontend/base/default/react-app/static/css/';
        $cssFiles = glob($cssPath . 'main.*.css');
        return !empty($cssFiles) ? basename($cssFiles[0]) : '';
    }

    public function getMainJsFile()
    {
        $jsPath = Mage::getBaseDir('skin') . '/frontend/base/default/react-app/static/js/';
        $jsFiles = glob($jsPath . 'main.*.js');
        return !empty($jsFiles) ? basename($jsFiles[0]) : '';
    }

    public function getChunkJsFile()
    {
        $jsPath = Mage::getBaseDir('skin') . '/frontend/base/default/react-app/static/js/';
        $jsFiles = glob($jsPath . '*.chunk.js');
        return !empty($jsFiles) ? basename($jsFiles[0]) : '';
    }
}