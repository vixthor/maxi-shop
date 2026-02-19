<?php
/**
 * Copyright (c) Since 2024 InnoShop - All Rights Reserved
 *
 * @link       https://www.innoshop.com
 * @author     InnoShop <team@innoshop.com>
 * @license    https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace Plugin\Paystack\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InnoShop\Common\Repositories\Order\PaymentRepo;
use InnoShop\Common\Repositories\OrderRepo;
use InnoShop\Common\Services\StateMachineService;
use Plugin\Paystack\Services\PaystackService;

class PaystackController extends Controller
{
    /**
     * Initialize Paystack payment
     *
     * @param  Request  $request
     * @return JsonResponse
     * @throws \Throwable
     */
    public function initialize(Request $request): JsonResponse
    {
        try {
            $filters = [
                'number'      => $request->get('order_number'),
                'customer_id' => current_customer_id(),
            ];

            $order = OrderRepo::getInstance()->builder($filters)->first();

            if (!$order) {
                return json_fail(trans('Paystack::common.order_not_found'));
            }

            Log::info('Initializing Paystack payment for order: ' . $order->number);

            $paystackService = new PaystackService($order);
            $response = $paystackService->initializeTransaction();

            if (!$response['status']) {
                Log::error('Paystack initialization failed: ' . json_encode($response));
                return json_fail(trans('Paystack::common.initialize_fail'));
            }

            // Store payment record with reference
            $paymentData = [
                'amount' => $order->total,
                'paid' => false,
                'reference' => $response['data']['reference'],
            ];
            PaymentRepo::getInstance()->createOrUpdatePayment($order->id, $paymentData);

            Log::info('Paystack initialization successful. Reference: ' . $response['data']['reference']);

            return json_success(trans('Paystack::common.initialize_success'), [
                'authorization_url' => $response['data']['authorization_url'],
                'reference' => $response['data']['reference'],
            ]);

        } catch (\Exception $e) {
            Log::error('Paystack initialize error: ' . $e->getMessage());
            return json_fail($e->getMessage());
        }
    }

    /**
     * Verify Paystack payment
     *
     * @param  Request  $request
     * @return JsonResponse
     * @throws \Throwable
     */
    public function verify(Request $request): JsonResponse
    {
        try {
            $reference = $request->get('reference');
            $orderNumber = $request->get('order_number');

            if (!$reference || !$orderNumber) {
                return json_fail(trans('Paystack::common.verify_fail'));
            }

            $order = OrderRepo::getInstance()->getOrderByNumber($orderNumber);

            if (!$order) {
                return json_fail(trans('Paystack::common.order_not_found'));
            }

            $paystackService = new PaystackService($order);
            $response = $paystackService->verifyTransaction($reference);

            if (!$response['status'] || $response['data']['status'] !== 'success') {
                return json_fail(trans('Paystack::common.verify_fail'));
            }

            $expectedAmount = $paystackService->getOrderAmountInSubunit();
            $actualAmount = (int) ($response['data']['amount'] ?? 0);
            if ($actualAmount !== $expectedAmount) {
                Log::warning('Paystack verify amount mismatch', [
                    'order_number' => $order->number,
                    'reference' => $reference,
                    'expected' => $expectedAmount,
                    'actual' => $actualAmount,
                ]);

                return json_fail(trans('Paystack::common.verify_fail'));
            }

            // Update payment record
            $paymentData = [
                'charge_id' => $response['data']['reference'],
                'amount' => $order->total,
                'paid' => true,
                'reference' => json_encode($response['data']),
            ];
            PaymentRepo::getInstance()->createOrUpdatePayment($order->id, $paymentData);

            // Update order status
            StateMachineService::getInstance($order)->setShipment()->changeStatus(StateMachineService::PAID);

            return json_success(trans('Paystack::common.payment_success'), []);

        } catch (\Exception $e) {
            Log::error('Paystack verify error: ' . $e->getMessage());
            return json_fail($e->getMessage());
        }
    }

    /**
     * Webhook endpoint from Paystack
     * Handles both GET (user redirect) and POST (webhook notification)
     * Configure in Paystack Dashboard > Settings > Webhooks
     * Test URL: https://yourdomain.com/webhook/paystack (or /paystack/webhook)
     * 
     * https://paystack.com/docs/webhooks
     *
     * @param  Request  $request
     * @return JsonResponse|\Illuminate\Http\Response
     */
    public function webhook(Request $request)
    {
        Log::info('====== Paystack Webhook/Callback ======');
        Log::info('Method: ' . $request->method());
        Log::info('Query: ' . json_encode($request->query()));
        Log::info('Body: ' . $request->getContent());

        try {
            // Handle POST webhook from Paystack
            return $this->handlePaystackWebhook($request);
        } catch (\Exception $e) {
            Log::error('Webhook handler error: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());

            return json_success('Error received');
        }
    }

    /**
     * Handle GET redirect after user completes payment on Paystack
     *
     * @param  Request  $request
     * @return \Illuminate\Http\Response
     */
    private function handlePaystackCallback(Request $request)
    {
        $reference = $request->get('reference') ?? $request->get('trxref');
        
        Log::info('=== Callback START ===');
        Log::info('Reference: ' . $reference);

        if (!$reference) {
            Log::warning('No reference provided in callback');
            return $this->redirectToPaymentResult(null, 'Invalid reference');
        }

        // Extract order number from reference (format: order_NUMBER_TIMESTAMP)
        $parts = explode('_', $reference);
        Log::info('Reference parts: ' . json_encode($parts));
        
        if (count($parts) < 2) {
            Log::warning('Invalid reference format: ' . $reference);
            return $this->redirectToPaymentResult(null, 'Invalid reference format');
        }

        $orderNumber = $parts[1];
        Log::info('Order number extracted: ' . $orderNumber);
        
        try {
            $order = OrderRepo::getInstance()->getOrderByNumber($orderNumber);
            Log::info('Order lookup result: ' . json_encode($order ? ['id' => $order->id, 'number' => $order->number] : 'null'));

            if (!$order) {
                Log::warning('Order not found for number: ' . $orderNumber);
                return $this->redirectToPaymentResult(null, 'Order not found');
            }

            Log::info('Verifying payment with Paystack...');
            $paystackService = new PaystackService($order);
            $response = $paystackService->verifyTransaction($reference);

            if (!is_array($response) || !isset($response['status'])) {
                Log::warning('Invalid verification response format', ['response' => $response]);
                return $this->redirectToPaymentResult($orderNumber, 'Payment verification failed');
            }

            Log::info('Verification response status: ' . ($response['status'] ? 'true' : 'false'));

            if (!$response['status']) {
                Log::warning('Payment API returned false status');
                return $this->redirectToPaymentResult($orderNumber, 'Payment verification failed');
            }

            $paymentStatus = $response['data']['status'] ?? null;
            Log::info('Payment status from Paystack: ' . $paymentStatus);
            
            if ($paymentStatus !== 'success') {
                Log::warning('Payment not successful. Status: ' . $paymentStatus);
                return $this->redirectToPaymentResult($orderNumber, 'Payment status: ' . $paymentStatus);
            }

            $expectedAmount = $paystackService->getOrderAmountInSubunit();
            $actualAmount = (int) ($response['data']['amount'] ?? 0);
            if ($actualAmount !== $expectedAmount) {
                Log::warning('Paystack callback amount mismatch', [
                    'order_number' => $orderNumber,
                    'reference' => $reference,
                    'expected' => $expectedAmount,
                    'actual' => $actualAmount,
                ]);

                return $this->redirectToPaymentResult($orderNumber, 'Payment amount verification failed');
            }

            // Update payment record
            Log::info('Updating payment record...');
            $paymentData = [
                'charge_id' => $response['data']['reference'],
                'amount' => $order->total,
                'paid' => true,
                'reference' => json_encode($response['data']),
            ];
            PaymentRepo::getInstance()->createOrUpdatePayment($order->id, $paymentData);
            Log::info('Payment record updated');

            // Update order status
            Log::info('Updating order status to PAID...');
            StateMachineService::getInstance($order)->setShipment()->changeStatus(StateMachineService::PAID);
            Log::info('Order status updated');

            Log::info('=== Callback SUCCESS ===');
            return $this->redirectToPaymentResult($orderNumber, null);

        } catch (\Throwable $e) {
            Log::error('=== Callback ERROR ===');
            Log::error('Message: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
            return $this->redirectToPaymentResult($orderNumber ?? null, $e->getMessage());
        }
    }

    /**
     * Handle POST webhook from Paystack
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    private function handlePaystackWebhook(Request $request): JsonResponse
    {

        try {
            $secretKey = plugin_setting('paystack.secret_key');
            $signature = $request->header('x-paystack-signature');
            $payload = $request->getContent();

            // Verify webhook signature
            if (empty($secretKey) || empty($signature)) {
                Log::warning('Missing Paystack webhook secret/signature');
                return json_success('Event received');
            }

            $computedSignature = hash_hmac('sha512', $payload, $secretKey);
            if (!hash_equals($computedSignature, $signature)) {
                Log::warning('Invalid Paystack webhook signature');
                return json_success('Event received');
            }

            $requestData = $request->json()->all();
            Log::info('Paystack callback data: ' . json_encode($requestData));

            $event = $requestData['event'] ?? '';
            $data = $requestData['data'] ?? [];

            if ($event === 'charge.success' && isset($data['metadata']['order_number'])) {
                $orderNumber = $data['metadata']['order_number'];
                $order = OrderRepo::getInstance()->getOrderByNumber($orderNumber);

                if ($order) {
                    $paystackService = new PaystackService($order);
                    $expectedAmount = $paystackService->getOrderAmountInSubunit();
                    $actualAmount = (int) ($data['amount'] ?? 0);

                    if ($actualAmount !== $expectedAmount) {
                        Log::warning('Paystack webhook amount mismatch', [
                            'order_number' => $orderNumber,
                            'reference' => $data['reference'] ?? null,
                            'expected' => $expectedAmount,
                            'actual' => $actualAmount,
                        ]);

                        return json_success('Event received');
                    }

                    // Update payment record
                    $paymentData = [
                        'charge_id' => $data['reference'] ?? null,
                        'amount' => $order->total,
                        'paid' => true,
                        'reference' => json_encode($data),
                    ];
                    PaymentRepo::getInstance()->createOrUpdatePayment($order->id, $paymentData);

                    // Update order status
                    StateMachineService::getInstance($order)->setShipment()->changeStatus(StateMachineService::PAID);

                    Log::info('Order ' . $orderNumber . ' paid successfully via Paystack');
                    return json_success(trans('Paystack::common.payment_success'));
                }
            }

            return json_success('Event received');

        } catch (\Throwable $e) {
            Log::error('Paystack webhook error: ' . $e->getMessage());
            return json_success('Error processed');
        }
    }

    /**
     * Redirect to payment result page safely.
     *
     * @param  string|null  $orderNumber
     * @param  string|null  $error
     * @return RedirectResponse
     */
    private function redirectToPaymentResult(?string $orderNumber, ?string $error): RedirectResponse
    {
        $params = [];
        if ($orderNumber) {
            $params['order_number'] = $orderNumber;
        }

        try {
            $url = front_route($error ? 'payment.fail' : 'payment.success', $params);
            $redirect = redirect()->to($url);
        } catch (\Throwable $e) {
            $path = $error ? '/payment/fail' : '/payment/success';
            if ($orderNumber) {
                $path .= '?order_number=' . urlencode($orderNumber);
            }
            $redirect = redirect($path);
        }

        if ($error) {
            return $redirect->with('error', $error);
        }

        return $redirect->with('success', 'Payment successful');
    }

    /**
     * Callback endpoint for Paystack return URL
     * Handles the redirect after user completes/cancels payment
     *
     * @param  Request  $request
     * @return \Illuminate\Http\Response
     */
    public function callback(Request $request)
    {
        return $this->handlePaystackCallback($request);
    }
}
