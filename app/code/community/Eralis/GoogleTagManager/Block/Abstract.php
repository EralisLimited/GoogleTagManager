<?php
abstract class Eralis_GoogleTagManager_Block_Abstract extends Mage_Core_Block_Abstract
{
    const DATA_LAYER_NAME_DEFAULT = 'dataLayer';

    /**
     * Returns the system configuration container ID.
     * 
     * @return string
     */
    public function getContainerId()
    {
        if (!$this->hasData('container_id')) {
            $this->setData('container_id', Mage::getStoreConfig('erails_googletagmanager/datalayer/container_id'));
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
            $dataLayerName = Mage::getStoreConfig('erails_googletagmanager/datalayer/data_layer_name');
            if (!$dataLayerName) {
                $dataLayerName = self::DATA_LAYER_NAME_DEFAULT;
            }
            $this->setData('data_layer_name', $dataLayerName);
        }
        return $this->getData('data_layer_name');

    }
}