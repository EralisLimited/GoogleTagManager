<?php
class Eralis_GoogleTagManager_Model_Observer
{
    /**
     * Attempts to add the transaction data to the data layer
     * - Includes a handy event for third party developers to modify this logic.
     *
     * @param Varien_Event_Observer $observer
     */
    public function addTransactionData(Varien_Event_Observer $observer)
    {
        try {

            $data = new Varien_Object();

            $orderIds = $observer->getEvent()->getOrderIds();

            $fullActionName = $this->_getFullActionName();

            if (!empty($orderIds) && in_array($fullActionName, $this->_getLayoutHandles())) {
                $data->setData('order_ids', $orderIds);
            }

            Mage::getSingleton('core/layout')->getBlock('eralis.googletagmanager.datalayer')->setData('order_ids', $data->getOrderIds());

        } catch (Exception $e) {

            // Lets not break peoples sites when devs do naughty things with our layout XML :p
            Mage::logException($e);

        }
    }

    /**
     * Gets the layout handles which the transaction data will appear
     * - Includes a handy event for third party developers to modify this logic.
     *
     * @return array
     */
    protected function _getLayoutHandles()
    {
        $layoutHandles = new Varien_Object(explode(',', Mage::getStoreConfig('eralis_googletagmanager/data_layer/transaction_layout_handles')));
        return $layoutHandles->toArray();
    }

    /**
     * Get the full action name from the request object
     * - returns like 'checkout_onepage_success'
     *
     * @return string
     */
    protected function _getFullActionName()
    {
        return Mage::helper('eralis_googletagmanager')->getFullActionName();
    }
}