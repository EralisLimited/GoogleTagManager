<?php
class Eralis_GoogleTagManager_Block_Head extends Eralis_GoogleTagManager_Block_Abstract
{
    /**
     * Internal constructor
     * - Used to set default template if not set in layout.
     */
    protected function _construct()
    {
        if (!$this->hasData('template')) {
            $this->setData('template', 'eralis/googletagmanager/head.phtml');
        }
        return parent::_construct();
    }
}