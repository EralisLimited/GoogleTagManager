<?php
abstract class Eralis_GoogleTagManager_Block_Abstract extends Mage_Core_Block_Template
{
    const XML_CONFIG_PATH_ENABLED                               = 'eralis_googletagmanager/general/enabled';

    const XML_CONFIG_PATH_DATA_LAYER_CONTAINER_ID               = 'eralis_googletagmanager/data_layer/container_id';
    const XML_CONFIG_PATH_DATA_LAYER_DATA_LAYER_NAME            = 'eralis_googletagmanager/data_layer/data_layer_name';
    const XML_CONFIG_PATH_DATA_LAYER_CUSTOMER_DATA_ENABLED      = 'eralis_googletagmanager/data_layer/customer_data_enabled';
    const XML_CONFIG_PATH_DATA_LAYER_ORDER_DATA_ENABLED         = 'eralis_googletagmanager/data_layer/order_data_enabled';
    const XML_CONFIG_PATH_DATA_LAYER_QUOTE_DATA_ENABLED         = 'eralis_googletagmanager/data_layer/quote_data_enabled';

    const XML_CONFIG_PATH_CUSTOMER_DATA_EXISTING_CUSTOMER_MODE              = 'eralis_googletagmanager/customer_data/existing_customer_mode';
    const XML_CONFIG_PATH_CUSTOMER_DATA_INCLUDE_RECENTLY_VIEWED_PRODUCTS    = 'eralis_googletagmanager/customer_data/include_recently_viewed_products';
    const XML_CONFIG_PATH_CUSTOMER_DATA_RECENTLY_VIEWED_PRODUCT_LIMIT       = 'eralis_googletagmanager/customer_data/recently_viewed_product_limit';

    const XML_CONFIG_PATH_QUOTE_DATA_INCLUDE_INVISIBLE_ITEMS    = 'eralis_googletagmanager/quote_data/include_invisible_items';

    const XML_CONFIG_PATH_ORDER_DATA_INCLUDE_INVISIBLE_ITEMS    = 'eralis_googletagmanager/order_data/include_invisible_items';
    
    const DATA_LAYER_NAME_DEFAULT       = 'dataLayer';

    /**
     * Returns the system configuration container ID.
     * 
     * @return string
     */
    public function getContainerId()
    {
        if (!$this->hasData('container_id')) {
            $this->setData('container_id', Mage::getStoreConfig(self::XML_CONFIG_PATH_DATA_LAYER_CONTAINER_ID));
        }
        return $this->getData('container_id');
    }

    /**
     * Returns the system configuration data layer name.
     * - Defaults to self::DATA_LAYER_NAME_DEFAULT when not set
     *
     * @return string
     */
    public function getDataLayerName()
    {
        if (!$this->hasData('data_layer_name')) {
            $dataLayerName = Mage::getStoreConfig(self::XML_CONFIG_PATH_DATA_LAYER_DATA_LAYER_NAME);
            if (!$dataLayerName) {
                $dataLayerName = self::DATA_LAYER_NAME_DEFAULT;
            }
            $this->setData('data_layer_name', $dataLayerName);
        }
        return $this->getData('data_layer_name');
    }
    
    /**
     * Render block HTML
     *
     * @return string
     */
    protected function _toHtml()
    {
        if (!Mage::getStoreConfig(self::XML_CONFIG_PATH_ENABLED)) {
            return '';
        }
        return parent::_toHtml();
    }
}