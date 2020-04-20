<?php

namespace robokassa;

use Yii;
use yii\base\BaseObject;
use yii\base\InvalidConfigException;

/**
 * Receipt ResultAction
 * @package robokassa
 */
class Receipt extends BaseObject
{
    const SNO_OSN = 'osn'; // общая СН;
    const SNO_USN_INCOME = 'usn_income'; // упрощенная СН (доходы);
    const SNO_USN_INCOME_OUTCOME = 'usn_income_outcome'; // упрощенная СН (доходы минус расходы);
    const SNO_ENVD = 'envd'; // единый налог на вмененный доход;
    const SNO_ESN = 'esn'; // единый сельскохозяйственный налог;
    const SNO_PATENT = 'patent'; // патентная СН.


    public $sno = null; // Система налогообложения. Необязательное поле, если у организации имеется только один тип налогообложения.
    public $items = []; // Массив данных о позициях чека.

    public $merchantId = null;
    public $id = null;
    public $originId = null;
    public $operation = 'sell';
    public $url = null;
    public $total = null;
    public $client = null;
    public $payments = [];
    public $vats = [];


    /**
     * @param ReceiptItem $item
     */
    public function addItem(ReceiptItem $item)
    {
        $this->items[] = $item;
    }

    /**
     * @param $email
     * @param $phone
     */
    public function setClient($email, $phone)
    {
        $this->client = new \stdClass();
        $this->client->email = $email;
        $this->client->phone = $phone;
    }

    /**
     * @param $sum
     */
    public function addPayment($sum)
    {
        $payment = new \stdClass();
        $payment->type = 2;
        $payment->sum = $sum;
        $this->payments[] = $payment;
    }

    /**
     * @param $type
     * @param $sum
     */
    public function addVat($type, $sum)
    {
        $vat = new \stdClass();
        $vat->type = $type;
        $vat->sum = $sum;
        $this->vats[] = $vat;
    }


    /**
     * @return false|string
     */
    public function getFirstReceiptJson()
    {
        $receipt = new \stdClass();
        $receipt->sno = $this->sno;
        $receipt->items = $this->items;
        return json_encode($receipt);
    }

    /**
     * @return false|string
     */
    public function getSecondReceiptJson()
    {
        $receipt = new \stdClass();
        $receipt->sno = $this->sno;
        $receipt->merchantId = $this->merchantId;
        $receipt->id = $this->id;
        $receipt->originId = $this->originId;
        $receipt->operation = $this->operation;
        $receipt->url = $this->url;
        $receipt->total = $this->total;
        $receipt->items = $this->items;
        $receipt->client = $this->client;
        $receipt->payments = $this->payments;
        $receipt->vats = $this->vats;
        return json_encode($receipt, JSON_UNESCAPED_UNICODE);
    }

}
