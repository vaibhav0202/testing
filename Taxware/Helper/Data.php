<?php
class FW_Taxware_Helper_Data extends Mage_Core_Helper_Abstract {

    public function isTaxwareEnabled($store = null)
    {
        return Mage::getStoreConfig('thirdparty/fw_taxware/active', $store);
    }

    public function getStoreTaxOrgCode($store = null)
    {
        return Mage::getStoreConfig('thirdparty/fw_taxware/taxware_orgcode', $store);
    }

    public function getStoreTaxGeoCode($store = null)
    {
        return Mage::getStoreConfig('thirdparty/fw_taxware/taxware_geocode', $store);
    }

	public function getStoreTaxPoo($store = null)
	{
		return Mage::getStoreConfig('thirdparty/fw_taxware/taxware_poo', $store);
	}

	public function getStoreTaxPoa($store = null)
	{
		return Mage::getStoreConfig('thirdparty/fw_taxware/taxware_poa', $store);
	}

    public function getDomain() {
        //Setting is stored in local.xml and domain needs to be placed in
        ///etc/hosts file pointing to the correct IP
        $domain = Mage::getConfig()->getNode('taxware/domain');
        //Default to local.taxware.com in case not set in local.xml
        $domain = ($domain) ? $domain : "local.taxware.com";
        return $domain;
    }

    public function getTaxCalculationUrl() {
        $url = "http://" . $this->getDomain() . ":8086/twe/services/TaxCalculationManagerService";
        return $url;
    }

    public function getTaxUtilityUrl($store = null) {
        $url = "http://" . $this->getDomain() . ":8086/twe/services/TaxUtilityManagerService";
        return $url;
    }

    public function getRate(Mage_Sales_Model_Quote_Address $mageQuoteAddress) {
        //LOAD CART ITEMS
        $cartItems = $mageQuoteAddress->getQuote()->getAllVisibleItems();

	 //CONNECT TO TAXWARE GATEWAY AND GET GEOCODE
	$address_country = $mageQuoteAddress->getCountry();
	if( strcmp($address_country,"CA") === 0 ){
            $geo_tw = new FW_Taxware_Model_Gateway('TaxUtilityManager');
            $geocode_request = Mage::helper('taxware')->buildGeoCodeRequest($mageQuoteAddress->getPostcode(), Mage::app()->getLocale()->getCountryTranslation($address_country), "", "");
            $geocode_response = $geo_tw->getGeoCode($geocode_request);
            $geocode = (isset($geocode_response->geoCdRslts->geoCdRslt->geoCd) ? $geocode_response->geoCdRslts->geoCdRslt->geoCd : "NO MATCH");
	} elseif ( strcmp($address_country, "US") === 0 ) {
	    $geo_tw = new FW_Taxware_Model_Gateway('TaxUtilityManager');
	    $geocode_request = Mage::helper('taxware')->buildGeoCodeRequest($mageQuoteAddress->getPostcode(), Mage::app()->getLocale()->getCountryTranslation($address_country), $mageQuoteAddress->getRegion(), $mageQuoteAddress->getCity());
	    $geocode_response = $geo_tw->getGeoCode($geocode_request);
	    $geocode = (isset($geocode_response->geoCdRslts->geoCdRslt->geoCd) ? $geocode_response->geoCdRslts->geoCdRslt->geoCd : "NO MATCH");
	} else {
	    $geo_tw = new FW_Taxware_Model_Gateway('TaxUtilityManager');
	    $geocode_request = Mage::helper('taxware')->buildGeoCodeRequest($mageQuoteAddress->getPostCode(), Mage::app()->getLocale()->getCountryTranslation($address_country), "IT", $mageQuoteAddress->getCity());
	    $geocode_response = $geo_tw->getGeoCode($geocode_request);
	    $geocode = (isset($geocode_response->geoCdRslts->geoCdRslt->geoCd) ? $geocode_response->geoCdRslts->geoCdRslt->geoCd : "NO MATCH");
	}	

	//LOAD SHIPPING COST IF ANY
	$shippingMethod = $mageQuoteAddress->getQuote()->getShippingAddress();
	$shipping_cost = $shippingMethod['shipping_amount'];

        //CONNECT TO TAXWARE GATEWAY AND GET TAX CALC
        $tax_tw = new FW_Taxware_Model_Gateway('TaxCalculationManager');
        $tax_request = Mage::helper('taxware')->buildTaxCalcRequest($mageQuoteAddress->getBaseSubtotal(), $geocode, $cartItems, $shipping_cost);
        $tax_response = $tax_tw->calculateLine($tax_request);
        Mage::log("TAX REQUEST:" . $tax_request, null, "tax.log");
	Mage::log("TAX RESPONSE:" . print_r($tax_response, true), null, "tax.log");
        return $tax_response;
    }

    public function buildGeoCodeRequest($postalcode = NULL, $country = NULL, $statePrv = NULL, $city = NULL){
        $geocode_request = '<getGeoCodeRequest xmlns="http://ws.taxwareenterprise.com" xmlns:doc="http://ws.taxwareenterprise.com">
	<secrtySbj>
		<usrname>Admin</usrname>
		<pswrd>Admin1234</pswrd>
	</secrtySbj>
	<addrs>
		<city>'.$city.'</city>
		<statePrv>'.$statePrv.'</statePrv>
		<postCd>'.$postalcode.'</postCd>
		<cntry>'.$country.'</cntry>
	</addrs>
</getGeoCodeRequest>
';
        return $geocode_request;
    }

    public function buildTaxCalcRequest($grossAmt, $geocode, $cartItems, $shipping_cost){

        $taxwareHelper = Mage::helper('taxware');
		$quoteId = Mage::getSingleton('checkout/session')->getQuoteId();
		$timestamp = date("F j, Y, g:i a");
        $tax_request = '<calculateLineRequest xmlns="http://ws.taxwareenterprise.com" xmlns:doc="http://ws.taxwareenterprise.com">
	<secrtySbj>
		<usrname>Admin</usrname>
		<pswrd>ADMIN1234</pswrd>
	</secrtySbj>
	<doc>
		<lnItms>';
        $cartCounter = 0;
        foreach ($cartItems as $item) {
            $cartCounter++;
            $taxware_taxcode = ($item->getProduct()->getTaxwareTaxcode() <> NULL ? $item->getProduct()->getTaxwareTaxcode() : "76800");
            $tax_request .= '
			<lnItm>
				<trnTp>1</trnTp>';

			//CHECK FOR DISCOUNTS AND APPLY
			if($item->getDiscountAmount() <> NULL) {
				$tax_request .='
					<grossAmt>'.$item->getTaxableAmount().'</grossAmt>
			    	<qnty>'.$item->getQty().'</qnty>
			    	<discnts>
                      <discnt>
                        <amt>'.$item->getDiscountAmount().'</amt>
                      </discnt>
                    </discnts>';
			}else{
				$tax_request .='
					<grossAmt>'.$item->getTaxableAmount().'</grossAmt>
			    	<qnty>'.$item->getQty().'</qnty>';
			}

			$tax_request .='
				<org>
					<code>'.$taxwareHelper->getStoreTaxOrgCode().'</code>
				</org>
				<shipFrom>
					<geoCd>'.$taxwareHelper->getStoreTaxGeoCode().'</geoCd>
					<locCd>'.$taxwareHelper->getStoreTaxOrgCode().'</locCd>
				</shipFrom>
				<shipTo>
					<geoCd>'.$geocode.'</geoCd>
				</shipTo>
				<lOR>
					<geoCd>'.$taxwareHelper->getStoreTaxPoo().'</geoCd>
				</lOR>
				<lOA>
					<geoCd>'.$taxwareHelper->getStoreTaxPoa().'</geoCd>
				</lOA>
				<goodSrv>
				    <myCd>'.$taxware_taxcode.'</myCd>
				</goodSrv>
				<lnItmNm>'.$cartCounter.'</lnItmNm>
			</lnItm>';
        }

        $tax_request .= '
		</lnItms>
		<currn>USD</currn>
		<dlvrAmt>'.$shipping_cost.'</dlvrAmt>
		<txCalcTp>1</txCalcTp>
		<trnDocNm>'.$quoteId.'-'.$timestamp.'</trnDocNm>
		<trnSrc>MAGENTO</trnSrc>
	</doc>
	<rsltLvl>1</rsltLvl>
	<isAudit>true</isAudit>
</calculateLineRequest>';
        return $tax_request;
    }
}
