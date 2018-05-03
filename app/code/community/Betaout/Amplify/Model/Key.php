<?php
require_once Mage::getModuleDir('Model', 'Betaout_Amplify').DS.'Model/Amplify.php';


class Betaout_Amplify_Model_Key extends Mage_Core_Model_Abstract {
    /* @var $this Betaout_Amplify_Model_Key */

    public $key;
    public $secret;
    public $projectId;
    public $bdebug=0;
    public $verified;
    public $host = 'y0v.in';
    public $amplify;
    public $allitems;
    public $checkstring;
    public $email = '';
    public $installDate;
    public $_process_date;
    public $_schedule = '0 0 0 1 12 4090';

    const XML_PATH_KEY = 'betaout_amplify_options/settings/amplify_key';
    const XML_PATH_SECRET = 'betaout_amplify_options/settings/amplify_secret';
    const XML_PATH_PROJECTID = 'betaout_amplify_options/settings/amplify_projectId';
    const XML_PATH_SEND_ORDER_STATUS = 'betaout_amplify_options/order/status1';
    const XML_PATH_DEBUG = 'betaout_amplify_options/settings/amplify_debug';
    const MAIL_TO = 'dharmendra@getamplify.com';
    const MAIL_SUB = 'Magento Info';
    const XML_PATH_MAX_RUNNING_TIME = 'system/cron/max_running_time';
    const XML_PATH_EMAIL_TEMPLATE = 'system/cron/error_email_template';
    const XML_PATH_EMAIL_IDENTITY = 'system/cron/error_email_identity';
    const XML_PATH_EMAIL_RECIPIENT = 'system/cron/error_email';

    public function __construct($key_string) {
        try {

            $this->key = Mage::getStoreConfig(self::XML_PATH_KEY);
            $this->secret = Mage::getStoreConfig(self::XML_PATH_SECRET);
            $this->projectId = Mage::getStoreConfig(self::XML_PATH_PROJECTID);
            $this->verified = 1;
            $this->bdebug = Mage::getStoreConfig(self::XML_PATH_DEBUG);
            $this->amplify = new Amplify($this->key,$this->projectId);
            $this->verified = 1;
            $this->_process_date = Mage::getStoreConfig('betaout_amplify_options/settings/_process_date');
        } catch (Exception $ex) {
            
        }
    }

    public function getToken() {
        $visitorData = Mage::getSingleton('core/session')->getVisitorData();
        return $visitorData['visitor_id'];
    }

    
    public function getAmplifyConfigChangeObserver($evnt) {
    }

    public function getAmplifyEventRemoveFromCart(Varien_Event_Observer $observer) {

        try {
            if ($this->verified && is_object($observer)) {

                $product = $observer->getEvent()->getQuote_item();
                $actionData = array();
                $newProduct=Mage::getModel("catalog/product")->loadByAttribute('sku',$product->getSku());
               if($newProduct->getTypeID()!="bundle"){
                $productId = $product->getProductId();
                $productName = $newProduct->getName();
                $actionData[0]['product_group_id']=$productId;
                $actionData[0]['product_group_name']=$product->getName();
                $actionData[0]['id'] = $newProduct->getId();;
                $actionData[0]['name'] = $productName;
                $actionData[0]['sku'] = $product->getSku();
                $actionData[0]['price'] = $product->getPrice();
                $actionData[0]['currency'] = Mage::app()->getStore()->getBaseCurrencyCode();
                $actionData[0]['quantity'] = (int) $product->getQty();
  
                 }else{
                    $pdata=$product->getData();
                    $removeArray=$pdata['qty_options'];
                    $i=0;
                    foreach($removeArray as $radat=>$val){
                     $newproduct= Mage::getModel("catalog/product")->load($radat);
                     $actionData[$i]['id'] =$newproduct->getId();
                     $actionData[$i]['name'] =$newproduct->getName();
                     $actionData[$i]['sku'] = $newproduct->getSku();
                     $actionData[$i]['price'] = $newproduct->getPrice();
                     $actionData[$i]['currency'] = Mage::app()->getStore()->getBaseCurrencyCode();
                     $actionData[$i]['quantity']=$product->getQty();
                     $i++;
                    }
                 }
                $subprice = (int) $product->getQty() * $product->getPrice();
                //$subprice=Mage::helper('core')->currency($subprice , false, false);
                $cart = Mage::getSingleton('checkout/cart');
                $cart_id=$cart->getQuote()->getId();
                $subTotalPrice = $cart->getQuote()->getGrandTotal();
                
                $cartInfo["total"] = $subTotalPrice - $subprice;
                $cartInfo["revenue"] = $subTotalPrice - $subprice;
                $cartInfo['currency'] = Mage::app()->getStore()->getCurrentCurrencyCode();
                
                $actionDescription = array(
                    'activity_type' => 'remove_from_cart',
                    'identifiers' => $this->getCustomerIdentity(),
                    'cart_info' => $cartInfo,
                    'products' => $actionData
                );
               mail("rohit@getamplify.com","Magento 18 remove cart", json_encode($actionDescription));
               $this->amplify->customer_action($actionDescription);
            }
        } catch (Exception $ex) {
            
        }
    }

    public function getAmplifyEventAddToCart(Varien_Event_Observer $evnt) {
        try {
           
            if ($this->verified) {
                $actionData = array();
                $event = $evnt->getEvent();
                $product = $event->getProduct();
                $productId = $product->getId();
                $request = $event->getData('request');
                $params=$request->getParams();
                $attrArray=array();
              if($product->getTypeID()=='grouped'){
                    $products = $product->getTypeInstance(true)->getAssociatedProducts($product);
                    $i=0;
                    $pGid=0;
                    $pGname=$product->getName();
                    foreach($products as $p){
                       $productGrouped = Mage::getModel('catalog/product')->load($p->getId());
                        $productId = $productGrouped->getId(); 
                        $productName = $productGrouped->getName();
                        $qty=$params['super_group'][$productId];
                        if($qty==0){
                            continue;
                        }
                        $attributes = $productGrouped->getAttributes();
                        foreach ($attributes as $attribute) { 
                           if($attribute->getIsUserDefined()){
                            $attributeLabel = $attribute->getFrontendLabel();
                            $value = $attribute->getFrontend()->getValue($product);
                            $attrArray[$attribute->getAttributeCode()]=$value;   
                           }

                        }
                       $catCollection = $productGrouped->getCategoryCollection();
                       $categs = $catCollection->exportToArray();
                       $cateHolder = array();
                       foreach ($categs as $cat) {
                               $cateName = Mage::getModel('catalog/category')->load($cat['entity_id']);
                               $name =$cateName->getName();
                               $id = $cateName->getEntityId();
                               $pid = $cateName->getParent_id();
                               if ($pid == 1) {
                                   $pid = 0;
                               }
                               if(!empty($name)){
                                $cateHolder[] = array("cat_id"=>$id,"cat_name" => $name, "parent_cat_id" => $pid);
                               }
                          }

                        $actionData[$i]['product_group_id']=$pGid;
                        $actionData[$i]['product_group_name']=$pGname;
                        $actionData[$i]['id'] =$productId;
                        $actionData[$i]['name'] =$productName;
                        $actionData[$i]['sku'] = $productGrouped->getSku();
                        $actionData[$i]['price'] = $productGrouped->getFinalPrice();
                        $actionData[$i]['currency'] = Mage::app()->getStore()->getBaseCurrencyCode();
                        $actionData[$i]['image_url'] = $productGrouped->getImageUrl();
                        $actionData[$i]['product_url'] = $productGrouped->getProductUrl();
                        $actionData[$i]['quantity'] =$qty;
                        $actionData[$i]['categories'] = $cateHolder;
                        $actionData[$i]['specs']=$attrArray;
                        $i++;
                    }
                }else if($product->getTypeID() == 'configurable'){
                
                 $pGid=$product->getId();
                 $pGname=$product->getName();
                 $assProduct=Mage::getModel("catalog/product")->loadByAttribute('sku',$product->getSku());
                 $productId = $assProduct->getId();
                 $productName = $assProduct->getName();
                 $attributes = $assProduct->getAttributes();
                   foreach ($attributes as $attribute) { 
                       if($attribute->getIsUserDefined()){
                        $acode=$attribute->getAttributeCode();
                        if($acode=="size" || $acode=="color" ||$acode=="pro_categories"){
                        $attributeLabel = $attribute->getFrontendLabel();
                        $value = $attribute->getFrontend()->getValue($assProduct);
                        $attrArray[$attribute->getAttributeCode()]=$value;  
                        }
                       }

                    }
               
                $catCollection = $product->getCategoryCollection();
                $categs = $catCollection->exportToArray();
                $cateHolder = array();
                foreach ($categs as $cat) {
                    $cateName = Mage::getModel('catalog/category')->load($cat['entity_id']);
                    $name =$cateName->getName();
                    $id = $cateName->getEntityId();
                    $pid = $cateName->getParent_id();
                    if ($pid == 1) {
                        $pid = 0;
                    }
                    if(!empty($name)){
                     $cateHolder[] = array("cat_id"=>$id,"cat_name" => $name, "parent_cat_id" => $pid);
                    }
                }
               
                $actionData[0]['product_group_id']=$pGid;
                $actionData[0]['product_group_name']=$pGname;
                $actionData[0]['id'] =$productId;
                $actionData[0]['name'] =$productName;
                $actionData[0]['sku'] = $product->getSku();
                $actionData[0]['price'] = $product->getFinalPrice();
                $actionData[0]['currency'] = Mage::app()->getStore()->getBaseCurrencyCode();
                $actionData[0]['image_url'] = $product->getImageUrl();
                $actionData[0]['product_url'] = $product->getProductUrl();
                $actionData[0]['quantity'] = (int) $product->getQty();
                $actionData[0]['categories'] = $cateHolder;
                $actionData[0]['specs']=$attrArray;
                }else if($product->getTypeID() == 'bundle'){
          
           $mproduct=$product;
           $options = $product->getTypeInstance(true)->getOrderOptions($product);
           
           $optionIds = array_keys($options['info_buyRequest']['bundle_option']);
           $productName = $product->getName();
           $assproduct=array();
           $collection = $product->getTypeInstance(true)->getSelectionsCollection($optionIds, $product);
           $selection_map = array();
           foreach ($collection as $item) {
                $idata=$item->getData();
                if(!isset($selection_map[$idata['option_id']])) {
                   $selection_map[$idata['option_id']] = array();
                 }
                $selection_map[$idata['option_id']][$idata['selection_id']] = $idata;  
             } 
             //mail("rohit@getamplify.com","Bundle product selection map", json_encode($selection_map));
            foreach($options['info_buyRequest']['bundle_option'] as $op => $sel) {
            
              if(is_array($sel)){
                foreach($sel as $km){
                $assproduct[] = $selection_map[$op][$km]['product_id'];
               }   
              }else{
                $assproduct[] = $selection_map[$op][$sel]['product_id'];
               }
              
            }
           
            $pGid=0;
            $pGname=$productName;
            $i=0;
           if(count($assproduct)){
            foreach($assproduct as $pdata){
            $product= Mage::getModel('catalog/product')->load($pdata);
            $attributes = $product->getAttributes();
              foreach ($attributes as $attribute) { 
                  if($attribute->getIsUserDefined()){
                   $acode=$attribute->getAttributeCode();
                  
                   $attributeLabel = $attribute->getFrontendLabel();
                   $value = $attribute->getFrontend()->getValue($product);
                   if (!$product->hasData($attribute->getAttributeCode())) {
                                $value ="";
                        } elseif ((string)$value == '') {
                            $value = "";
                        } elseif ($attribute->getFrontendInput() == 'price' && is_string($value)) {
                            $value =$value;
                        }

                        if (is_string($value) && strlen($value)) {
                          $attrArray[$attribute->getAttributeCode()]=$value;   
                        }
                   
                  }

               }
                $catCollection = $product->getCategoryCollection();
                $categs = $catCollection->exportToArray();
                $cateHolder = array();
                  foreach ($categs as $cat) {
                        $category = Mage::getModel('catalog/category')->load($cat['entity_id']);
                        $name =$category->getName();
                        $id = $category->getEntityId();
                        $pid = $category->getParent_id();
                        if ($pid == 1) {
                            $pid = 0;
                        }
                        if(!empty($name)){
                         $cateHolder[] = array("cat_id"=>$id,"cat_name" => $name, "parent_cat_id" => $pid);
                        }
            
                    }
                $actionData[$i]['product_group_name']=$pGname;
                $actionData[$i]['id'] = $product->getId();
                $actionData[$i]['name'] = $product->getName();
                $actionData[$i]['sku'] =  $product->getSku();
                $actionData[$i]['price'] = $product->getFinalPrice();
                $actionData[$i]['currency'] =  Mage::app()->getStore()->getBaseCurrencyCode();
                $actionData[$i]['image_url'] =  $product->getImageUrl();
                $actionData[$i]['product_url'] = $product->getProductUrl(); 
                if($mproduct->getQty()){
                  $actionData[$i]['quantity']= (int) $mproduct->getQty();  
                }else{
                 $actionData[$i]['quantity']=1;   
                }
                $actionData[$i]['categories'] = $cateHolder;
                $i++;
              }

            }else{
                
            }
              $subprice = (float) $mproduct->getQty() * $mproduct->getFinalPrice();
        }else{
                 $productName = $product->getName();
                 $pGid=0;
                 $pGname="";
                    $attributes = $product->getAttributes();
                   foreach ($attributes as $attribute) { 
                       if($attribute->getIsUserDefined()){
                        $acode=$attribute->getAttributeCode();
                        if($acode=="size" || $acode=="color" ||$acode=="pro_categories"){
                        $attributeLabel = $attribute->getFrontendLabel();
                        $value = $attribute->getFrontend()->getValue($product);
                        $attrArray[$attribute->getAttributeCode()]=$value;  
                        } 
                       }

                    }
                $catCollection = $product->getCategoryCollection();
                $categs = $catCollection->exportToArray();
                $cateHolder = array();
                foreach ($categs as $cat) {
                    $cateName = Mage::getModel('catalog/category')->load($cat['entity_id']);
                    $name =$cateName->getName();
                    $id = $cateName->getEntityId();
                    $pid = $cateName->getParent_id();
                    if ($pid == 1) {
                        $pid = 0;
                    }
                    if(!empty($name)){
                     $cateHolder[] = array("cat_id"=>$id,"cat_name" => $name, "parent_cat_id" => $pid);
                    }
                }
               
                $actionData[0]['product_group_id']=$pGid;
                $actionData[0]['product_group_name']=$pGname;
                $actionData[0]['id'] =$productId;
                $actionData[0]['name'] =$productName;
                $actionData[0]['sku'] = $product->getSku();
                $actionData[0]['price'] = $product->getFinalPrice();
                $actionData[0]['currency'] = Mage::app()->getStore()->getBaseCurrencyCode();
                $actionData[0]['image_url'] = $product->getImageUrl();
                $actionData[0]['product_url'] = $product->getProductUrl();
                $actionData[0]['quantity'] = (int) $product->getQty();
                $actionData[0]['categories'] = $cateHolder;
                $actionData[0]['specs']=$attrArray;
               }
               
                $cart = Mage::getSingleton('checkout/cart');
                $subTotalPrice = $cart->getQuote()->getGrandTotal();
              
                $cartInfo["total"] =$subTotalPrice;
                $cartInfo["revenue"] = $subTotalPrice;
                $cartInfo['abandon_cart_url'] = Mage::getUrl('checkout/cart');
                $cartInfo['currency'] = Mage::app()->getStore()->getCurrentCurrencyCode();
                
                $actionDescription = array(
                    'activity_type' => 'add_to_cart',
                    'identifiers' => $this->getCustomerIdentity(),
                    'cart_info' => $cartInfo,
                    'products' => $actionData
                );
             
              $this->amplify->customer_action($actionDescription);
            }
        } catch (Exception $ex) {
            
        }
    }
public function getAmplify_cartUpdate(Varien_Event_Observer $observer) {
        try {
           
            if ($this->verified) {
                $i = 0;
                $subdiff = 0;
                $actionData = array();
                foreach ($observer->getCart()->getQuote()->getAllVisibleItems() as $product) {
                   
                    if ($product->hasDataChanges()) {
                        $productId = $product->getProductId();
                        $newProduct=Mage::getModel("catalog/product")->loadByAttribute('sku',$product->getSku());
                        if($newProduct->getTypeID()!="bundle"){
                        $productName = $newProduct->getName();
                        $attributes = $newProduct->getAttributes();
                        $attrArray=array();
                        foreach ($attributes as $attribute) { 
                                if($attribute->getIsUserDefined()){
                                $acode=$attribute->getAttributeCode();
                                if($acode=="size" || $acode=="color" ||$acode=="pro_categories"){
                                $attributeLabel = $attribute->getFrontendLabel();
                                $value = $attribute->getFrontend()->getValue($newProduct);
                                $attrArray[$attribute->getAttributeCode()]=$value;  
                                } 
                               }
                          }
                        $actionData[$i]['product_group_id']=$productId;
                        $actionData[$i]['product_group_name']=$product->getName();
                        $actionData[$i]['id'] = $newProduct->getId();
                        $actionData[$i]['name'] = $productName;
                        $actionData[$i]['sku'] = $product->getSku();
                        $actionData[$i]['price'] = $product->getPrice();
                        $actionData[$i]['currency'] = Mage::app()->getStore()->getBaseCurrencyCode();
                        
                        $actionData[$i]['image_url'] = $newProduct->getImageUrl();
                        $actionData[$i]['product_url'] = $newProduct->getProductUrl();
                        $actionData[$i]['specs']=$attrArray;
                        $oldQty = (int) $product->getOrigData('qty');
                        $newQty = (int) $product->getQty();
                        $qtyDiff = 0;
                        $subdiff = $subdiff + ($newQty - $oldQty) * $product->getPrice();
                        $actionData[$i]['quantity'] = (int) $product->getQty();
                        $i++;
                        }else{
                        $pdata=$product->getData();
                        $removeArray=$pdata['qty_options'];
                            foreach($removeArray as $radat=>$val){
                             $newproduct=Mage::getModel("catalog/product")->load($radat);
                             $actionData[$i]['id'] =$newproduct->getId();
                             $actionData[$i]['name'] =$newproduct->getName();
                             $actionData[$i]['sku'] = $newproduct->getSku();
                             $actionData[$i]['price'] = $newproduct->getPrice();
                             $actionData[$i]['currency'] = Mage::app()->getStore()->getBaseCurrencyCode();
                             $oldQty = (int) $product->getOrigData('qty');
                             $newQty = (int) $product->getQty();
                             $qtyDiff = 0;
                             $subdiff = $subdiff + ($newQty - $oldQty) * $newproduct->getPrice();
                             $actionData[$i]['quantity'] = (int) $product->getQty();
                             $i++;
                            }
                        }
                    }
                }
                //$subdiff=Mage::helper('core')->currency($subdiff , false, false);
                $cart = Mage::getSingleton('checkout/cart');
                $subTotalPrice = $cart->getQuote()->getGrandTotal(); 
                $totals = Mage::getSingleton('checkout/cart')->getQuote()->getTotals();

                
                $cartInfo["total"] =$subTotalPrice + $subdiff;
                $cartInfo["revenue"] = $subTotalPrice + $subdiff;
                $cartInfo['abandon_cart_url'] = Mage::getUrl('checkout/cart');
                $cartInfo['currency'] = Mage::app()->getStore()->getCurrentCurrencyCode();
                
                $actionDescription = array(
                    'activity_type' => 'update_cart',
                    'identifiers' => $this->getCustomerIdentity(),
                    'cart_info' => $cartInfo,
                    'products' => $actionData
                );
               
               $this->amplify->customer_action($actionDescription);
            }
        } catch (Exception $ex) {
            
        }
    }
    
    public function getAmplifyEventReview($evnt) {
        try {
            if ($this->verified) {

                $event = $evnt->getEvent();
                $action = $event->getControllerAction();
                $product = $evnt->getProduct();
               
                $actionData = array();
                $actionData[0]['id'] = $product->getId();
                $actionData[0]['name'] = $product->getName();
                $actionData[0]['sku'] = $product->getSku();
                $actionData[0]['price'] = $product->getPrice();
                $actionData[0]['currency'] = Mage::app()->getStore()->getBaseCurrencyCode();
                $actionData[0]['image_url'] = $product->getImageUrl();
                $actionData[0]['product_url'] = $product->getProductUrl();
              
                
                 $actionDescription = array(
                    'activity_type' => 'review',
                    'identifiers' => $this->getCustomerIdentity(),
                    'products' => $actionData
                );
                
                $res = $this->amplify->customer_action($actionDescription);
            }
        } catch (Exception $ex) {
            
        }
    }

    public function getAmplifyEventVote($evnt) {
        
    }

    public function getAmplifyEventCustomerLogout($evnt) {
        try {
            if ($this->verified) {

                $this->event('customer_logout');
            }
        } catch (Exception $ex) {
            
        }
    }

    public function getAmplifyEventCustomerLogin($evnt) {
        try {
            if ($this->verified) {
                $data=array();
                $c = Mage::getSingleton('customer/session')->getCustomer();
                $customer = Mage::getModel('customer/customer')->load($c->getId());
                $email = $customer->getEmail();
                $custName = $customer->getFirstname();
                $custName = $custName . " " . $customer->getLastname();
                
                $person = array();
                $customerAddressId = $c->getDefaultShipping();
                if ($customerAddressId) {
                    $customer = Mage::getModel('customer/address')->load($customerAddressId);
                }



                if (is_object($customer)) {
                    $person['firstname'] = $customer->getFirstname();
                    $person['lastname'] = $customer->getLastname();
                    $person['postcode'] = $customer->getPostcode();
                    $person['fax'] = $customer->getfax();
                    $person['company'] = $customer->getCompany();
                    $person['street'] = $customer->getStreetFull();
                    
                    $data['email']=$email;
                    $data['phone'] = $customer->getTelephone();
                    $data['customer_id'] = $customer->getCustomerId();
                    try {
                      $data=  array_filter($data);
                      $this->amplify->identify($data);
                     } catch (Exception $ex) {
                    }
                    $person = array_filter($person);
                    $properties['update']=$person;
                    $res = $this->amplify->userProperties($data, $properties);
                }else{
                  try {
                     $data['email']=$email;
                     $this->amplify->identify($data);
                   } catch (Exception $ex) {

                   }
                }
                
                $this->amplify->event($data, "customer_login");
            }
        } catch (Exception $ex) {
            
        }
    }

    public function getAmplifyEventNewsletter($evnt) {
        try {
            if ($this->verified) {

                $subscriber = $evnt->getEvent()->getSubscriber();
                $identity['email']=$subscriber->subscriber_email;
                try{
                $this->amplify->identify($identity);
                }catch(Exception $ex){
                    
                }

                if ($subscriber->getStatus() == Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED) {
                   $this->event('subscribed_to_newsletter');
                } elseif ($subscriber->getStatus() == Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED) {
                   $this->event('unsubscribed_from_newsletter');
                }
            }
        } catch (Exception $ex) {
            
        }
    }

    public function getCustomerIdentity($true = 1) {
        try {
           $data=array();
            if (Mage::getSingleton('customer/session')->isLoggedIn()) {
                $c = Mage::getSingleton('customer/session')->getCustomer();
                $customer = Mage::getModel('customer/customer')->load($c->getId());
                $email = $customer->getEmail();
                $data['email']=$email;
                $data['customer_id']=$c->getId();
            } else {
               //$data = json_decode(base64_decode(Mage::getModel('core/cookie')->get('_ampUSER')),true);
            }
            if ($true)
                return $data;
        } catch (Exception $ex) {
            
        }
    }

    public function getAmplifyEventOrder($evnt) {
        try {
            if ($this->verified) {
                $this->event('place_order_clicked');
            }
        } catch (Exception $ex) {
            
        }
    }

    public function getamplifyEventCustomerSave($evnt) {
        
    }

    public function getCustomereventInfo($customer) {
        try {
            if ($this->verified) {

                $person = array();
                $person['email'] = $customer->getEmail();
                $person['customer_id'] = $customer->getId();
                $person['first_name'] = $customer->getFirstname();
                $person['last_name'] = $customer->getLastname();
                $person['created'] = $customer->getCreatedAt();
//                $person['unique_id'] = $customer->getEmail();

                return $person;
            }
        } catch (Exception $ex) {
            
        }
    }

    public function eventCustomerFromCheckout($evnt) {
        
    }

    public function getAmplifyEventCustomerRegisterSuccess($evnt) {
        try {
            if ($this->verified) {

                $customer = $evnt->getCustomer();
                $person = array();
                $person = $this->getCustomereventInfo($customer);
                $identifyData['email']=$person['email'];
                $identifyData['customer_id']=$person['customer_id'];
                $this->amplify->identify($identifyData, $person['first_name']);
                $properties['update']=array("first_name"=>$person['first_name'],'last_name'=>$person['first_name']);
                $this->amplify->event($identifyData, $properties);
				$this->amplify->event($identifyData,"Signup");
            }
        } catch (Exception $ex) {
            
        }
    }

    public function getAmplifyEventCoupon($evnt) {

        try {
            if ($this->verified) {

                $action = $evnt->getEvent()->getControllerAction();
                $coupon_code = trim($action->getRequest()->getParam('coupon_code'));
                $oCoupon = Mage::getModel('salesrule/coupon')->load($coupon_code, 'code');
                $oRule = Mage::getModel('salesrule/rule')->load($oCoupon->getRuleId());
                $coupon_code = $oRule->getCoupon_code();

                if (isset($coupon_code) && !empty($coupon_code)) {
                    $this->event('coupon_success');
                } else {
                 $this->event('coupon_unsuccess');                }
                return $this;
            }
        } catch (Exception $ex) {
            
        }
    }

    public function event($event) {
        try {
            if ($this->verified) {
             $this->amplify->event($this->getCustomerIdentity(), $event);
            }
        } catch (Exception $ex) {
            
        }
    }

    public function eventPerson($person, $additional = array()) {
        
    }

    public function event_revenue($identifier, $revenue) {
        
    }

    public function getAmplifyEventWishlist($evnt) {
        try {
            if ($this->verified) {

//                $event = $evnt->getEvent();
//                $eventname = $event->getName();
//                $product = $event->getProduct();
//                $actionData = array();
//                $actionData[0]['id'] = $product->getId();
//                $actionData[0]['name'] = $product->getName();
//                $actionData[0]['sku'] = $product->getSku();
//                $actionData[0]['price'] = $product->getPrice();
//                $actionData[0]['currency'] = Mage::app()->getStore()->getBaseCurrencyCode();
//                
//                $actionData[0]['image_url'] = $product->getImageUrl();
//                $actionData[0]['product_url'] = $product->getProductUrl();
//
//                $actionDescription = array(
//                    'activity_type' => 'add_to_wishlist',
//                    'identifiers' => $this->getCustomerIdentity(),
//                    'products' => $actionData
//                );
//                $res = $this->amplify->customer_action($actionDescription);
            }
        } catch (Exception $ex) {
            
        }
    }

    /**
     * @author Rohit Tyagi
     * @desc verify key and secret while saving them
     */
    public function getAmplifyOrderSuccessPageView(Varien_Event_Observer $evnt) {
        try {
            if ($this->verified) {
               $orderIds = $evnt->getData('order_ids');
               if (empty($orderIds) || !is_array($orderIds)) {
                 $this->event('Order Id Missing');
               }else{
               foreach($orderIds as $_orderId){
                $order = Mage::getModel("sales/order")->load($_orderId);
                $order_id = $order->getIncrementId();
                $person = array();
                $data=array();
                $billingCity="";
                $customerAddressId = Mage::getSingleton('customer/session')->getCustomer()->getDefaultShipping();
                if ($customerAddressId) {
                     $customer = $order->getShippingAddress();
                     $billingAddress=$order->getBillingAddress();
                    if (is_object($customer)) {
                        $data['email']=$customer->getEmail();
                        $data['phone'] = $customer->getTelephone();
                        $data['customer_id'] = $customer->getCustomerId();
                        $person['firstname'] = $customer->getFirstname();
                        $person['lastname'] = $customer->getLastname();
                        $person['postcode'] = $customer->getPostcode();
                        $person['fax'] = $customer->getfax();
                        $person['company'] = $customer->getCompany();
                        $person['street'] = $customer->getStreetFull();
                        $person['city'] = $customer->getCity();
                        if(is_object($billingAddress)){
                         $billingCity= $billingAddress->getCity();  
                        }
                       
                        $person['dob'] = $order->getCustomerDob();
                        $person['ip'] = $order->getRemoteIp();
                        $person['gender']=$order->getCustomerGender();
                    }
                   try {
                      $this->amplify->identify($data);
                     } catch (Exception $ex) {
                    }
                    $person = array_filter($person);
                    $properties['update']=$person;
                    if($billingCity){
                       $properties['append']=array("billingcity"=>$billingCity);
                      }
                    $data=  array_filter($data);
                    $res = $this->amplify->userProperties($data, $properties);
                    
                } else {
                    $customer = $order->getShippingAddress();
                    $billingAddress=$order->getBillingAddress();
                    if (is_object($customer)) {
                        $data['email']=$customer->getEmail();
                        $data['phone'] = $customer->getTelephone();
                        $data['customer_id'] = $customer->getCustomerId();
                        $person['firstname'] = $customer->getFirstname();
                        $person['lastname'] = $customer->getLastname();
                        $person['postcode'] = $customer->getPostcode();
                        $person['fax'] = $customer->getfax();
                        $person['company'] = $customer->getCompany();
                        $person['street'] = $customer->getStreetFull();
                        $person['city'] = $customer->getCity();
                        if(is_object($billingAddress)){
                         $billingCity= $billingAddress->getCity();  
                        }
                       
                        $person['dob'] = $order->getCustomerDob();
                        $person['ip'] = $order->getRemoteIp();
                        $person['gender']=$order->getCustomerGender();
                        try {
                         $this->amplify->identify($data);
                          } catch (Exception $ex) {
                          }
                       $person = array_filter($person);
                       $properties['update']=$person;
                       if($billingCity){
                       $properties['append']=array("billingcity"=>$billingCity);
                       }
                       $data=  array_filter($data);
                      $res = $this->amplify->userProperties($data, $properties);
                    }
                }

                $items = $order->getAllVisibleItems();
                $itemcount = count($items);
                
                $i = 0;
                $actionData = array();

                foreach ($items as $itemId => $item) {
                      $product = $item;
                      $productId = $product->getProductId();
                      $newProduct=Mage::getModel("catalog/product")->loadByAttribute('sku',$product->getSku());
                      $product = Mage::getModel('catalog/product')->load($product->getProductId());
                      $productName = $newProduct->getName();
                      $attributes = $newProduct->getAttributes();
                      $attrArray=array();
                        foreach ($attributes as $attribute) { 
                                if($attribute->getIsUserDefined()){
                                $acode=$attribute->getAttributeCode();
                                if($acode=="size" || $acode=="color" ||$acode=="pro_categories"){
                                $attributeLabel = $attribute->getFrontendLabel();
                                $value = $attribute->getFrontend()->getValue($newProduct);
                                $attrArray[$attribute->getAttributeCode()]=$value;  
                                } 
                               }
                          }
                    $cateHolder = array();
                    try{
                        $catCollection = $product->getCategoryCollection();
                        $categs = $catCollection->exportToArray();
                        foreach ($categs as $cat) {
                            $cateName = Mage::getModel('catalog/category')->load($cat['entity_id']);
                            $name = $cateName->getName();
                            $id = $cateName->getEntityId();
                            $pid = $cateName->getParent_id();
                            if ($pid == 1) {
                                $pid = 0;
                            }
                            if(!empty($name)){
                             $cateHolder[] = array_filter(array("cat_id"=>$id,"cat_name" => $name, "parent_cat_id" => $pid));
                            }
                        }
                    }catch(Exception $e){
                        
                    }
                    $pprice=$newProduct->getFinalPrice();
                    if($pprice==0){
                     $pprice=$item->getPrice();  
                    }
                    $gid=$productId;
                    $gname=$product->getName();
                    if($productId==$newProduct->getId()){
                        $gid=0;
                        $gname="";
                    }
                    $actionData[$i]['product_group_id']=$gid;
                    $actionData[$i]['product_group_name']=$gname;
                    $actionData[$i]['id'] = $newProduct->getId();
                    $actionData[$i]['name'] = $productName;
                    $actionData[$i]['sku'] = $product->getSku();
                    $actionData[$i]['price'] = $pprice;
                    $actionData[$i]['currency'] = Mage::app()->getStore()->getBaseCurrencyCode();
                    $actionData[$i]['image_url'] = $newProduct->getImageUrl();
                    $actionData[$i]['product_url'] = $newProduct->getProductUrl();
                    $actionData[$i]['brandname'] = $product->getResource()->getAttribute('manufacturer') ? $product->getAttributeText('manufacturer') : false;
                    $actionData[$i]['quantity'] = (int) $item->getQtyOrdered();
                    $actionData[$i]['categories'] = $cateHolder;
                    $actionData[$i]['specs']=$attrArray;
                   
                    $i++;
                }

           
                    $cart_id=Mage::getModel('core/cookie')->get('_ampCart');
                
                 
                $TotalPrice = $order->getGrandTotal();
                $totalShippingPrice = $order->getShippingAmount();
                $TotalPrice = $TotalPrice;
                $subTotalPrice = $order->getSubtotal();
                
                $orderInfo["revenue"]  = $subTotalPrice - abs($order->getDiscountAmount());
                $orderInfo["total"]    = $TotalPrice;
                $orderInfo["shipping"] = $totalShippingPrice;
                $orderInfo['order_id'] = $order->getIncrementId();
                $orderInfo['coupon']= $order->getCouponCode();
                $orderInfo['discount'] = abs($order->getDiscountAmount());
               
                $orderInfo['currency'] = $order->getOrderCurrencyCode();
                $orderInfo['status'] = 'completed';
                
                $orderInfo['tax'] = $order->getShippingTaxAmount();
                if(!is_object($order->getPayment())){
                   $orderInfo['payment_method']="Custom";
                 }else{
                  $orderInfo['payment_method'] = $order->getPayment()->getMethodInstance()->getCode();
                 }
             
                $actionDescription = array(
                    'activity_type' => 'purchase',
                    'identifiers' => $data,
                    'order_info' => $orderInfo,
                    'products' => $actionData
                );
              
                $res = $this->amplify->customer_action($actionDescription);
                
              }
             }
            }
        } catch (Exception $ex) {
            $this->event('error_one');
          
        }
    }

    public function getAmplifyOrderSaveSuccess(Varien_Event_Observer $evnt) {
         try {
           
            if ($this->verified) {
               
               $order_id = $evnt->getEvent()->getOrder()->getId() ;
               $order_no = $evnt->getEvent()->getOrder()->getIncrementId() ;
               $order = Mage::getModel("sales/order")->load($order_id);
               $person=array();
               $paymentmethod=$order->getPayment()->getMethodInstance()->getCode();
               $orderstatus=$order->getStatusLabel();
               $billingCity="";
                $customerAddressId = Mage::getSingleton('customer/session')->getCustomer()->getDefaultShipping();
                if ($customerAddressId) {
                 
                   $customer = $order->getShippingAddress();
                   $billingAddress=$order->getBillingAddress();
                      if (is_object($customer)) {
                        $data['email']=$customer->getEmail();
                        $data['phone'] = $customer->getTelephone();
                        $data['customer_id'] = $customer->getCustomerId();
                        $person['firstname'] = $customer->getFirstname();
                        $person['lastname'] = $customer->getLastname();
                        $person['postcode'] = $customer->getPostcode();
                        $person['fax'] = $customer->getfax();
                        $person['company'] = $customer->getCompany();
                        $person['street'] = $customer->getStreetFull();
                        $person['city'] = $customer->getCity();
                        if(is_object($billingAddress)){
                         $billingCity= $billingAddress->getCity();  
                        }
                       
                        $person['dob'] = $order->getCustomerDob();
                        $person['ip'] = $order->getRemoteIp();
                        $person['gender']=$order->getCustomerGender();
                         }
                         try {
                           $this->amplify->identify($data);
                          } catch (Exception $ex) {
                      }
                    $person = array_filter($person);
                    $properties['update']=$person;
                    if($billingCity){
                       $properties['append']=array("billingcity"=>$billingCity);
                    }
                    $data=  array_filter($data);
                    $res = $this->amplify->userProperties($data, $properties);
                } else {
                    $customer = $order->getShippingAddress();
                    $billingAddress=$order->getBillingAddress();
                    if (is_object($customer)) {
                        $data['email']=$customer->getEmail();
                        $data['phone'] = $customer->getTelephone();
                        $data['customer_id'] = $customer->getCustomerId();
                        $person['firstname'] = $customer->getFirstname();
                        $person['lastname'] = $customer->getLastname();
                        $person['postcode'] = $customer->getPostcode();
                        $person['fax'] = $customer->getfax();
                        $person['company'] = $customer->getCompany();
                        $person['street'] = $customer->getStreetFull();
                        $person['city'] = $customer->getCity();
                        if(is_object($billingAddress)){
                         $billingCity= $billingAddress->getCity();  
                        }
                       
                        $person['dob'] = $order->getCustomerDob();
                        $person['ip'] = $order->getRemoteIp();
                        $person['gender']=$order->getCustomerGender();
                        try {
                         $this->amplify->identify($data);
                          } catch (Exception $ex) {
                          }
                       $person = array_filter($person);
                       $properties['update']=$person;
                       if($billingCity){
                        $properties['append']=array("billingcity"=>$billingCity);
                       }
                       $data=  array_filter($data);
                      
                      $res = $this->amplify->userProperties($data, $properties);
                    }
                }
                $items = $order->getAllVisibleItems();
                $itemcount = count($items);
                $i = 0;
                $actionData = array();

                foreach ($items as $itemId => $item) {
                     $product = $item;
                     $productId = $product->getProductId();
                     $newProduct=Mage::getModel("catalog/product")->loadByAttribute('sku',$product->getSku());
                     $product = Mage::getModel('catalog/product')->load($product->getProductId());
                     $productName = $newProduct->getName();
                     $attributes = $newProduct->getAttributes();
                     $attrArray=array();
                        foreach ($attributes as $attribute) { 
                                if($attribute->getIsUserDefined()){
                                $acode=$attribute->getAttributeCode();
                                if($acode=="size" || $acode=="color" ||$acode=="pro_categories"){
                                $attributeLabel = $attribute->getFrontendLabel();
                                $value = $attribute->getFrontend()->getValue($newProduct);
                                $attrArray[$attribute->getAttributeCode()]=$value;  
                                } 
                               }
                          }
                    
                    $categoryIds = $product->getCategoryIds();
                    $cateHolder = array();
                    
                    foreach ($categoryIds as $cat) {
                    $cateName = Mage::getModel('catalog/category')->load($cat['entity_id']);
                    $name=$cateName->getName();
                    $id=$cateName->getEntityId();
                    $pid=$cateName->getParent_id();
                    if($pid==1){
                        $pid=0;
                    }
                     if(!empty($name)){
                             $cateHolder[] = array_filter(array("cat_id"=>$id,"cat_name" => $name, "parent_cat_id" => $pid));
                     }
                   }
                    $pprice=$newProduct->getPrice();
                    if($pprice==0){
                     $pprice=$item->getPrice();  
                    }
                    $gid=$productId;
                    $gname=$product->getName();
                    if($productId==$newProduct->getId()){
                        $gid=0;
                        $gname="";
                    }
                    $actionData[$i]['product_group_id']=$gid;
                    $actionData[$i]['product_group_name']=$gname;
                    $actionData[$i]['id'] = $newProduct->getId();
                    $actionData[$i]['name'] = $productName;
                    $actionData[$i]['sku'] = $product->getSku();
                    $actionData[$i]['price'] = $pprice;
                    $actionData[$i]['currency'] = Mage::app()->getStore()->getBaseCurrencyCode();
                    $actionData[$i]['image_url'] = $newProduct->getImageUrl();
                    $actionData[$i]['product_url'] = $newProduct->getProductUrl();
                    $actionData[$i]['brandname'] = $newProduct->getResource()->getAttribute('manufacturer') ? $product->getAttributeText('manufacturer') : false;
                    $actionData[$i]['quantity'] = (int) $item->getQtyOrdered();
                    $actionData[$i]['categories'] = $cateHolder;
                    $actionData[$i]['specs']=$attrArray;
                    $i++;
                }

                $cart = Mage::getSingleton('checkout/cart');
                $TotalPrice = $order->getGrandTotal();
                $totalShippingPrice = $order->getShippingAmount();
                $subTotalPrice = $order->getSubtotal();
                $orderInfo["revenue"] = $subTotalPrice-abs($order->getDiscountAmount());
                $orderInfo["total"] = $TotalPrice;
                $orderInfo["shipping"] = $totalShippingPrice;
                $orderInfo['order_id'] = $order_no;
                $orderInfo['coupon'] = $order->getCouponCode();
                $orderInfo['discount'] = abs($order->getDiscountAmount());
                $orderInfo['currency'] = $order->getOrderCurrencyCode();
                $orderInfo['status'] = $order->getStatusLabel();
                $orderInfo['tax'] = $order->getShippingTaxAmount();
                $orderInfo['payment_method']=$order->getPayment()->getMethodInstance()->getCode();
                $actionDescription = array(
                    'activity_type' => 'order_placed',
                    'identifiers' => $data,
                    'order_info' => $orderInfo,
                    'products' => $actionData
                );
             
                $res = $this->amplify->customer_action($actionDescription);
               
            }
           
        } catch (Exception $ex) {
           $this->event('error_two');
          
         }
    }

    public function getAmplify_checkout_allow_guest($evnt) {
        try {
            if ($this->verified) {
                $getquote = $evnt->getQuote();
                $data = array_filter($getquote->getData());
                if(isset($data['customer_email']) && $data['customer_email']!=""){
                Mage::getModel('core/cookie')->set('amplify_email', $data['customer_email']);
                $person = array();
                $person['webId'] = Mage::app()->getWebsite()->getId();
                $person['storeId'] = Mage::app()->getStore()->getId();
                $person['firstName'] = $data['customer_firstname'];
                $person['lastName'] = $data['customer_lastname'];
                $person = array_filter($person);
                $identifierData['email']=$data['customer_email'];
                $identifierData['customer_id']="";
                $identifierData['phone']="";
                $this->amplify->identify($identifierData);
                $properties['update']=$person;
                $res = $this->amplify->userProperties($identifierData, $properties);
                }
            }
        } catch (Exception $ex) {
            
        }
    }

    public function getAmplifyCatalog_product_save_after($observer) {
          try {
        $product = $observer->getProduct();
        $catCollection = $product->getCategoryCollection();

               $categs = $catCollection->exportToArray();
               $cateHolder = array();
               foreach ($categs as $cat) {
                   $cateName = Mage::getModel('catalog/category')->load($cat['entity_id']);
                   $name = $cateName->getName();
                   $id = $cateName->getEntityId();
                   $pid = $cateName->getParent_id();
                   if ($pid == 1) {
                       $pid = 0;
                   }
                   $cateHolder[] = array("cat_id"=>$id,"cat_name" => $name, "parent_cat_id" => $pid);
               }
           $actionData = array();
           $actionData[0]['id'] = $product->getId();
           $actionData[0]['name'] = $product->getName();
           $actionData[0]['sku'] = $product->getSku();
           $actionData[0]['price'] = $product->getPrice();
           $actionData[0]['currency'] = Mage::app()->getStore()->getBaseCurrencyCode();
           $actionData[0]['image_url'] = $product->getImageUrl();
           $actionData[0]['product_url'] = $product->getProductUrl(); 
           $actionData[0]['categories'] = $cateHolder;
           $actionDescription = array(
                    "identifiers" => $this->getCustomerIdentity(),
                    'products' => $actionData
            );
           $this->amplify->product_add($actionDescription);
           } catch (Exception $ex) {
            
        }
    }

    public function getAmplifyCatalog_product_delete_after_done($evnt) {
        
    }

    public function getAmplifyCatalogProductView(Varien_Event_Observer $evnt) {
        try {
            if ($this->verified) {
               
                $product = $evnt->getEvent()->getProduct();
                $catCollection = $product->getCategoryCollection();

                $categs = $catCollection->exportToArray();
                $cateHolder = array();
                foreach ($categs as $cat) {
                    $cateName = Mage::getModel('catalog/category')->load($cat['entity_id']);
                    $name = $cateName->getName();
                    $id = $cateName->getEntityId();
                    $pid = $cateName->getParent_id();
                    if ($pid == 1) {
                        $pid = 0;
                    }
                    $cateHolder[] = array("cat_id"=>$id,"cat_name" => $name, "parent_cat_id" => $pid);
                }


                $event = $evnt->getEvent();
                $action = $event->getControllerAction();
                $stock_data = $product->getIs_in_stock();
                $actionData = array();
                $price=$product->getFinalPrice();
                if($price==0){
                 $price=$product->getPrice();
                }
                if(!$price>0){
                    $price=0;
                }
                $actionData[0]['id'] = $product->getId();
                $actionData[0]['name'] = $product->getName();
                $actionData[0]['sku'] = $product->getSku();
                $actionData[0]['price'] = $price;
                $actionData[0]['currency'] = Mage::app()->getStore()->getBaseCurrencyCode();
                $actionData[0]['image_url'] = $product->getImageUrl();
                $actionData[0]['product_url'] = $product->getProductUrl(); 
                $actionData[0]['categories'] = $cateHolder;
                
             
               // $actionData[0]['discount'] = abs($product->getPrice() - $product->getFinalPrice());
                $actionDescription = array(
                    'activity_type' => 'view',
                    "identifiers" => $this->getCustomerIdentity(),
                    'products' => $actionData
                );
               
                $res = $this->amplify->customer_action($actionDescription);
            }
        } catch (Exception $ex) {
            
        }
    }
    
    public function getAmplifyCustomerAdressSaveAfter($evnt) {
        
    }

    public function getAmplifyCancelOrderItem($observer) {
        
    }
     public function salesOrderPaymentCancel($observer){
        try{
       
        $order = $observer->getOrder();
        $order_id = explode("-", $order->getIncrementId());
        $order = Mage::getModel("sales/order")->loadByIncrementId($order_id);
               
                $person = array();
                $data=array();
                
                    $customer = $order->getShippingAddress();
                    if (is_object($customer)) {
                        $data['email']=$customer->getEmail();
                        $data['phone'] = $customer->getTelephone();
                        $data['customer_id'] = $customer->getCustomerId();
                        $person['firstname'] = $customer->getFirstname();
                        $person['lastname'] = $customer->getLastname();
                        $person['postcode'] = $customer->getPostcode();
                        $person['fax'] = $customer->getfax();
                        $person['company'] = $customer->getCompany();
                        $person['street'] = $customer->getStreetFull();
                        try {
                         $this->amplify->identify($data);
                          } catch (Exception $ex) {
                          }
                       $person = array_filter($person);
                       $properties['update']=$person;
                       $data=  array_filter($data);
                      $res = $this->amplify->userProperties($data, $properties);
                    }
                

                $items = $order->getAllVisibleItems();
                $itemcount = count($items);
                
                $i = 0;
                $actionData = array();
               foreach ($items as $itemId => $item) {
                    $product = $item;
                     $productId = $product->getProductId();
                      $newProduct=Mage::getModel("catalog/product")->loadByAttribute('sku',$product->getSku());
                      $product = Mage::getModel('catalog/product')->load($product->getProductId());
                      $productName = $newProduct->getName();
                      $attributes = $newProduct->getAttributes();
                      $attrArray=array();
                        foreach ($attributes as $attribute) { 
                                if($attribute->getIsUserDefined()){
                                $acode=$attribute->getAttributeCode();
                                if($acode=="size" || $acode=="color" ||$acode=="pro_categories"){
                                $attributeLabel = $attribute->getFrontendLabel();
                                $value = $attribute->getFrontend()->getValue($newProduct);
                                $attrArray[$attribute->getAttributeCode()]=$value;  
                                } 
                               }
                          }
                    $cateHolder = array();
                    try{
                        $catCollection = $product->getCategoryCollection();
                        $categs = $catCollection->exportToArray();
                        foreach ($categs as $cat) {
                            $cateName = Mage::getModel('catalog/category')->load($cat['entity_id']);
                            $name = $cateName->getName();
                            $id = $cateName->getEntityId();
                            $pid = $cateName->getParent_id();
                            if ($pid == 1) {
                                $pid = 0;
                            }
                            if(!empty($name)){
                             $cateHolder[] = array_filter(array("cat_id"=>$id,"cat_name" => $name, "parent_cat_id" => $pid));
                            }
                        }
                    }catch(Exception $e){
                        
                    }
                    $pprice=$newProduct->getPrice();
                    if($pprice==0){
                     $pprice=$item->getPrice();  
                    }
                    $gid=$productId;
                    $gname=$product->getName();
                    if($productId==$newProduct->getId()){
                        $gid=0;
                        $gname="";
                    }
                    $actionData[$i]['product_group_id']=$gid;
                    $actionData[$i]['product_group_name']=$gname;
                    $actionData[$i]['id'] = $newProduct->getId();
                    $actionData[$i]['name'] = $productName;
                    $actionData[$i]['sku'] = $product->getSku();
                    $actionData[$i]['price'] = $pprice;
                    $actionData[$i]['currency'] = Mage::app()->getStore()->getBaseCurrencyCode();
                    $actionData[$i]['image_url'] = $newProduct->getImageUrl();
                    $actionData[$i]['product_url'] = $newProduct->getProductUrl();
                    $actionData[$i]['brandname'] = $product->getResource()->getAttribute('manufacturer') ? $product->getAttributeText('manufacturer') : false;
                    $actionData[$i]['quantity'] = (int) $item->getQtyOrdered();
                    $actionData[$i]['categories'] = $cateHolder;
                    $actionData[$i]['specs']=$attrArray;
                    $i++;
                }

                $TotalPrice = $order->getGrandTotal();
                $totalShippingPrice = $order->getShippingAmount();
                $TotalPrice = $TotalPrice;
                $subTotalPrice = $order->getSubtotal();
                
                $orderInfo["revenue"]  = $subTotalPrice - abs($order->getDiscountAmount());
                $orderInfo["total"]    = $TotalPrice;
                $orderInfo["shipping"] = $totalShippingPrice;
                $orderInfo['order_id'] = $order->getIncrementId();
                $orderInfo['coupon']= $order->getCouponCode();
                $orderInfo['discount'] = abs($order->getDiscountAmount());
               
                $orderInfo['currency'] = $order->getOrderCurrencyCode();
                $orderInfo['status'] = 'cancled';
                
                $orderInfo['tax'] = $order->getShippingTaxAmount();
                if(!is_object($order->getPayment())){
                   $orderInfo['payment_method']="Custom";
                 }else{
                  $orderInfo['payment_method'] = $order->getPayment()->getMethodInstance()->getCode();
                 }
             
                $actionDescription = array(
                    'activity_type' => 'order_refunded',
                    'identifiers' => $data,
                    'order_info' => $orderInfo,
                    'products' => $actionData
                );
                $res = $this->amplify->customer_action($actionDescription);
             
        } catch (Exception $ex) {
            $this->event('error_one');
        }
      
        
        
    }
    public function salesOrderPaymentRefund($observer){
        try{
        $payment = $observer->getPayment();
        $creditmemo = $observer->getCreditmemo();
        /** @var Mage_Sales_Model_Order $order */
        $order = $payment->getOrder();
        $order_id=$order->getIncrementId();
     
       $remainingAmountAfterRefund = $order->getBaseGrandTotal() - $order->getBaseTotalRefunded();
       $refundedTotal = $creditmemo->getBaseGrandTotal();
        $order = Mage::getModel("sales/order")->loadByIncrementId($order_id);
               
                $person = array();
                $data=array();
                
                    $customer = $order->getShippingAddress();
                    if (is_object($customer)) {
                        $data['email']=$customer->getEmail();
                        $data['phone'] = $customer->getTelephone();
                        $data['customer_id'] = $customer->getCustomerId();
                        $person['firstname'] = $customer->getFirstname();
                        $person['lastname'] = $customer->getLastname();
                        $person['postcode'] = $customer->getPostcode();
                        $person['fax'] = $customer->getfax();
                        $person['company'] = $customer->getCompany();
                        $person['street'] = $customer->getStreetFull();
                        try {
                         $this->amplify->identify($data);
                          } catch (Exception $ex) {
                          }
                       $person = array_filter($person);
                       $properties['update']=$person;
                       $data=  array_filter($data);
                      $res = $this->amplify->userProperties($data, $properties);
                    }
                

                $items = $order->getAllVisibleItems();
                $itemcount = count($items);
                
                $i = 0;
                $actionData = array();
               foreach ($items as $itemId => $item) {
                      $product = $item;
                      $productId = $product->getProductId();
                      $newProduct=Mage::getModel("catalog/product")->loadByAttribute('sku',$product->getSku());
                      $product = Mage::getModel('catalog/product')->load($product->getProductId());
                      $productName = $newProduct->getName();
                      $attributes = $newProduct->getAttributes();
                      $attrArray=array();
                        foreach ($attributes as $attribute) { 
                                if($attribute->getIsUserDefined()){
                                $acode=$attribute->getAttributeCode();
                                if($acode=="size" || $acode=="color" ||$acode=="pro_categories"){
                                $attributeLabel = $attribute->getFrontendLabel();
                                $value = $attribute->getFrontend()->getValue($newProduct);
                                $attrArray[$attribute->getAttributeCode()]=$value;  
                                } 
                               }
                          }
                    $cateHolder = array();
                    try{
                        $catCollection = $product->getCategoryCollection();
                        $categs = $catCollection->exportToArray();
                        foreach ($categs as $cat) {
                            $cateName = Mage::getModel('catalog/category')->load($cat['entity_id']);
                            $name = $cateName->getName();
                            $id = $cateName->getEntityId();
                            $pid = $cateName->getParent_id();
                            if ($pid == 1) {
                                $pid = 0;
                            }
                            if(!empty($name)){
                             $cateHolder[] = array_filter(array("cat_id"=>$id,"cat_name" => $name, "parent_cat_id" => $pid));
                            }
                        }
                    }catch(Exception $e){
                        
                    }
                    $pprice=$newProduct->getPrice();
                    if($pprice==0){
                     $pprice=$item->getPrice();  
                    }
                    $gid=$productId;
                    $gname=$product->getName();
                    if($productId==$newProduct->getId()){
                        $gid=0;
                        $gname="";
                    }
                    $actionData[$i]['product_group_id']=$gid;
                    $actionData[$i]['product_group_name']=$gname;
                    $actionData[$i]['id'] = $newProduct->getId();
                    $actionData[$i]['name'] = $productName;
                    $actionData[$i]['sku'] = $product->getSku();
                    $actionData[$i]['price'] = $pprice;
                    $actionData[$i]['currency'] = Mage::app()->getStore()->getBaseCurrencyCode();
                    $actionData[$i]['image_url'] = $newProduct->getImageUrl();
                    $actionData[$i]['product_url'] = $newProduct->getProductUrl();
                    $actionData[$i]['brandname'] = $product->getResource()->getAttribute('manufacturer') ? $product->getAttributeText('manufacturer') : false;
                    $actionData[$i]['quantity'] = (int) $item->getQtyOrdered();
                    $actionData[$i]['categories'] = $cateHolder;
                    $actionData[$i]['specs']=$attrArray;
                    $i++;
                }

                $TotalPrice = $order->getGrandTotal();
                $totalShippingPrice = $order->getShippingAmount();
                $TotalPrice = $TotalPrice;
                $subTotalPrice = $order->getSubtotal();
                
                $orderInfo["revenue"]  = $subTotalPrice - abs($order->getDiscountAmount());
                $orderInfo["total"]    = $TotalPrice;
                $orderInfo["shipping"] = $totalShippingPrice;
                $orderInfo['order_id'] = $order->getIncrementId();
                $orderInfo['coupon']= $order->getCouponCode();
                $orderInfo['discount'] = abs($order->getDiscountAmount());
               
                $orderInfo['currency'] = $order->getOrderCurrencyCode();
                $orderInfo['status'] = 'cancled';
                
                $orderInfo['tax'] = $order->getShippingTaxAmount();
                if(!is_object($order->getPayment())){
                   $orderInfo['payment_method']="Custom";
                 }else{
                  $orderInfo['payment_method'] = $order->getPayment()->getMethodInstance()->getCode();
                 }
             
                $actionDescription = array(
                    'activity_type' => 'order_refunded',
                    'identifiers' => $data,
                    'order_info' => $orderInfo,
                    'products' => $actionData
                );
               
                $res = $this->amplify->customer_action($actionDescription);
             
        } catch (Exception $ex) {
            $this->event('error_one');
        }

    }

    public function sendData() {
        
    }
    

}
