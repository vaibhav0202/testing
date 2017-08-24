<?php
class FW_Taxware_Model_Gateway {

    private $mode;

    public function __construct($mode = "TaxCalculationManager")
    {
        $this->mode = $mode;

        $wsdlBasePath = Mage::getModuleDir('etc', 'FW_Taxware')  . DS . 'wsdl' . DS;
        $this->_CalculationManagerServiceWsdl = $wsdlBasePath . 'TaxCalculationManagerService.wsdl';
        $this->_UtilityManagerServiceWsdl = $wsdlBasePath . 'TaxUtilityManagerService.wsdl';

        if($mode == "TaxCalculationManager"){
            $this->soap_url = Mage::helper('taxware')->getTaxCalculationUrl();
            $wsdl = $this->_CalculationManagerServiceWsdl;
        }
        elseif($mode == "TaxUtilityManager"){
            $this->soap_url = Mage::helper('taxware')->getTaxUtilityUrl();
            $wsdl = $this->_UtilityManagerServiceWsdl;
        }

        $this->soap = new SoapClient($wsdl);
        $this->soap->__setLocation($this->soap_url);
    }

    public function __call($name, $request)
    {
        $response = $this->soap->$name(new SoapVar($request, XSD_ANYXML));
        return $response;
    }
}
