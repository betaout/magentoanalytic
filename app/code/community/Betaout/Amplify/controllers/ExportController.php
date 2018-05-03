<?php
class Betaout_Amplify_ExportController extends Mage_Core_Controller_Front_Action {

public function indexAction(){
  $key=Mage::getStoreConfig('betaout_amplify_options/settings/amplify_key');
  $projectId = Mage::getStoreConfig('betaout_amplify_options/settings/amplify_projectId');
 $options = Mage::getModel('sales/order_status')->getResourceCollection()->getData();  
 $sdata=array();
 $i=0;
 foreach ($options as $optionsdata){
     $sdata[$i]['value']=$optionsdata['status'];
     $sdata[$i]['label']=$optionsdata['label'];
     $i++;
 }
 $result=array("apiKey"=>$key,
               "projectId"=>$projectId,
               "status"=>$sdata,
               "responseCode"=>200);
 echo json_encode($result);  
}
public function orderAction(){
   try{
       $key=isset($_GET['apiKey'])?$_GET['apiKey']:"";
        $projectId=isset($_GET['projectId'])?$_GET['projectId']:"";
        if($key=="" || $projectId==""){
         $result=array("error"=>"Api key and ProjectId required","responseCode"=>500);
          echo json_encode($result);
          die();
        }
       $status=isset($_GET['status'])?$_GET['status']:"complete";
       $cpage=isset($_GET['pageNo'])?$_GET['pageNo']:1;
       $limit = isset($_GET['limit']) ? $_GET['limit'] : 50;
       $totalCount = isset($_GET['totalCount']) ? $_GET['totalCount'] : 0;
    if($totalCount){
        $orders = Mage::getModel('sales/order')->getCollection()
           ->addFieldToFilter('status', $status)
           ->addAttributeToSelect('entity_id');
         $count=$orders->Count();
         if(empty($count)){
          $count=0;
         }
         $result=array("total"=>$count,"cpage"=>0,"responseCode"=>200);  
       echo json_encode($result);
    }else{
      $orders = Mage::getModel('sales/order')->getCollection()
           ->addFieldToFilter('status', $status)
           ->addAttributeToSelect('entity_id')
           ->setPageSize($limit);
      $lpages = $orders->getLastPageNumber();
      $orders->setCurPage($cpage);
      $count=$orders->Count();
       $ordata=array();
       $j=0;
      if($count){
        foreach ($orders as $order)  {
                $orderId = $order->getId();
                $order = Mage::getModel("sales/order")->load($orderId);
                $order_id = $order->getIncrementId();
                $email=  $order->getData('customer_email');
                $data=array();
                $data['email']=$email;
                $customer = $order->getShippingAddress();
                 if (is_object($customer)) {
                 $data['email']=$customer->getEmail();
                 $data['phone'] = $customer->getTelephone();
                 $data['customer_id'] = $customer->getCustomerId();
                 }
                 $data= array_filter($data);
                 $items = $order->getAllVisibleItems();
                $itemcount = count($items);
                $i = 0;
                $actionData = array();

                foreach ($items as $itemId => $item) {
                    $product = $item;
                    $product = Mage::getModel('catalog/product')->load($product->getProductId());
                    $categoryIds = $product->getCategoryIds();
                    $cateHolder = array();

                    foreach ($categoryIds as $cat) {
                        $cateName = Mage::getModel('catalog/category')->load($cat['entity_id']);
                        $name = $cateName->getName();
                        $id = $cateName->getEntityId();
                        $pid = $cateName->getParent_id();
                        if ($pid == 1) {
                            $pid = 0;
                        }
                        if(!empty($name)){
                        $cateHolder[] = array("cat_id"=>$id,"cat_name" => $name, "parent_cat_id" => $pid);
                        }
                    }

                    $actionData[$i]['id'] = $product->getId();
                    $actionData[$i]['name'] = $product->getName();
                    $actionData[$i]['sku'] = $product->getSku();
                    $actionData[$i]['price'] = $product->getPrice();
                    $actionData[$i]['currency'] = Mage::app()->getStore()->getBaseCurrencyCode();
                    $actionData[$i]['image_url'] = $product->getImageUrl();
                    $actionData[$i]['product_url'] = $product->getProductUrl();
                    $actionData[$i]['brandname'] = $product->getResource()->getAttribute('manufacturer') ? $product->getAttributeText('manufacturer') : false;
                    $actionData[$i]['quantity'] = (int) $item->getQtyOrdered();
                    $actionData[$i]['categories'] = $cateHolder;
                    $i++;
                }

              
                $TotalPrice = $order->getGrandTotal();
                $totalShippingPrice = $order->getShippingAmount();
                $TotalPrice = $TotalPrice;
                $subTotalPrice = $order->getSubtotal();
                $orderInfo["revenue"] = $subTotalPrice - abs($order->getDiscountAmount());
                $orderInfo["total_price"] = $TotalPrice;
                $orderInfo["shipping_price"] = $totalShippingPrice;
                $orderInfo['order_id'] = $order->getIncrementId();
                $orderInfo['promo_code'] = $order->getCouponCode();
                $orderInfo['discount'] = abs($order->getDiscountAmount());
                $orderInfo['currency'] = $order->getOrderCurrencyCode();
                $orderInfo['order_status'] =$status;
                $orderInfo['taxes'] = $order->getShippingTaxAmount();
                try{
                if(!is_object($order->getPayment())){
                   $orderInfo['payment_method']="Custom";
                 }else{
                  $orderInfo['payment_method'] = $order->getPayment()->getMethodInstance()->getCode();
                 }
                }catch(Exception $e){
                  $orderInfo['payment_method']="Custom";  
                }
                $orderInfo['products']=$actionData; 
                $orderInfo['created_time']=strtotime($order->getData('created_at'));
                $actionDescription = array(
                    'identifiers' => $data,
                    'properties' => $orderInfo
                );
             $ordata[$j]=$actionDescription;
            $j++;  
        }
        $data=array();
        $data['orders']=$ordata;
        $sresponce=self::sendData($data,'bulk/orders/');
        $result=array("lastPage"=>$lpages,"cpage"=>$cpage,"responseCode"=>200,"serverResponce"=>$sresponce);
         echo json_encode($result);
        }else{
          $result=array("error"=>"No Data","responseCode"=>500);
          echo json_encode($result);
      }
   }
        
   }catch(Exception $e){
       echo json_encode(array("error"=>$e->getMessage()));
   }
        
}

public function customerAction(){
   try{
   $limit = isset($_GET['limit']) ? $_GET['limit'] : "5";
    $cpage = isset($_GET['pageNo']) ? $_GET['pageNo'] : 1;
    $totalCount = isset($_GET['totalCount']) ? $_GET['totalCount'] : 0;
    if($totalCount){
    $collection = Mage::getModel('customer/customer')->getCollection()
            ->addAttributeToSelect('*');
    $count=$collection->Count();
    $result=array("total"=>$count,"cpage"=>0,"responseCode"=>200); 
    echo json_encode($result);
    }else{
    $collection =  Mage::getModel('customer/customer')->getCollection()
            ->addAttributeToSelect('*')
            ->joinAttribute('shipping_firstname', 'customer_address/firstname', 'default_shipping', null, 'left')
            ->joinAttribute('shipping_lastname', 'customer_address/lastname', 'default_shipping', null, 'left')
            ->joinAttribute('shipping_company', 'customer_address/company', 'default_shipping', null, 'left')
            ->joinAttribute('shipping_street', 'customer_address/street', 'default_shipping', null, 'left')
            ->joinAttribute('shipping_postcode', 'customer_address/postcode', 'default_shipping', null, 'left')
            ->joinAttribute('shipping_telephone', 'customer_address/telephone', 'default_shipping', null, 'left')
            ->joinAttribute('shipping_city', 'customer_address/city', 'default_shipping', null, 'left')
            ->joinAttribute('shipping_region', 'customer_address/region', 'default_shipping', null, 'left')
            ->joinAttribute('shipping_country_id', 'customer_address/country_id', 'default_shipping', null, 'left');
    $collection->setPageSize($limit);
    $collection->setCurPage($cpage);
    $lpages = $collection->getLastPageNumber();
    $count=$collection->Count();

    $customerData = array();
    $i = 0;
    if($count){
    foreach ($collection as $customer) {
        $customerArray = $customer->toArray();
        $customerData[$i]['identifiers']['email']=$customerArray['email'];
        $customerData[$i]['identifiers']['phone']=$customerArray['shipping_telephone'];
        $customerData[$i]['identifiers']['customer_id']=$customerArray['entity_id'];
        $customerData[$i]['properties']['update']['firstname']=$customerArray['firstname'];
        $customerData[$i]['properties']['update']['city']=$customerArray['shipping_city'];
        $customerData[$i]['properties']['update']['region']=$customerArray['shipping_region'];
        $customerData[$i]['properties']['update']['country']=$customerArray['shipping_country_id'];
        $customerData[$i]['properties']['update']['street']=$customerArray['shipping_street'];
        $customerData[$i]['properties']['update']['postcode']=$customerArray['shipping_postcode'];
        $customerData[$i]['properties']['update']['firstseen_date']= strtotime($customerArray['created_at']);
        $customerData[$i]['properties']['update']['company']=$customerArray['shipping_company'];
        $i++;
    }
      $data=array();
      $data['users']=$customerData;
      $serverrep=self::sendData($data,"bulk/users/");
      $result=array("lastPage"=>$lpages,"cpage"=>$cpage,"responseCode"=>200,"serverResponse"=>$serverrep);
      echo json_encode($result);
    
    }else{
      $result=array("lastPage"=>0,"cpage"=>0,"responseCode"=>400,'msg'=>"No Data Found");  
      echo json_encode($result);
    }

   }
   }catch(Exception $e){
     $result=array("error"=>$e->getMessage(),"responseCode"=>500);
     echo json_encode($result);
}
}
public function productAction(){
    try{
    $limit = isset($_GET['limit']) ? $_GET['limit'] : "5";
    $cpage = isset($_GET['pageNo']) ? $_GET['pageNo'] : 1;
    $totalCount = isset($_GET['totalCount']) ? $_GET['totalCount'] : 0;
    if($totalCount){
         $products =Mage::getModel('catalog/product')->getCollection()
                    ->addAttributeToSelect('*');
         $count=$products->Count();
         $result=array("total"=>$count,"cpage"=>0,"responseCode"=>200); 
         echo json_encode($result);
    }else{
        $products = Mage::getModel('catalog/product')->getCollection()
       ->addAttributeToSelect('*') // select all attributes
       ->setPageSize($limit); // limit number of results returned
        $lpages = $products->getLastPageNumber();
        $products->setCurPage($cpage);
        $count=$products->Count();
        $productData=array();
        $i=0;
       // we iterate through the list of products to get attribute values
       foreach ($products as $product) {
          $productData[$i]['name']=$product->getName(); //get name
          $productData[$i]['price']=(float) $product->getPrice(); //get price as cast to float
          $productData[$i]['id']=$product->getId();
          $productData[$i]['sku']= $product->getSku();
          $productData[$i]['currency'] = Mage::app()->getStore()->getBaseCurrencyCode();
          $productData[$i]['image_url'] = $product->getImageUrl();
          $productData[$i]['product_url'] = $product->getProductUrl();
          $productData[$i]['brandname'] = $product->getResource()->getAttribute('manufacturer') ? $product->getAttributeText('manufacturer') : false;
         $categories=array();
          $categoryIds = $product->getCategoryIds();
         // getCategoryIds(); returns an array of category IDs associated with the product
         foreach ($categoryIds as $category_id) {
             $cateName = Mage::getModel('catalog/category')->load($category_id['entity_id']);
              $name = $cateName->getName();
              $id = $cateName->getEntityId();
              $pid = $cateName->getParent_id();
              if ($pid == 1) {
               $pid = 0;
           }
          $categories[] = array("cat_id"=>$id,"cat_name" => $name, "parent_cat_id" => $pid);
         }
         $productData[$i]['categories']=$categories;
         $i++;
       }
        $actionDescription = array(
                           'products' => $productData,
                           'timestamp'=> time()
                       );
        self::sendData($actionDescription,'ecommerce/products/');
        $result=array("lastPage"=>$lpages,"cpage"=>$cpage,"responseCode"=>200);
        echo json_encode($result);
    }
}catch(Exception $e){
     $result=array("error"=>$e->getMessage(),"responseCode"=>500);
     echo json_encode($result);
}
}
public function sendData($data,$path){
        $key=Mage::getStoreConfig('betaout_amplify_options/settings/amplify_key');
        $projectId = Mage::getStoreConfig('betaout_amplify_options/settings/amplify_projectId');
        $url="https://api.betaout.com/v2/".$path;
        $data['apikey']=$key;
        $data['project_id']=$projectId;
        $data['useragent'] = $_SERVER['HTTP_USER_AGENT'];
        $jdata = json_encode($data);
        $curl = curl_init($url);
        curl_setopt($curl,CURLOPT_HTTPHEADER,array('Content-Type: application/json'));
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT_MS, 50000);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $jdata);
        $result     = curl_exec($curl);
        $response   = json_decode($result);
        curl_close($curl);
        return $response;
    }
}
