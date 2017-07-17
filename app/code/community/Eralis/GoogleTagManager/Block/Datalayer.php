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
        $dataLayer += $this->_getProductData();

        $dataLayer = new Varien_Object($dataLayer);

        Mage::dispatchEvent('eralis_googletagmanager_data_layer', array('data_layer' => $dataLayer, 'block' => $this));

        if (Mage::getIsDeveloperMode()) {
            return json_encode($dataLayer->getData(), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }
        return json_encode($dataLayer->getData(), JSON_UNESCAPED_SLASHES);
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
    protected function _getProductData()
    {
        $data = array();
        /* @var $product Mage_Catalog_Model_Product */
        $product = $this->getProduct();
        $defaultTax = false;
        $currentTax = false;

        if ($product) {
            $data['productName'] = $this->jsQuoteEscape($product->getName());
            $_request = Mage::getSingleton('tax/calculation')->getDefaultRateRequest();

            if ($_request) {
                $_request->setProductClassId($product->getTaxClassId());
                $defaultTax = Mage::getSingleton('tax/calculation')->getRate($_request);

                $_request = Mage::getSingleton('tax/calculation')->getRateRequest();
                $_request->setProductClassId($product->getTaxClassId());
                $currentTax = Mage::getSingleton('tax/calculation')->getRate($_request);
            }


            $_regularPrice = $product->getPrice();
            $_finalPrice = $product->getFinalPrice();
            if ($product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
                $_priceInclTax = Mage::helper('tax')->getPrice($product, $_finalPrice, true,
                    null, null, null, null, null, false);
                $_priceExclTax = Mage::helper('tax')->getPrice($product, $_finalPrice, false,
                    null, null, null, null, null, false);
            } else {
                $_priceInclTax = Mage::helper('tax')->getPrice($product, $_finalPrice, true);
                $_priceExclTax = Mage::helper('tax')->getPrice($product, $_finalPrice);
            }
            $_tierPrices = array();
            $_tierPricesInclTax = array();
            foreach ($product->getTierPrice() as $tierPrice) {
                $_tierPrices[] = Mage::helper('core')->currency(
                    Mage::helper('tax')->getPrice($product, (float)$tierPrice['website_price'], false) - $_priceExclTax
                    , false, false);
                $_tierPricesInclTax[] = Mage::helper('core')->currency(
                    Mage::helper('tax')->getPrice($product, (float)$tierPrice['website_price'], true) - $_priceInclTax
                    , false, false);
            }

            /** @var Mage_Catalog_Model_Resource_Category_Collection $categoryCollection */
            $categoryCollection = Mage::getResourceModel('catalog/category_collection')
                ->setStore(Mage::app()->getStore())
                ->addAttributeToSelect('name')
                ->addFieldToFilter('entity_id', array('in' => $product->getCategoryIds()))
                ->addFieldToFilter('is_active', 1)
                ->addPathsFilter('1/' . Mage::app()->getStore()->getRootCategoryId() . '/');

            $data += array(
                'productId'           => $product->getId(),
                'productSku'          => $this->jsQuoteEscape($product->getSku()),
                'productPrice'        => Mage::helper('core')->currency($_finalPrice, false, false),
                'productOldPrice'     => Mage::helper('core')->currency($_regularPrice, false, false),
                'priceInclTax'        => Mage::helper('core')->currency($_priceInclTax, false, false),
                'priceExclTax'        => Mage::helper('core')->currency($_priceExclTax, false, false),
                'defaultTax'          => $defaultTax ? $defaultTax : '',
                'currentTax'          => $currentTax ? $currentTax : '',
                'tierPrices'          => $_tierPrices,
                'tierPricesInclTax'   => $_tierPricesInclTax,
                'categories'          => implode('|', $categoryCollection->getColumnValues('name'))
            );
        }

        return $data;
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
            /** @var Mage_Catalog_Model_Resource_Category_Collection $parentCategoryCollection */
            $parentCategoryCollection = Mage::getResourceModel('catalog/category_collection')
                ->setStore(Mage::app()->getStore())
                ->addAttributeToSelect('name')
                ->addAttributeToSelect('url_key')
                ->addFieldToFilter('entity_id', array('in' => $category->getPathIds()))
                ->addFieldToFilter('is_active', 1)
                ->addOrder('level', Mage_Catalog_Model_Resource_Category_Collection::SORT_ORDER_ASC)
                ->addPathsFilter('1/' . Mage::app()->getStore()->getRootCategoryId() . '/');
            $data['categoryName']       = $this->jsQuoteEscape($category->getName());
            $data['categoryUrlKey']     = $this->jsQuoteEscape($category->getUrlKey());
            $data['categoryUrlKeyPath'] = $this->jsQuoteEscape(implode('/', $parentCategoryCollection->getColumnValues('url_key')));
            $data['categoryNamePath']   = $this->jsQuoteEscape(implode('|', $parentCategoryCollection->getColumnValues('name')));

            $productCollection = $this->_getProductListCollection();

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
                    if ($category->getLevel() > 1) {
                        $categoryNames[] = $this->jsQuoteEscape($category->getName());
                    }
                }
                $products[$product->getSku()] = array(
                    'name'          => $this->jsQuoteEscape(Mage::helper('core')->escapeHtml($product->getName())),
                    'sku'           => $this->jsQuoteEscape(Mage::helper('core')->escapeHtml($product->getSku())),
                    'category'      => implode('|', $categoryNames),
                    'price'         => (double)number_format($product->getFinalPrice(), 2, '.', ''),
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

    protected function _getProductListCollection()
    {
        if (!Mage::registry('eralis_product_list')) {
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

            $this->_getProductCollection()->load();

            Mage::register('eralis_product_list', $productCollection->load());
        }

        return Mage::registry('eralis_product_list');

    }

    /**
     * Retrieve loaded category collection
     *
     * @return Mage_Eav_Model_Entity_Collection_Abstract
     */
    protected function _getProductCollection()
    {
        if (is_null($this->_productCollection)) {
            $layer = $this->_getLayer();
            /* @var $layer Mage_Catalog_Model_Layer */
            if ($this->getShowRootCategory()) {
                $this->setCategoryId(Mage::app()->getStore()->getRootCategoryId());
            }

            // if this is a product view page
            if (Mage::registry('product')) {
                // get collection of categories this product is associated with
                $categories = Mage::registry('product')->getCategoryCollection()
                    ->setPage(1, 1)
                    ->load();
                // if the product is associated with any category
                if ($categories->count()) {
                    // show products from this category
                    $this->setCategoryId(current($categories->getIterator()));
                }
            }

            $origCategory = null;
            if ($this->getCategoryId()) {
                $category = Mage::getModel('catalog/category')->load($this->getCategoryId());
                if ($category->getId()) {
                    $origCategory = $layer->getCurrentCategory();
                    $layer->setCurrentCategory($category);
                    $this->addModelTags($category);
                }
            }
            $this->_productCollection = $layer->getProductCollection();

            if ($origCategory) {
                $layer->setCurrentCategory($origCategory);
            }
        }

        return $this->_productCollection;
    }

    /**
     * Get catalog layer model
     *
     * @return Mage_Catalog_Model_Layer
     */
    protected function _getLayer()
    {
        $layer = Mage::registry('current_layer');
        if ($layer) {
            return $layer;
        }
        return Mage::getSingleton('catalog/layer');
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
                            'transactionSubtotal'       => round($order->getBaseSubtotal(), 2),
                            'transactionShipping'       => round($order->getBaseShippingAmount(), 2),
                            'transactionTax'            => round($order->getBaseTaxAmount(), 2),
                            'transactionDiscount'       => round($order->getBaseDiscountAmount(), 2),
                            'transactionPaymentType'    => $order->getPayment()->getMethodInstance()->getTitle(),
                            'transactionCurrency'       => $order->getBaseCurrencyCode(),
                            'purchaseCurrency'          => $order->getOrderCurrencyCode(),
                            'transactionShippingMethod' => $order->getShippingCarrier()->getCarrierCode(),
                            'transactionPromoCode'      => $order->getCouponCode(),
                            'transactionProducts'       => array()
                        );
                    } else {
                        $data['transactionId']              .= '|' . $order->getIncrementId();
                        $data['transactionTotal']           += $order->getBaseGrandTotal();
                        $data['transactionShipping']        += $order->getBaseShippingAmount();
                        $data['transactionTax']             += $order->getBaseTaxAmount();
                        $data['transactionShippingMethod']  .= '|' . $order->getShippingCarrier()->getCarrierCode();
                    }

                    $customerSession = Mage::getSingleton('customer/session');
                    if ($customerSession->isLoggedIn()) {
                        $data['purchaseNumber'] = 1;
                        /** @var Mage_Sales_Model_Resource_Order_Collection $customerOrderCollection */
                        $customerOrderCollection = Mage::getModel('sales/order')->getCollection()
                            ->addFieldToFilter('customer_id', $customerSession->getId())
                            ->addFieldToFilter('entity_id', array('nin' => $orderIds))
                            ->addFieldToFilter('status', array('nin' => array('canceled', 'pay_aborted')))
                            ->setOrder('created_at');
                        if ($customerOrderCollection && $customerOrderCollection->count()) {
                            $data['purchaseNumber']             = $customerOrderCollection->count();
                            $lastOrderDate                      = new \DateTime($customerOrderCollection->getFirstItem()->getCreatedAt());
                            $nowDate                            = new \DateTime(Mage::getModel('core/date')->date());
                            $data['daysSinceLastTransaction']   = $lastOrderDate->diff($nowDate)->format('%a');
                        }
                    } else {
                        $data['purchaseNumber'] = 0;
                    }

                    /** @var Mage_Sales_Model_Resource_Order_Collection $salesItemCollection */
                    $salesItemCollection = $order->getItemsCollection();
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
}