<?php

namespace App\Http\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PaymentService
{

    const PAYMENT_GATEWAY_1_ID = 1;

    const PAYMENT_GATEWAY_2_ID = 2;

    /**
     * @throws \Exception
     */
    protected function reportError($message)
    {
        if (config('app.debug')) {
            throw new \Exception($message);
        }

        $errorId = Str::random() . time();
        Log::error("#$errorId: $message");
        throw new \Exception("Server error #$errorId. It has been logged and we are trying to fix it");
    }

    protected function checkHash($validationRules, $requestData, $merchantKeyIndex, $separator, $hashAlgorithm, $signature)
    {
        $requestKeys = array_keys($validationRules);

        sort($requestKeys);

        $message = '';

        $merchantKey = config($merchantKeyIndex);
        if (!$merchantKey) {
            $message = "Your configuration file doesn't contain $merchantKeyIndex";
        }

        if ($message) {
            $this->reportError($message);
        }

        $rawSignatureValues = [];
        foreach ($requestKeys as $requestKey) {
            $rawSignatureValues []= $requestData[$requestKey];
        }

        $rawSignature = implode($separator, $rawSignatureValues) . $merchantKey;
        $hashed = hash($hashAlgorithm, $rawSignature);

        if ($hashed !== $signature) {
            throw new \Exception("Invalid signature");
        }
    }

    public function getGatewayId($requestData)
    {
        if (array_key_exists('merchant_id', $requestData)) {
            return static::PAYMENT_GATEWAY_1_ID;
        }
        return static::PAYMENT_GATEWAY_2_ID;
    }

    /**
     * @throws \Exception
     */
    public function getValidationRules($gateWayId)
    {
        $rules = [
            'amount' => ['required', 'integer'],
            'amount_paid' => ['required', 'integer'],
        ];

        switch ($gateWayId) {
            case static::PAYMENT_GATEWAY_1_ID:
                $rules['merchant_id'] = ['required', 'integer'];
                $rules['payment_id'] = ['required', 'integer'];
                $rules['timestamp'] = ['required', 'integer', 'max:2147483647'];
                $rules['status'] =  ['required', Rule::in(['new', 'pending', 'completed', 'expired', 'rejected'])];
                $rules['sign'] = ['required'];
                return $rules;

            case static::PAYMENT_GATEWAY_2_ID:
                $rules['project'] = ['required', 'integer'];
                $rules['invoice'] = ['required', 'integer'];
                $rules['status'] =  ['required', Rule::in(['created', 'inprogress', 'paid', 'expired', 'rejected', 'completed'])];
                $rules['rand'] = ['required'];

                return $rules;
        }

        $this->reportError("Unknown gateway ID: $gateWayId");
    }

    /**
     * @param $requestData
     * @param $gateWayId
     * @return array
     */
    public function unifyRequestData($requestData, $gateWayId)
    {
        if ($gateWayId === static::PAYMENT_GATEWAY_2_ID) {
            $requestData['merchant_id'] = $requestData['project'];
            $requestData['payment_id'] = $requestData['invoice'];
            unset($requestData['project']);
            unset($requestData['invoice']);
        }
        return $requestData;
    }

    /**
     * @throws \Exception
     */
    public function checkSignature($requestData, $gateWayId, $validationRules, $authorizationKey = null)
    {
        switch ($gateWayId) {
            case static::PAYMENT_GATEWAY_1_ID:
                unset($validationRules['sign']);

                $this->checkHash(
                    $validationRules,
                    $requestData,
                    'payments.gateway_1.merchant_key',
                    ':',
                    "sha256",
                    $requestData['sign']
                );

                return true;
            case static::PAYMENT_GATEWAY_2_ID:
                $this->checkHash(
                    $validationRules,
                    $requestData,
                    'payments.gateway_2.app_key',
                    ".",
                    "md5",
                    $authorizationKey
                );
                return true;
        }
        $this->reportError("Unknown gateway ID: $gateWayId");
    }

    public function save($requestData, $gateWayId)
    {
        $requestData = $this->unifyRequestData($requestData, $gateWayId);

        $payment = new Payment();
        $payment->fill($requestData);
        $payment->gateway_id = $gateWayId;
        $payment->save();

        return $payment;
    }
}
