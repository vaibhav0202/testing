<?php
/**
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class FW_Taxware_Model_Subtotal extends Mage_Tax_Model_Sales_Total_Quote_Subtotal
{
    /**
     * Code used to determine the block renderer for the address line.
     * @see Mage_Checkout_Block_Cart_Totals::_getTotalRenderer
     * @var string
     */
    /**
     * @param array $args May contain key/value for:
			       - helpercore => Mage_Tax_Helper_Data
     *                         - helper => FW_Taxware_Helper_Data
     *			       - config => Mage_Tax_Model_Config
     *                         - calculator => Mage_Tax_Model_Calculation
     */
    public function __construct(array $args = [])
    {
        list(
	    $this->_helper,
            $this->__helper,
	    $this->_config,
	    $this->_calculator
        ) = $this->_checkTypes(
	    $this->_nullCoalesce($args, 'helpercore', Mage::helper('tax')),
            $this->_nullCoalesce($args, 'helper', Mage::helper('taxware')),
	    $this->_nullCoalesce($args, 'config', Mage::getSingleton('tax/config')),
	    $this->_nullCoalesce($args, 'calculator', Mage::getSingleton('tax/calculation'))
        );
    }
    /**
     * Enforce type checks on constructor init params.
     *
     * @param Mage_Tax_Helper_Data
     * @param FW_Taxware_Helper_Data
     * @param Mage_Tax_Model_Config
     * @param Mage_Tax_Model_Calculator
     * @return array
     */
    protected function _checkTypes(
	Mage_Tax_Helper_Data $helpercore,
        FW_Taxware_Helper_Data $helper,
	Mage_Tax_Model_Config $config,
	Mage_Tax_Model_Calculation $calculator
    ) {
        return [
	    $helpercore,
            $helper,
	    $config,
	    $calculator
        ];
    }
    /**
     * Fill in default values.
     *
     * @param string
     * @param array
     * @param mixed
     * @return mixed
     */
    protected function _nullCoalesce(array $arr, $key, $default)
    {
        return isset($arr[$key]) ? $arr[$key] : $default;
    }
    /**
     * Calculate item price including/excluding tax, row total including/excluding tax
     * and subtotal including/excluding tax.
     * Determine discount price if needed
     *
     * @param   Mage_Sales_Model_Quote_Address $address
     *
     * @return  Mage_Tax_Model_Sales_Total_Quote_Subtotal
     */
    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
	if (!$this->__helper->isTaxwareEnabled()) {
            return parent::collect($address);
        }else{
            parent::collect($address);
        }

        $this->_store = $address->getQuote()->getStore();
        $this->_address = $address;
        $items = $this->_getAddressItems($address);
        if (!$items) {
            return $this;
        }

	//Get Taxware Response for Items in Quote
        $taxResponse = $this->__helper->getRate($address);

	$count = 0;
        foreach ($items as $item) {
	    if( $item->getParentItem()) {
		continue;
	    }

	    if( is_object($taxResponse) && isset($taxResponse))
	    {
	    	$lineResult = $taxResponse->txDocRslt->lnRslts->lnRslt;

	    	if( is_object($lineResult))
	    	{
	    		$this->_processItemFW($item, $address, $lineResult);
	    	} else {
			$this->_processItemFW($item, $address, $lineResult[$count]);
		}
	   	$this->_addSubtotalAmountFW($address, $item);
	    }
	    $count++;
        }
        return $this;
    }
    /**
     *
     * @param Mage_Sales_Model_Quote_Item_Abstract $item
     * @param Mage_Sales_Model_Quote_Address $address
     *
     * @return Mage_Tax_Model_Sales_Total_Quote_Subtotal
     */
    protected function _processItemFW(Mage_Sales_Model_Quote_Item_Abstract $item, Mage_Sales_Model_Quote_Address $address, $lineResult) 
    {
	$qty = $item->getTotalQty();
        $price = $taxPrice = $this->_calculator->round($item->getCalculationPriceOriginal());
        $basePrice = $baseTaxPrice = $this->_calculator->round($item->getBaseCalculationPriceOriginal());
        $subtotal = $taxSubtotal = $this->_calculator->round($item->getRowTotal());
        $baseSubtotal = $baseTaxSubtotal = $this->_calculator->round($item->getBaseRowTotal());
        
	if( $item->hasCustomPrice()) {
		$item->getOriginalPrice();
		$item->setCustomPrice($price);
		$item->setBaseCustomPrice($basePrice);
	}

	$merchItemTaxTotal = (isset($lineResult->txAmt) ? $lineResult->txAmt : "0.00");

	if( $merchItemTaxTotal )
	{
		$merchItemTaxTotal = $merchItemTaxTotal / $qty;
	}

	$item->setPrice($basePrice);
        $item->setBasePrice($basePrice);
        $item->setRowTotal($subtotal);
        $item->setBaseRowTotal($baseSubtotal);

        if ($this->_config->priceIncludesTax($this->_store)) {
		$taxable = $price;
                $baseTaxable = $basePrice;
        	$tax = $merchItemTaxTotal;
                $baseTax = $merchItemTaxTotal;
                $taxPrice        = $price;
                $baseTaxPrice    = $basePrice;
                $taxSubtotal     = $subtotal;
                $baseTaxSubtotal = $baseSubtotal;
                $price = $price - $tax;
                $basePrice = $basePrice - $baseTax;
                $subtotal = $price * $qty;
                $baseSubtotal = $basePrice * $qty;
                $isPriceInclTax  = true;
                               
                $item->setRowTax($tax * $qty);
                $item->setBaseRowTax($baseTax * $qty);
        } else {
		$taxable = $price;
                $baseTaxable = $basePrice;
                $tax             = $merchItemTaxTotal;
                $baseTax         = $merchItemTaxTotal;
                $taxPrice        = $price + $tax;
                $baseTaxPrice    = $basePrice + $baseTax;
                $taxSubtotal     = $taxPrice * $qty;
                $baseTaxSubtotal = $baseTaxPrice * $qty;
                $isPriceInclTax  = false;
        }

        $item->setPriceInclTax($taxPrice);
        $item->setBasePriceInclTax($baseTaxPrice);
        $item->setRowTotalInclTax($taxSubtotal);
        $item->setBaseRowTotalInclTax($baseTaxSubtotal);
        $item->setTaxableAmount($taxable*$qty);
        $item->setBaseTaxableAmount($baseTaxable*$qty);
        $item->setIsPriceInclTax($isPriceInclTax);
        if ($this->_config->discountTax($this->_store)) {
        	$item->setDiscountCalculationPrice($taxPrice);
                $item->setBaseDiscountCalculationPrice($baseTaxPrice);
        }
	return $this;
    }
    /**
     * Add row total item amount to subtotal
     *
     * @param   Mage_Sales_Model_Quote_Address $address
     * @param   Mage_Sales_Model_Quote_Item_Abstract $item
     *
     * @return  Mage_Tax_Model_Sales_Total_Quote_Subtotal
     */
    protected function _addSubtotalAmountFW(Mage_Sales_Model_Quote_Address $address, $item)
    {
        $address->setSubtotalInclTax($address->getSubtotalInclTax() + $item->getTaxAmount());
        $address->setBaseSubtotalInclTax($address->getBaseSubtotalInclTax() + $item->getBaseTaxAmount());
        return $this;
    }
}
