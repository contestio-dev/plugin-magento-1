<?php
class Contestio_Connect_Block_NextJs extends Mage_Core_Block_Template
{
    public function getNextJsAppUrl()
    {
        return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN) . 'frontend/base/default/nextjs-app/';
    }

    public function getNextJsChunkHash()
    {
        $staticPath = Mage::getBaseDir('skin') . '/frontend/base/default/nextjs-app/_next/static/';
        $directories = glob($staticPath . '*', GLOB_ONLYDIR);
        foreach ($directories as $dir) {
            if (file_exists($dir . '/_buildManifest.js')) {
                return basename($dir);
            }
        }
        return '';
    }

    public function getNextJsMainHash()
    {
        $mainJsPath = Mage::getBaseDir('skin') . '/frontend/base/default/nextjs-app/_next/static/chunks/';
        $mainJsFiles = glob($mainJsPath . 'main-*.js');
        if (!empty($mainJsFiles)) {
            return pathinfo(basename($mainJsFiles[0]), PATHINFO_FILENAME);
        }
        return '';
    }

    public function getMainAppJsFile()
    {
        $path = Mage::getBaseDir('skin') . '/frontend/base/default/nextjs-app/_next/static/chunks/';
        $files = glob($path . 'main-app-*.js');
        return !empty($files) ? basename($files[0]) : '';
    }

    public function getPageJsFile()
    {
        $path = Mage::getBaseDir('skin') . '/frontend/base/default/nextjs-app/_next/static/chunks/app/';
        $files = glob($path . 'page-*.js');
        return !empty($files) ? basename($files[0]) : '';
    }
}