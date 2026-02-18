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

        try {
            // Handle GET redirect from Paystack (after user completes payment)
            if ($request->isMethod('get')) {
                return $this->handlePaystackCallback($request);
            }

            // Handle POST webhook from Paystack
            return $this->handlePaystackWebhook($request);
        } catch (\Exception $e) {
            Log::error('Webhook handler error: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
            
            if ($request->isMethod('get')) {
                return redirect()->route('payment.success')->with('error', 'Callback error: ' . $e->getMessage());
            }
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
            return redirect()->route('payment.success')->with('error', 'Invalid reference');
        }

        // Extract order number from reference (format: order_NUMBER_TIMESTAMP)
        $parts = explode('_', $reference);
        Log::info('Reference parts: ' . json_encode($parts));
        
        if (count($parts) < 2) {
            Log::warning('Invalid reference format: ' . $reference);
            return redirect()->route('payment.success')->with('error', 'Invalid reference format');
        }

        $orderNumber = $parts[1];
        Log::info('Order number extracted: ' . $orderNumber);
        
        try {
            $order = OrderRepo::getInstance()->getOrderByNumber($orderNumber);
            Log::info('Order lookup result: ' . json_encode($order ? ['id' => $order->id, 'number' => $order->number] : 'null'));

            if (!$order) {
                Log::warning('Order not found for number: ' . $orderNumber);
                return redirect()->route('payment.success')->with('error', 'Order not found');
            }

            Log::info('Verifying payment with Paystack...');
            $paystackService = new PaystackService($order);
            $response = $paystackService->verifyTransaction($reference);

            Log::info('Verification response status: ' . ($response['status'] ? 'true' : 'false'));

            if (!$response['status']) {
                Log::warning('Payment API returned false status');
                return redirect()->route('payment.success')->with('error', 'Payment verification failed');
            }

            $paymentStatus = $response['data']['status'] ?? null;
            Log::info('Payment status from Paystack: ' . $paymentStatus);
            
            if ($paymentStatus !== 'success') {
                Log::warning('Payment not successful. Status: ' . $paymentStatus);
                return redirect()->route('payment.success')->with('error', 'Payment status: ' . $paymentStatus);
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
            return redirect()->route('payment.success', ['order_number' => $orderNumber])->with('success', 'Payment successful');

        } catch (\Exception $e) {
            Log::error('=== Callback ERROR ===');
            Log::error('Message: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
            return redirect()->route('payment.success')->with('error', $e->getMessage());
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
