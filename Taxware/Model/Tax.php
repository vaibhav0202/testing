<?php
class FW_Taxware_Model_Tax extends Mage_Tax_Model_Sales_Total_Quote_Tax {

    /**
     * Collect tax totals for quote address
     *
     * @param   Mage_Sales_Model_Quote_Address $address
     * @return  Mage_Tax_Model_Sales_Total_Quote
     */
    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        $taxwareHelper = Mage::helper('taxware');

        if (!$taxwareHelper->isTaxwareEnabled()) {
            return parent::collect($address);
        }else{
            parent::collect($address);
        }

        $this->_roundingDeltas = array();
        $this->_baseRoundingDeltas = array();
        $this->_hiddenTaxes = array();
        $address->setShippingTaxAmount(0);
        $address->setBaseShippingTaxAmount(0);

        $this->_store = $address->getQuote()->getStore();
        $customer = $address->getQuote()->getCustomer();
        if ($customer) {
            $this->_calculator->setCustomer($customer);
        }

        if (!$address->getAppliedTaxesReset()) {
            $address->setAppliedTaxes(array());
        }

        $items = $this->_getAddressItems($address);
        if (!count($items)) {
            return $this;
        }
        $request = $this->_calculator->getRateRequest(
            $address,
            $address->getQuote()->getBillingAddress(),
            $address->getQuote()->getCustomerTaxClassId(),
            $this->_store
        );

        if ($this->_config->priceIncludesTax($this->_store)) {
            if ($this->_helper->isCrossBorderTradeEnabled($this->_store)) {
                $this->_areTaxRequestsSimilar = true;
            } else {
                $this->_areTaxRequestsSimilar = $this->_calculator->compareRequests(
                    $this->_calculator->getRateOriginRequest($this->_store),
                    $request
                );
            }
        }

        switch ($this->_config->getAlgorithm($this->_store)) {
            case Mage_Tax_Model_Calculation::CALC_UNIT_BASE:
                $this->_unitBaseCalculation($address, $request);
                break;
            case Mage_Tax_Model_Calculation::CALC_ROW_BASE:
                $this->_rowBaseCalculation($address, $request);
                break;
            case Mage_Tax_Model_Calculation::CALC_TOTAL_BASE:
                $this->_totalBaseCalculation($address, $request);
                break;
            default:
                break;
        }

        $address->setTaxAmount(0);
        $address->setBaseTaxAmount(0);
        $this->_setAmount(0);
        $this->_setBaseAmount(0);

        $taxRate = $taxwareHelper->getRate($address);
        $taxAmount = (isset($taxRate->txDocRslt->txAmt) ? $taxRate->txDocRslt->txAmt : "0.00");
        $this->_addAmount($taxAmount);
        $this->_addBaseAmount($taxAmount);
	
        $lineResult = $taxRate->txDocRslt->lnRslts->lnRslt;
        if(isset($lineResult) )
        {
                $items = $this->_getAddressItems($address);
                if( $items )
                {
			$count = 0;
                        //There is only 1 item in cart so, there should be only 1 quoteItem
                        foreach($items as $quoteItem )
                        {
				if( $quoteItem->getParentItem()) {
			                continue;
        			}
				if( is_object($lineResult))
				{
                                	$lineTaxAmount = (isset($lineResult->txAmt) ? $lineResult->txAmt : "0.00");
                                	$lineTaxRate = (isset($lineResult->txRate) ? $lineResult->txRate : "0.00");
				} else {
					$lineTaxAmount = (isset($lineResult[$count]->txAmt) ? $lineResult[$count]->txAmt : "0.00");
                                        $lineTaxRate = (isset($lineResult[$count]->txRate) ? $lineResult[$count]->txRate : "0.00");
				}
                                $quoteItem->setTaxAmount($lineTaxAmount);
                                $quoteItem->setBaseTaxAmount($lineTaxAmount);
                                $quoteItem->setTaxPercent($lineTaxRate*100);
				$count++;
                        }
                }
        }

        $this->_calculateShippingTax($address, $request);
        $this->_processHiddenTaxes();

        //round total amounts in address
        $this->_roundTotals($address);
        return $this;
    }
}
