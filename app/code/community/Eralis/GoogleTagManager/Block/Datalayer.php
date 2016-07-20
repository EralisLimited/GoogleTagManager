<?php

class Eralis_GoogleTagManager_Block_Datalayer extends Eralis_GoogleTagManager_Block_Abstract
{

    /**
     * @return string
     */
    public function getDataLayer()
    {
        $dataLayer = array(
            'pageType' => Mage::helper('eralis_googletagmanager')->getFullActionName()
        );

        $dataLayer += $this->_getCustomerData();
        $dataLayer += $this->_getQuoteData();
        $dataLayer += $this->_getOrderData();
        $dataLayer += $this->_getCategoryData();
        // @todo Cripps do this!
//        $dataLayer += $this->_getProductData();

        $dataLayer = new Varien_Object($dataLayer);

        Mage::dispatchEvent('eralis_googletagmanager_data_layer', array('data_layer' => $dataLayer, 'block' => $this));

        if (Mage::getIsDeveloperMode()) {
            return json_encode($dataLayer->getData(), JSON_PRETTY_PRINT);
        }
        return json_encode($dataLayer->getData());
    }

    /**
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        if (!$this->hasData('quote')) {
            $this->setData('quote', Mage::getSingleton('checkout/session')->getQuote());
        }
        return $this->getData('quote');
    }

    /**
     * @return array
     * @throws Mage_Core_Exception
     */
    protected function _getCategoryData()
    {
        $data = array();
        $category = $this->getCategory();
        if ($category && !$this->getProduct()) {
            $data['categoryName'] = $this->jsQuoteEscape($category->getName());
            // @todo Cripps do this!
            $data['categoryPath'] = '';
            /** @var Mage_Catalog_Block_Product_List $block */
            $block = Mage::getSingleton('core/layout')->getBlock('product_list');

            /** @var Mage_Catalog_Model_Resource_Product_Collection $productCollection */
            $productCollection = $block->getLoadedProductCollection();

            /** @var Mage_Catalog_Block_Product_List_Toolbar $toolbar */
            $toolbar = $block->getToolbarBlock();

            if ($orders = $block->getAvailableOrders()) {
                $toolbar->setAvailableOrders($orders);
            }
            if ($sort = $block->getSortBy()) {
                $toolbar->setDefaultOrder($sort);
            }
            if ($dir = $block->getDefaultDirection()) {
                $toolbar->setDefaultDirection($dir);
            }
            if ($modes = $block->getModes()) {
                $toolbar->setModes($modes);
            }

            // set collection to toolbar and apply sort
            $toolbar->setCollection($productCollection);

            $block->setChild('toolbar', $toolbar);

            Mage::dispatchEvent('catalog_block_product_list_collection', array(
                'collection' => $productCollection
            ));

            $productCollection->load();

            $products = array();
            $collection = Mage::getResourceModel('catalog/category_collection')
                ->joinField('product_id',
                    'catalog/category_product',
                    'product_id',
                    'category_id = entity_id',
                    null)
                ->addFieldToFilter('product_id', $productCollection->getColumnValues('entity_id'))
                ->addAttributeToSelect('name')
                ->setStoreId(Mage::app()->getStore()->getId())
                ->groupByAttribute('entity_id');
            foreach ($productCollection as $product) {
                $categories = $collection->getItemsByColumnValue('product_id', $product->getId());
                $categoryNames = array();
                foreach ($categories as $category) {
                    $categoryNames[] = $this->jsQuoteEscape($category->getName());
                }
                $products[$product->getSku()] = array(
                    'name'     => $this->jsQuoteEscape(Mage::helper('core')->escapeHtml($product->getName())),
                    'sku'      => $this->jsQuoteEscape(Mage::helper('core')->escapeHtml($product->getSku())),
                    'category' => implode('|', $categoryNames),
                    'price'    => (double)number_format($product->getFinalPrice(), 2, '.', ''),
                );
            }

            foreach ($products as $product) {
                $data['categoryProducts'][] = $product;
            }
        }
        return $data;
    }

    /**
     * @return Mage_Catalog_Model_Product
     */
    public function getProduct()
    {
        return Mage::registry('current_product');
    }

    /**
     * @return Mage_Catalog_Model_Category
     */
    public function getCategory()
    {
        return Mage::registry('current_category');
    }

    /**
     * @return array
     * @throws Mage_Core_Exception
     */
    protected function _getQuoteData()
    {
        $data = array();
        $quote = $this->getQuote();
        if (Mage::getStoreConfig(self::XML_CONFIG_PATH_DATA_LAYER_QUOTE_DATA_ENABLED) && $quote && $quote->getItemsCount()) {
            $data = array(
                'event'                     => 'transaction',
                'transactionId'             => $quote->getId(),
                'transactionType'           => 'QUOTE',
                'transactionDate'           => date("Y-m-d H:m:s", strtotime($quote->getCreatedAt())),
                'transactionCreatedAtDate'  => date("Y-m-d H:m:s", strtotime($quote->getCreatedAt())),
                'transactionUpdatedAtDate'  => date("Y-m-d H:m:s", strtotime($quote->getUpdatedAt())),
                'transactionAffiliation'    => $this->getWebsiteName(),
                'transactionTotal'          => round($quote->getBaseGrandTotal(), 2),
                'transactionShipping'       => round($quote->getShippingAddress()->getBaseShippingAmount(), 2),
                'transactionTax'            => round($quote->getShippingAddress()->getBaseTaxAmount(), 2),
                'transactionCurrency'       => $quote->getBaseCurrencyCode(),
                'transactionPromoCode'      => $quote->getCouponCode(),
                'transactionProducts'       => array()
            );

            /** @var Mage_Sales_Model_Resource_Quote_Item_Collection $quoteItemCollection */
            $quoteItemCollection = $quote->getItemsCollection();
            /** @var Mage_Catalog_Model_Resource_Product_Collection $productCollection */
            $productCollection = Mage::getResourceModel('catalog/product_collection')
                ->addIdFilter($quoteItemCollection->getColumnValues('product_id'))
                ->addAttributeToSelect('name')
                ->addCategoryIds()
                ->addStoreFilter($quote->getStore());
            $includeInvisible = (bool) Mage::getStoreConfig(self::XML_CONFIG_PATH_QUOTE_DATA_INCLUDE_INVISIBLE_ITEMS);
            $products = array();
            /** @var Mage_Sales_Model_Quote_Item $item */
            foreach ($quoteItemCollection as $item) {
                if (!$item->isDeleted() && empty($products[$item->getSku()]) && (!$item->getParentItemId() || ($includeInvisible && $item->getParentItemId()))) {
                    /** @var Mage_Catalog_Model_Resource_Category_Collection $collection */
                    $collection = Mage::getResourceModel('catalog/category_collection')
                        ->joinField('product_id',
                            'catalog/category_product',
                            'product_id',
                            'category_id = entity_id',
                            null)
                        ->addFieldToFilter('product_id', (int)$item->getProductId())
                        ->addAttributeToSelect('name')
                        ->setStoreId($quote->getStoreId());
                    $products[$item->getSku()] = array(
                        'name'     => $this->jsQuoteEscape(Mage::helper('core')->escapeHtml($item->getName())),
                        'sku'      => $this->jsQuoteEscape(Mage::helper('core')->escapeHtml($item->getSku())),
                        'category' => implode('|', $collection->getColumnValues('name')),
                        'price'    => (double)number_format($item->getBasePrice(), 2, '.', ''),
                        'quantity' => (int)$item->getQty()
                    );
                } else {
                    $products[$item->getSku()]['quantity'] += (int)$item->getQtyOrdered();
                }
            }
            foreach ($products as $product) {
                $data['transactionProducts'][] = $product;
            }

        }
        return $data;
    }

    /**
     * Get visitor data
     *
     * @link https://developers.google.com/tag-manager/reference
     * @return array
     */
    protected function _getCustomerData()
    {
        $data = array();
        if (Mage::getStoreConfig(self::XML_CONFIG_PATH_DATA_LAYER_CUSTOMER_DATA_ENABLED)) {
            $customer = Mage::getSingleton('customer/session');
            if ($customer->getCustomerId()) {
                $data['visitorId'] = (string)$customer->getCustomerId();
            }
            $customerGroup = (string)Mage::getModel('customer/group')->load($customer->getCustomerGroupId())->getCode();
            $data['visitorLoginState'] = ($customer->isLoggedIn()) ? 'Logged in' : 'Logged out';
            $data['visitorType'] = $customerGroup;
            $data['customerGroup'] = $customerGroup;

            $orders = Mage::getResourceModel('sales/order_collection')
                ->addFieldToSelect('grand_total')
                ->addFieldToFilter('customer_id', $customer->getId());
            $ordersTotal = array_sum((array)$orders->getColumnValues('base_grand_total'));

            if ($customer->isLoggedIn()) {
                $data['visitorLifetimeValue'] = round($ordersTotal, 2);
                $data['visitorLifetimeOrders'] = $orders->count();
            } else {
                $orderData = $this->_getOrderData();
                if (!empty($orderData)) {
                    $data['visitorLifetimeValue'] = $orderData['transactionTotal'];
                } else {
                    $data['visitorLifetimeValue'] = 0;
                }
            }
            $existingCustomerRequirements = explode(',', Mage::getStoreConfig(self::XML_CONFIG_PATH_CUSTOMER_DATA_EXISTING_CUSTOMER_MODE));
            $existingCustomer = false;
            foreach ($existingCustomerRequirements as $existingCustomerRequirement) {
                switch ($existingCustomerRequirement) {
                    case 'grand_total':
                        if ($ordersTotal > 0) {
                            $existingCustomer = true;
                        }
                        break;
                    case 'basket':
                        if ($this->getQuote()->getItemsQty()) {
                            $existingCustomer = true;
                        }
                        break;
                    case 'registered':
                    default:
                        if ($customer->isLoggedIn()) {
                            $existingCustomer = true;
                        }
                        break;
                }
                if ($existingCustomer) {
                    break;
                }
            }
            $data['visitorExistingCustomer'] = $existingCustomer ? 'Yes' : 'No';

            if (Mage::getStoreConfig(self::XML_CONFIG_PATH_CUSTOMER_DATA_INCLUDE_RECENTLY_VIEWED_PRODUCTS)) {
                $attributes = Mage::getSingleton('catalog/config')->getProductAttributes();
                $collection = Mage::getModel('reports/product_index_viewed')
                    ->getCollection()
                    ->addAttributeToSelect($attributes);

                if ($customer->getId()) {
                    $collection->setCustomerId($customer->getId());
                }

                $pageSize = max(50, min(1, (int) Mage::getStoreConfig(self::XML_CONFIG_PATH_CUSTOMER_DATA_RECENTLY_VIEWED_PRODUCT_LIMIT)));
                $collection->setPageSize($pageSize)
                    ->setCurPage(1);

                /* Price data is added to consider item stock status using price index */
                $collection->addPriceData();

                $collection->addIndexFilter();
                $collection->setAddedAtOrder();

                Mage::getSingleton('catalog/product_visibility')
                    ->addVisibleInSiteFilterToCollection($collection);

                if ($collection->count()) {
                    $products = array();
                    foreach ($collection as $product) {
                        /** @var Mage_Catalog_Model_Resource_Category_Collection $categoryCollection */
                        $categoryCollection = Mage::getResourceModel('catalog/category_collection')
                            ->joinField('product_id',
                                'catalog/category_product',
                                'product_id',
                                'category_id = entity_id',
                                null)
                            ->addFieldToFilter('product_id', (int)$product->getId())
                            ->addAttributeToSelect('name')
                            ->setStoreId(Mage::app()->getStore()->getId());

                        $products[$product->getSku()] = array(
                            'name'      => $this->jsQuoteEscape(Mage::helper('core')->escapeHtml($product->getName())),
                            'sku'       => $this->jsQuoteEscape(Mage::helper('core')->escapeHtml($product->getSku())),
                            'category'  => implode('|', $categoryCollection->getColumnValues('name')),
                            'price'     => (double)number_format($product->getFinalPrice(), 2, '.', ''),
                            'viewed_at' => date('Y-m-d H:m:s', strtotime($product->getAddedAt()))
                        );
                    }
                    foreach ($products as $product) {
                        $data['recentlyViewedProducts'][] = $product;
                    }
                }
            }
        }
        return $data;
    }

    /**
     * @return array
     */
    protected function _getOrderData()
    {
        $data = array();
        if (Mage::getStoreConfig(self::XML_CONFIG_PATH_DATA_LAYER_ORDER_DATA_ENABLED) && $this->getData('order_ids')) {
            $orderIds = $this->getData('order_ids');
            if (!empty($orderIds) && is_array($orderIds)) {
                /** @var Mage_Sales_Model_Resource_Order_Collection $collection */
                $collection = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('entity_id', array('in' => $orderIds));
                $i = 0;
                $products = array();
                /** @var Mage_Sales_Model_Order $order */
                foreach ($collection as $order) {
                    if ($i == 0) {
                        $data = array(
                            'event'                     => 'transaction',
                            'transactionId'             => $order->getIncrementId(),
                            'transactionDate'           => date("Y-m-d H:m:s", strtotime($order->getCreatedAt())),
                            'transactionType'           => 'ORDER',
                            'transactionAffiliation'    => $this->getWebsiteName(),
                            'transactionTotal'          => round($order->getBaseGrandTotal(), 2),
                            'transactionShipping'       => round($order->getBaseShippingAmount(), 2),
                            'transactionTax'            => round($order->getBaseTaxAmount(), 2),
                            'transactionPaymentType'    => $order->getPayment()->getMethodInstance()->getTitle(),
                            'transactionCurrency'       => $order->getBaseCurrencyCode(),
                            'purchaseCurrency'          => $order->getOrderCurrencyCode(),
                            'transactionShippingMethod' => $order->getShippingCarrier()->getCarrierCode(),
                            'transactionPromoCode'      => $order->getCouponCode(),
                            'transactionProducts'       => array()
                        );
                    } else {
                        $data['transactionId'] .= '|' . $order->getIncrementId();
                        $data['transactionTotal'] += $order->getBaseGrandTotal();
                        $data['transactionShipping'] += $order->getBaseShippingAmount();
                        $data['transactionTax'] += $order->getBaseTaxAmount();
                        $data['transactionShippingMethod'] .= '|' . $order->getShippingCarrier()->getCarrierCode();
                    }

                    $customer = Mage::getSingleton('customer/session');
                    if ($customer->isLoggedIn()) {
                        $data['purchaseNumber'] = 1;
                        /** @var Mage_Sales_Model_Resource_Order_Collection $customerOrderCollection */
                        $customerOrderCollection = Mage::getModel('sales/order')->getCollection()
                            ->addFieldToFilter('customer_id', $customer->getId())
                            ->addFieldToFilter('entity_id', array('nin' => $orderIds))
                            ->addFieldToFilter('status', array('nin' => array('canceled', 'pay_aborted')))
                            ->setOrder('created_at');
                        if ($customerOrderCollection && $customerOrderCollection->count()) {
                            $data['purchaseNumber'] += $customerOrderCollection->count();
                            $lastOrderDate = new \DateTime($customerOrderCollection->getFirstItem()->getCreatedAt());
                            $nowDate       = new \DateTime(Mage::getModel('core/date')->date());
                            $data['daysSinceLastTransaction'] = $lastOrderDate->diff($nowDate)->format('%a');
                        }
                    } else {
                        $data['purchaseNumber'] = 0;
                    }

                    /** @var Mage_Sales_Model_Resource_Order_Collection $salesItemCollection */
                    $salesItemCollection = $order->getItemsCollection();
                    /** @var Mage_Catalog_Model_Resource_Product_Collection $productCollection */
                    $productCollection = Mage::getResourceModel('catalog/product_collection')
                        ->addIdFilter($salesItemCollection->getColumnValues('product_id'))
                        ->addAttributeToSelect('name')
                        ->addCategoryIds()
                        ->addStoreFilter($order->getStore());
                    $includeInvisible = (bool) Mage::getStoreConfig(self::XML_CONFIG_PATH_ORDER_DATA_INCLUDE_INVISIBLE_ITEMS);
                    /** @var Mage_Sales_Model_Order_Item $item */
                    foreach ($salesItemCollection as $item) {
                        if (!$item->isDeleted() && empty($products[$item->getSku()]) && (!$item->getParentItemId() || ($includeInvisible && $item->getParentItemId()))) {
                            /** @var Mage_Catalog_Model_Resource_Category_Collection $collection */
                            $collection = Mage::getResourceModel('catalog/category_collection')
                                ->joinField('product_id',
                                    'catalog/category_product',
                                    'product_id',
                                    'category_id = entity_id',
                                    null)
                                ->addFieldToFilter('product_id', (int)$item->getProductId())
                                ->addAttributeToSelect('name')
                                ->setStoreId($order->getStoreId());
                            $products[$item->getSku()] = array(
                                'name'     => $this->jsQuoteEscape(Mage::helper('core')->escapeHtml($item->getName())),
                                'sku'      => $this->jsQuoteEscape(Mage::helper('core')->escapeHtml($item->getSku())),
                                'category' => implode('|', $collection->getColumnValues('name')),
                                'price'    => (double)number_format($item->getBasePrice(), 2, '.', ''),
                                'quantity' => (int)$item->getQtyOrdered()
                            );
                        } else {
                            $products[$item->getSku()]['quantity'] += (int)$item->getQtyOrdered();
                        }
                    }
                }
                foreach ($products as $product) {
                    $data['transactionProducts'][] = $product;
                }
                foreach ($data as $key => $value) {
                    if (!is_numeric($value) && empty($value)) unset($data[$key]);
                }
            }
        }
        return $data;
    }

    /**
     * @return string
     * @throws Mage_Core_Exception
     */
    public function getWebsiteName()
    {
        return Mage::app()->getWebsite()->getName();
    }

    /**
     * @param float $price
     * @return float
     */
    protected function _convertCurrency($price)
    {
        $from = Mage::app()->getStore()->getBaseCurrencyCode();
        $to = Mage::app()->getStore()->getCurrentCurrencyCode();

        if ($from != $to) {
            $price = Mage::helper('directory')->currencyConvert($price, $from, $to);
            $price = Mage::app()->getStore()->roundPrice($price);
        }

        return $price;
    }
}