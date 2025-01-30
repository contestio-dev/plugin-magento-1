<?php
class Contestio_Connect_MeController extends Mage_Core_Controller_Front_Action
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

            // Récupérer l'ID client à partir de la requête
            $customerId = $this->getRequest()->getParam('id');
            if (!$customerId) {
                throw new Exception('Customer ID is required');
            }

            // Charger le client par ID
            $customer = Mage::getModel('customer/customer')->load($customerId);
            if (!$customer->getId()) {
                throw new Exception('Customer not found');
            }

            // Préparer la réponse
            $response = array(
                'success' => true,
                'data' => array(
                    'id' => $customer->getId(),
                    'email' => $customer->getEmail(),
                    'firstName' => $customer->getFirstname(),
                    'lastName' => $customer->getLastname(),
                    'createdAt' => $customer->getCreatedAt()
                )
            );

            $this->getResponse()
                ->setHeader('Content-type', 'application/json')
                ->setHttpResponseCode(200)
                ->setBody(Mage::helper('core')->jsonEncode($response));

        } catch (Exception $e) {
            $httpCode = 500;
            
            // Définir le code HTTP approprié selon le type d'erreur
            if ($e->getMessage() == 'Authorization header is missing' || 
                $e->getMessage() == 'Invalid access token') {
                $httpCode = 401;
            } elseif ($e->getMessage() == 'Customer ID is required' || 
                      $e->getMessage() == 'Customer not found') {
                $httpCode = 400;
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
