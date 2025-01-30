<?php

class Contestio_Connect_Helper_Api extends Mage_Core_Helper_Abstract
{
    private $shopName;
    private $accessToken;

    public function __construct()
    {
        $this->shopName = Mage::getStoreConfig('contestio_connect/api_settings/api_key');
        $this->accessToken = Mage::getStoreConfig('contestio_connect/api_settings/access_token');
    }

    private function getApiBaseUrl()
    {
        $baseUrl = Mage::getStoreConfig('contestio_connect/api_settings_advanced/base_url');
        return $baseUrl ? $baseUrl : 'https://api.contestio.fr';
    }

    public function callApi($userAgent, $endpoint, $method, $data = null, $externalId = null, $externalEmail = null)
    {
        try {
            $ch = curl_init($this->getApiBaseUrl() . '/' . $endpoint);

            $headers = [
                'Content-Type' => 'application/json',
                'client-shop' => $this->shopName,
                'clientuseragent' => $userAgent
            ];

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Timeout après 5 secondes
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // Timeout de connexion après 3 secondes
            
            // Add customer id and email to headers
            if (Mage::getSingleton('customer/session')->isLoggedIn()) {
                $customer = Mage::getSingleton('customer/session')->getCustomer();
                $helper = Mage::helper('contestio_connect/api');
            
                $headers['client-customer-id'] = $helper->encryptDataBase64(
                    $externalId ?? $customer->getId(),
                    $this->accessToken
                );
                $headers['client-customer-email'] = $helper->encryptDataBase64(
                    $externalEmail ?? $customer->getEmail(),
                    $this->accessToken
                );
            }

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            // Set data (used for POST - final user order observer)
            if (!empty($data) && $method === 'POST') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                throw new Exception("Erreur CURL: " . $error);
            }

            if ($httpCode >= 200 && $httpCode < 300) {
                return json_decode($response, true);
            }
            
            throw new Exception($response, $httpCode);
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function encryptDataBase64($data, $accessToken) {
        $method = 'AES-256-CBC';

        // Generate key and iv
        $key = hash('sha256', $accessToken, true);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
    
        // Encrypt data
        $encrypted = openssl_encrypt($data, $method, $key, 0, $iv);
    
        // Encode data and iv in Base64
        return base64_encode(json_encode([
            'iv' => base64_encode($iv),
            'data' => $encrypted,
        ]));
    }
}
