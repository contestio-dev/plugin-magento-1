<?php

class Contestio_Connect_ProductsController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        try {
            // Vérifier le header Authorization
            $authHeader = $this->getRequest()->getHeader('Authorization');
            if (!$authHeader) {
                throw new Exception('Authorization header is missing');
            }

            // Vérifier le token d'accès
            $configToken = Mage::getStoreConfig('contestio_connect/api_settings/access_token');
            $providedToken = str_replace('Bearer ', '', $authHeader);

            if ($providedToken !== $configToken) {
                throw new Exception('Invalid access token');
            }

            // Paramètres de recherche
            $search = $this->getRequest()->getParam('search');
            $pageSize = (int) ($this->getRequest()->getParam('limit') ?: 20);
            $currentPage = (int) ($this->getRequest()->getParam('page') ?: 1);

            // Limiter pour éviter les abus
            if ($pageSize > 100) {
                $pageSize = 100;
            }

            // Construire la collection de produits
            $collection = Mage::getModel('catalog/product')->getCollection()
                ->addAttributeToSelect(array('name', 'price', 'small_image', 'url_key'))
                ->addAttributeToFilter('status', 1) // Actif
                ->addAttributeToFilter('visibility', array('in' => array(2, 3, 4))) // Visible
                ->setPageSize($pageSize)
                ->setCurPage($currentPage);

            // Filtrer par nom si recherche
            if ($search) {
                $collection->addAttributeToFilter('name', array('like' => '%' . $search . '%'));
            }

            // Récupérer le nombre total
            $totalCount = $collection->getSize();

            // Formater les produits
            $products = array();
            foreach ($collection as $product) {
                $imageUrl = null;
                try {
                    $imageUrl = Mage::helper('catalog/image')->init($product, 'small_image')->resize(200)->__toString();
                } catch (Exception $e) {
                    // Image non disponible
                }

                $products[] = array(
                    'id' => $product->getId(),
                    'sku' => $product->getSku(),
                    'name' => $product->getName(),
                    'price' => $product->getPrice(),
                    'imageUrl' => $imageUrl,
                    'url' => $product->getProductUrl(),
                );
            }

            // Préparer la réponse
            $response = array(
                'success' => true,
                'data' => array(
                    'products' => $products,
                    'totalCount' => $totalCount,
                    'pageSize' => $pageSize,
                    'currentPage' => $currentPage,
                )
            );

            $this->getResponse()
                ->setHeader('Content-type', 'application/json')
                ->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->setHeader('Pragma', 'no-cache')
                ->setHeader('Expires', '0')
                ->setHttpResponseCode(200)
                ->setBody(Mage::helper('core')->jsonEncode($response));

        } catch (Exception $e) {
            $httpCode = 500;

            // Définir le code HTTP approprié selon le type d'erreur
            if ($e->getMessage() == 'Authorization header is missing' ||
                $e->getMessage() == 'Invalid access token') {
                $httpCode = 401;
            }

            $response = array(
                'success' => false,
                'message' => $e->getMessage()
            );

            $this->getResponse()
                ->setHeader('Content-type', 'application/json')
                ->setHttpResponseCode($httpCode)
                ->setBody(Mage::helper('core')->jsonEncode($response));
        }
    }
}
