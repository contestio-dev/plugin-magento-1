<?php

require_once 'Abstract.php';

class Contestio_Connect_ProfileController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        parent::printMetaTags();

        $this->loadLayout();
        $this->renderLayout();
    }
}