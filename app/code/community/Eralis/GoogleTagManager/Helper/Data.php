<?php
class Eralis_GoogleTagManager_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Get the full action name from the request object
     * - returns like 'checkout_onepage_success'
     *
     * @return string
     */
    public function getFullActionName()
    {
        $request = Mage::app()->getRequest();
        return $request->getRequestedRouteName()
        . '_' . $request->getRequestedControllerName()
        . '_' . $request->getRequestedActionName();
    }
}