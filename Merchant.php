<?php

namespace robokassa;

use Yii;
use yii\base\BaseObject;
use yii\base\InvalidConfigException;
use yii\web\Response;

class Merchant extends BaseObject
{
    public $sMerchantLogin;

    public $sMerchantPass1;
    public $sMerchantPass2;

    public $isTest = false;

    public $baseUrl = 'https://auth.robokassa.ru/Merchant/Index.aspx';
    public $fiscalUrl = 'https://ws.roboxchange.com/RoboFiscal/Receipt';

    public $hashAlgo = 'md5';

    /**
     * @param mixed $nOutSum Требуемая к получению сумма (буквально — стоимость заказа, сделанного клиентом).
     * Формат представления — число, разделитель — точка, например: 123.45.
     * @param mixed $nInvId Номер счета в магазине.
     * @param null|string $sInvDesc Описание покупки,
     * можно использовать только символы английского или русского алфавита,
     * цифры и знаки препинания. Максимальная длина — 100 символов.
     * @param null|string $sIncCurrLabel Предлагаемый способ оплаты.
     * @param null|string $sEmail Email покупателя автоматически подставляется в платёжную форму ROBOKASSA.
     * @param null|string $sCulture Язык общения с клиентом (в соответствии с ISO 3166-1).
     * @param array $shp Дополнительные пользовательские параметры
     * @param string $sReceipt Информация о перечне товаров/услуг для формирования чека (в JSON)
     * @param bool $returnLink
     * @return string|Response
     * @throws InvalidConfigException
     */
    public function payment($nOutSum, $nInvId, $sInvDesc = null, $sIncCurrLabel = null, $sEmail = null, $sCulture = null, $shp = [], $sReceipt = null, $returnLink = false)
    {
        $url = $this->baseUrl;

        $sReceipt = $sReceipt !== null ? urlencode($sReceipt) : null;

        $sSignatureValue = $this->generateSignature($nOutSum, $nInvId, $shp, $sReceipt);

        $url .= '?' . http_build_query([
                'MrchLogin' => $this->sMerchantLogin,
                'OutSum' => $nOutSum,
                'InvId' => $nInvId,
                'Desc' => $sInvDesc,
                'SignatureValue' => $sSignatureValue,
                'IncCurrLabel' => $sIncCurrLabel,
                'Email' => $sEmail,
                'Culture' => $sCulture,
                'IsTest' => $this->isTest ? 1 : null,
            ]);

        if (!empty($shp) && ($query = http_build_query($shp)) !== '') {
            $url .= '&' . $query;
        }

        if ($sReceipt !== null && ($query = http_build_query(['Receipt' => $sReceipt])) !== '') {
            $url .= '&' . $query;
        }

        if (!$returnLink) {
            Yii::$app->user->setReturnUrl(Yii::$app->request->getUrl());
            return Yii::$app->response->redirect($url);
        } else {
            return $url;
        }
    }

    /**
     * @param $shp
     * @return string
     */
    private function implodeShp($shp)
    {
        ksort($shp);

        foreach ($shp as $key => $value) {
            $shp[$key] = $key . '=' . $value;
        }

        return implode(':', $shp);
    }

    /**
     * @param $nOutSum
     * @param $nInvId
     * @param array $shp
     * @param null $sReceipt
     * @return string
     */
    private function generateSignature($nOutSum, $nInvId, $shp = [], $sReceipt = null)
    {
        // варианты при передачи InvId и Receipt
        // MerchantLogin:OutSum:Пароль#1 | InvId=null и Receipt=null
        // MerchantLogin:OutSum:InvId:Пароль#1 | Receipt=null
        // MerchantLogin:OutSum:Receipt:Пароль#1 | InvId=null
        // MerchantLogin:OutSum:InvId:Receipt:Пароль#1

        $signature = "{$this->sMerchantLogin}:{$nOutSum}";

        if ($nInvId !== null) {
            $signature .= ":{$nInvId}";
        }

        if ($sReceipt !== null) {
            $signature .= ":{$sReceipt}";
        }

        $signature .= ":{$this->sMerchantPass1}";

        if (!empty($shp)) {
            $signature .= ':' . $this->implodeShp($shp);
        }

        return strtolower($this->encryptSignature($signature));
    }

    /**
     * @param string $sReceipt (в base64)
     * @return string
     */
    private function generateSignatureReceipt($sReceipt)
    {
        // ReceiptПароль#1
        $signature = "{$sReceipt}{$this->sMerchantPass1}";

        return strtolower($this->encryptSignature($signature));
    }

    /**
     * @param $sSignatureValue
     * @param $nOutSum
     * @param $nInvId
     * @param $sMerchantPass
     * @param array $shp
     * @return bool
     */
    public function checkSignature($sSignatureValue, $nOutSum, $nInvId, $sMerchantPass, $shp = [])
    {
        $signature = "{$nOutSum}:{$nInvId}:{$sMerchantPass}";

        if (!empty($shp)) {
            $signature .= ':' . $this->implodeShp($shp);
        }

        return strtolower($this->encryptSignature($signature)) === strtolower($sSignatureValue);

    }

    /**
     * Encode data to Base64URL
     * @param string $data
     * @return boolean|string
     */
    function base64UrlEncode($data)
    {
        $b64 = base64_encode($data);

        if ($b64 === false) {
            return false;
        }

        $url = strtr($b64, '+/', '-_');

        return rtrim($url, '=');
    }

    /**
     * Decode data from Base64URL
     * @param string $data
     * @param boolean $strict
     * @return boolean|string
     */
    function base64UrlDecode($data, $strict = false)
    {
        $b64 = strtr($data, '-_', '+/');

        return base64_decode($b64, $strict);
    }

    /**
     * @param $signature
     * @return string
     */
    protected function encryptSignature($signature)
    {
        return hash($this->hashAlgo, $signature);
    }

    /**
     * @param string $sReceipt Информация о перечне товаров/услуг для формирования чека (в JSON)
     * @return array
     * @throws InvalidConfigException
     */
    public function receiptAttach($sReceipt)
    {
        // зачем сделали такой странный порядок действий разработчики робокассы мне не понятно :)
        $sReceipt1 = $this->base64UrlEncode($sReceipt);

        $sSignatureValue1 = $this->generateSignatureReceipt($sReceipt1);
        $sSignatureValue1 = $this->base64UrlEncode($sSignatureValue1);



        $sReceipt2 = str_replace('+', '-', $sReceipt);
        $sReceipt2 = str_replace('/', '_', $sReceipt2);
        $sReceipt2 = base64_encode($sReceipt2);
        $sReceipt2 = rtrim($sReceipt2, '=');

        $sSignatureValue2 = $this->generateSignatureReceipt($sReceipt2);
        $sSignatureValue2 = base64_encode($sSignatureValue2);
        $sSignatureValue2 = rtrim($sSignatureValue2, '=');



        return [
            'url' => $this->fiscalUrl . '/Attach',
            'base64url' => $sReceipt1 .'.'. $sSignatureValue1,
            'base64url2' => $sReceipt2 .'.'. $sSignatureValue2
        ];

    }
} 
