<?php
/**
 * NOTICE OF LICENSE.
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 *  @author    Asymbo s.r.o.
 *  @copyright 2014-2016 Asymbo s.r.o.
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
namespace Asymbo\Plugin\Model\Admin;

/**
 * Admin blog configurations information block
 */
class Info extends \Magento\Config\Block\System\Config\Form\Field
{

    private $storeManager;
    
    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->storeManager = $storeManager;
    }
    
    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $infoUrl = $this->storeManager->getStore()->getUrl('asymbo-plugin/webhook/getaddress');
        $html = '<div style="padding:10px;background-color:#f8f8f8;border:1px solid #ddd;margin-bottom:7px;">' . $infoUrl . '</div>';
        return $html;
    }

}
