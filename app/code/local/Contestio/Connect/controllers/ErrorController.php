<?php

require_once 'Abstract.php';

class Contestio_Connect_ErrorController extends Contestio_Connect_Controller_Abstract
{
    public function indexAction()
    {
        parent::printMetaTags();

        $this->loadLayout();
        $this->renderLayout();
    }
}