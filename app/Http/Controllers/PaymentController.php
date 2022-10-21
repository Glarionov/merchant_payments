<?php

namespace App\Http\Controllers;

use App\Http\Services\PaymentService;
use http\Client\Response;
use Illuminate\Http\Request;
use Nette\Schema\ValidationException;

class PaymentController extends Controller
{
    public function save(Request $request)
    {
        $paymentService = new PaymentService();
        $requestData = $request->all();

        try {
            $gateWayId = $paymentService->getGatewayId($requestData);
            $validationRules = $paymentService->getValidationRules($gateWayId);
            $request->validate($validationRules);

            $authorizationKey = null;
            if ($gateWayId == $paymentService::PAYMENT_GATEWAY_2_ID) {
                if (!$request->hasHeader('Authorization')) {
                    throw new \Exception("No 'Authorization' header");
                }
                $authorizationKey = $request->header('Authorization');
            }

            $paymentService->checkSignature($requestData, $gateWayId, $validationRules, $authorizationKey);
            $payment = $paymentService->save($requestData, $gateWayId);
            return ['success' => true, 'payment' => $payment];
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return ['success' => false, 'message' => $exception->getMessage(), 'errors' => $exception->errors()];
        }
        catch (\Exception $exception) {
            if (config('app.debug')) {
                return ['success' => false, 'message' => $exception->getMessage(), 'exception' => $exception->getTrace()];
            }

            return ['success' => false, 'message' => $exception->getMessage()];
        }
    }
}
