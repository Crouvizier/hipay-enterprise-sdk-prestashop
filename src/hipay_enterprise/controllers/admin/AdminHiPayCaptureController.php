<?php

/**
 * 2017 HiPay
 *
 * NOTICE OF LICENSE
 *
 * @author    HiPay <support.wallet@hipay.com>
 * @copyright 2017 HiPay
 * @license   https://github.com/hipay/hipay-wallet-sdk-prestashop/blob/master/LICENSE.md
 */
require_once(dirname(__FILE__) . '/../../classes/helper/apiHandler/ApiHandler.php');
require_once(dirname(__FILE__) . '/../../classes/helper/tools/hipayDBQuery.php');

use HiPay\Fullservice\Enum\Transaction\Operation;

class AdminHiPayCaptureController extends ModuleAdminController {

    public function __construct() {
        parent::__construct();

        $this->apiHandler = new ApiHandler($this->module, $this->context);
        $this->db = new HipayDBQuery($this->module);
    }

    public function postProcess() {

        $context = Context::getContext();

        if (Tools::isSubmit('id_order') && Tools::getValue('id_order') > 0) {
            $order = new Order(Tools::getValue('id_order'));
            if (!Validate::isLoadedObject($order))
                throw new PrestaShopException('Can\'t load Order object');
            if (version_compare(_PS_VERSION_, '1.5.6', '>'))
                ShopUrl::cacheMainDomainForShop((int) $order->id_shop);
        }

        if (Tools::isSubmit('id_emp') && Tools::getValue('id_emp') > 0) {
            $id_employee = Tools::getValue('id_emp');
        } else {
            $id_employee = '1';
        }

        if (Tools::isSubmit('hipay_capture_type')) {
            $capture_type = Tools::getValue('hipay_capture_type');
            $capture_amount = Tools::getValue('hipay_capture_amount');
            $capture_amount = str_replace(' ', '', $capture_amount);
            $capture_amount = floatval(str_replace(',', '.', $capture_amount));
        }

        if (!$capture_amount) {
            $hipay_redirect_status = $this->module->l('Please enter an amount', 'capture');
            Tools::redirectAdmin($context->link->getAdminLink('AdminOrders') . '&id_order=' . (int) $order->id . '&vieworder&hipay_err=' . $hipay_redirect_status . '#hipay');
            die('');
        }
        if ($capture_amount <= 0) {
            $hipay_redirect_status = $this->module->l('Please enter an amount greater than zero', 'capture');
            Tools::redirectAdmin($context->link->getAdminLink('AdminOrders') . '&id_order=' . (int) $order->id . '&vieworder&hipay_err=' . $hipay_redirect_status . '#hipay');
            die('');
        }

        if (!is_numeric($capture_amount)) {
            $hipay_redirect_status = $this->module->l('Please enter an amount', 'capture');
            Tools::redirectAdmin($context->link->getAdminLink('AdminOrders') . '&id_order=' . (int) $order->id . '&vieworder&hipay_err=' . $hipay_redirect_status . '#hipay');
            die('');
        }

        // First check
        if (Tools::isSubmit('hipay_capture_submit')) {
            $totalPaid = $order->getTotalPaid();
            $stillToCapture = $order->total_paid_tax_incl - $totalPaid;

            if (round($capture_amount, 2) > round($stillToCapture, 2)) {
                var_dump($capture_amount);
                var_dump($stillToCapture);
                var_dump($capture_amount > $stillToCapture);
                die('');
                $hipay_redirect_status = $this->module->l('Amount exceeding authorized amount', 'capture');
                Tools::redirectAdmin($context->link->getAdminLink('AdminOrders') . '&id_order=' . (int) $order->id . '&vieworder&hipay_err=' . $hipay_redirect_status . '#hipay');
                die('');
            }

            $transactionReference = $this->db->getTransactionReference($order->id);

            if (!$transactionReference) {
                $hipay_redirect_status = $this->module->l('No transaction reference link to this order', 'capture');
                Tools::redirectAdmin($context->link->getAdminLink('AdminOrders') . '&id_order=' . (int) $order->id . '&vieworder&hipay_err=' . $hipay_redirect_status . '#hipay');
                die('');
            }

            if ($capture_type == 'complete') {

                $params = array("amount" => $stillToCapture, "transaction_reference" => $transactionReference);
                $this->apiHandler->handleCapture($params);
            } else if ($capture_type == 'partial') {
                $params = array("amount" => $capture_amount, "transaction_reference" => $transactionReference);
                $this->apiHandler->handleCapture($params);
            }
        }

        Tools::redirectAdmin($context->link->getAdminLink('AdminOrders') . '&id_order=' . (int) $order->id . '&vieworder&hipay_err=ok#hipay');
    }

}
