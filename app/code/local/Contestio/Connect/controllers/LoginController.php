<?php

class Contestio_Connect_LoginController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    public function postAction()
    {
        $content = json_decode($this->getRequest()->getRawBody(), true);
        $username = $content['username'] ?? null;
        $password = $content['password'] ?? null;

        if ($username && $password) {
            try {
                $session = Mage::getSingleton('customer/session');
                $session->login($username, $password);
                if ($session->isLoggedIn()) {
                    $response = ['success' => true];
                } else {
                    $response = ['success' => false, 'message' => 'Identifiant ou mot de passe incorrect.'];
                }
            } catch (Exception $e) {
                $response = ['success' => false, 'message' => 'Identifiant ou mot de passe incorrect.'];
            }
        } else {
            $response = ['success' => false, 'message' => 'Veuillez fournir un identifiant et un mot de passe.'];
        }

        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
    }
}
