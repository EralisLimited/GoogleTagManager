<?php
class Eralis_GoogleTagManager_Block_Datalayer extends Eralis_GoogleTagManager_Block_Abstract
{
    const XML_CONFIG_PATH_DATA_LAYER_VISITOR_DATA_ENABLED = 'eralis_googletagmanager/data_layer/visitor_data_enabled';
    const XML_CONFIG_PATH_DATA_LAYER_TRANSACTION_DATA_ENABLED = 'eralis_googletagmanager/data_layer/transaction_data_enabled';

    /**
     * @return string
     */
    public function getDataLayer()
    {
        $dataLayer = array();

        $dataLayer += $this->_getVisitorData();
        $dataLayer += $this->_getTransactionData();

        $dataLayer = new Varien_Object($dataLayer);

        Mage::dispatchEvent('eralis_googletagmanager_data_layer', array('data_layer' => $dataLayer, 'block' => $this));


        return json_decode($dataLayer->getData());
    }

    /**
     * @return array
     */
    protected function _getVisitorData()
    {
        $data = array();
        if (Mage::getStoreConfig(self::XML_CONFIG_PATH_DATA_LAYER_VISITOR_DATA_ENABLED)) {

        }
        return $data;
    }

    /**
     * @return array
     */
    protected function _getTransactionData()
    {
        $data = array();
        if (Mage::getStoreConfig(self::XML_CONFIG_PATH_DATA_LAYER_TRANSACTION_DATA_ENABLED)) {

        }
        return $data;

    }
}