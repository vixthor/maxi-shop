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

            $paystackService = new PaystackService($order);
            $response = $paystackService->initializeTransaction();

            if (!$response['status']) {
                return json_fail(trans('Paystack::common.initialize_fail'));
            }

            $paymentData = [
                'amount' => $order->total,
                'paid' => false,
                'reference' => $response['data']['reference'],
            ];
            PaymentRepo::getInstance()->createOrUpdatePayment($order->id, $paymentData);

            return json_success([
                'reference' => $response['data']['reference'],
                'access_code' => $response['data']['access_code'],
                'authorization_url' => $response['data']['authorization_url'],
            ], trans('Paystack::common.initialize_success'));

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

            // Update payment record
            $paymentData = [
                'charge_id' => $response['data']['reference'],
                'amount' => $order->total,
                'paid' => true,
                'reference' => $response['data'],
            ];
            PaymentRepo::getInstance()->createOrUpdatePayment($order->id, $paymentData);

            // Update order status
            StateMachineService::getInstance($order)->setShipment()->changeStatus(StateMachineService::PAID);

            return json_success([], trans('Paystack::common.payment_success'));

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

        // Handle GET redirect from Paystack (after user completes payment)
        if ($request->isMethod('get')) {
            return $this->handlePaystackCallback($request);
        }

        // Handle POST webhook from Paystack
        return $this->handlePaystackWebhook($request);
    }

    /**
     * Handle GET redirect after user completes payment on Paystack
     *
     * @param  Request  $request
     * @return \Illuminate\Http\Response
     */
    private function handlePaystackCallback(Request $request)
    {
        try {
            $reference = $request->get('reference') ?? $request->get('trxref');
            
            Log::info('Paystack callback reference: ' . $reference);

            if (!$reference) {
                return response('Invalid reference', 400);
            }

            // Extract order number from reference (format: order_NUMBER_TIMESTAMP)
            $parts = explode('_', $reference);
            if (count($parts) < 2) {
                Log::warning('Invalid reference format: ' . $reference);
                return response('Invalid reference format', 400);
            }

            $orderNumber = $parts[1];
            $order = OrderRepo::getInstance()->getOrderByNumber($orderNumber);

            if (!$order) {
                Log::warning('Order not found: ' . $orderNumber);
                return response('Order not found', 404);
            }

            try {
                $paystackService = new PaystackService($order);
                $response = $paystackService->verifyTransaction($reference);

                if (!$response['status'] || $response['data']['status'] !== 'success') {
                    Log::warning('Payment verification failed for: ' . $reference);
                    return redirect()->route('order.payment.failed', ['order_number' => $orderNumber]);
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

                Log::info('Order ' . $orderNumber . ' paid successfully via Paystack callback');
                return redirect()->route('order.payment.success', ['order_number' => $orderNumber]);

            } catch (\Exception $e) {
                Log::error('Payment verification error: ' . $e->getMessage());
                return redirect()->route('order.payment.failed', ['order_number' => $orderNumber]);
            }

        } catch (\Exception $e) {
            Log::error('Paystack callback error: ' . $e->getMessage());
            return response('Error processing callback', 500);
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
            $webhookSecret = plugin_setting('paystack.webhook_secret');
            $signature = $request->header('x-paystack-signature');

            // Verify webhook signature
            if ($webhookSecret) {
                $computedSignature = hash_hmac('sha512', $request->getContent(), $webhookSecret);
                if ($signature !== $computedSignature) {
                    Log::warning('Invalid Paystack webhook signature');
                    return json_fail('Invalid signature');
                }
            }

            $requestData = $request->json()->all();
            Log::info('Paystack callback data: ' . json_encode($requestData));

            $event = $requestData['event'] ?? '';
            $data = $requestData['data'] ?? [];

            if ($event === 'charge.success' && isset($data['metadata']['order_number'])) {
                $orderNumber = $data['metadata']['order_number'];
                $order = OrderRepo::getInstance()->getOrderByNumber($orderNumber);

                if ($order) {
                    // Update payment record
                    $paymentData = [
                        'charge_id' => $data['reference'],
                        'amount' => $order->total,
                        'paid' => true,
                        'reference' => $data,
                    ];
                    PaymentRepo::getInstance()->createOrUpdatePayment($order->id, $paymentData);

                    // Update order status
                    StateMachineService::getInstance($order)->setShipment()->changeStatus(StateMachineService::PAID);

                    Log::info('Order ' . $orderNumber . ' paid successfully via Paystack');
                    return json_success(trans('Paystack::common.payment_success'));
                }
            }

            return json_success('Event received');

        } catch (\Exception $e) {
            Log::error('Paystack webhook error: ' . $e->getMessage());
            return json_success('Error processed');
        }
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
