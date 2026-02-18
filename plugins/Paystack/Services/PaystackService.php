<?php
/**
 * Copyright (c) Since 2024 InnoShop - All Rights Reserved
 *
 * @link       https://www.innoshop.com
 * @author     InnoShop <team@innoshop.com>
 * @license    https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace Plugin\Paystack\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use InnoShop\Common\Libraries\Currency;
use InnoShop\Common\Models\Country;
use InnoShop\Front\Services\PaymentService;

class PaystackService extends PaymentService
{
    private const PAYSTACK_API_URL = 'https://api.paystack.co';
    private string $secretKey;

    /**
     * @throws Exception
     */
    public function __construct($order)
    {
        parent::__construct($order);
        $this->secretKey = plugin_setting('paystack.secret_key');
        if (empty($this->secretKey)) {
            throw new Exception('Invalid Paystack secret key');
        }
    }

    /**
     * Initialize a Paystack payment transaction
     *
     * @return array
     * @throws Exception
     */
    public function initializeTransaction(): array
    {
        $total = round(Currency::getInstance()->convertByRate($this->order->total, $this->order->currency_value), 2) * 100;

        $data = [
            'amount'          => $total,
            'email'           => $this->order->email,
            'metadata'        => [
                'order_number'  => $this->order->number,
                'customer_id'   => $this->order->customer_id,
                'customer_name' => $this->order->customer_name,
            ],
            'reference'       => 'order_' . $this->order->number . '_' . time(),
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type'  => 'application/json',
        ])->post(self::PAYSTACK_API_URL . '/transaction/initialize', $data);

        if (!$response->successful()) {
            throw new Exception('Failed to initialize payment: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Verify a Paystack payment transaction
     *
     * @param  string  $reference
     * @return array
     * @throws Exception
     */
    public function verifyTransaction(string $reference): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
        ])->get(self::PAYSTACK_API_URL . '/transaction/verify/' . $reference);

        if (!$response->successful()) {
            throw new Exception('Failed to verify payment: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Get mobile payment data for uniapp
     *
     * @return array
     * @throws Exception
     */
    public function getMobilePaymentData(): array
    {
        $initResponse = $this->initializeTransaction();

        if (!$initResponse['status']) {
            throw new Exception('Failed to initialize payment');
        }

        return [
            'isAllowDelay'    => true,
            'merchantName'    => plugin_setting('base.meta_title'),
            'accessCode'      => $initResponse['data']['access_code'],
            'authorizationUrl' => $initResponse['data']['authorization_url'],
            'reference'       => $initResponse['data']['reference'],
            'publicKey'       => plugin_setting('paystack.public_key'),
        ];
    }

    /**
     * Get customer data for transaction
     *
     * @return array
     */
    private function getCustomerData(): array
    {
        $paymentCountry = Country::query()->find($this->order->payment_country_id);

        return [
            'first_name'    => explode(' ', $this->order->customer_name)[0] ?? $this->order->customer_name,
            'last_name'     => implode(' ', array_slice(explode(' ', $this->order->customer_name), 1)) ?? '',
            'email'         => $this->order->email,
            'phone'         => $this->order->telephone ?: $this->order->payment_telephone,
            'city'          => $this->order->payment_city,
            'country'       => $paymentCountry->name ?? '',
            'address'       => $this->order->payment_address_1,
            'postal_code'   => $this->order->payment_zipcode,
        ];
    }

    /**
     * Get shipping address data
     *
     * @return array
     */
    private function getShippingAddress(): array
    {
        $shippingCountry = Country::query()->find($this->order->shipping_country_id);

        return [
            'name'    => $this->order->shipping_customer_name,
            'phone'   => $this->order->shipping_telephone,
            'city'    => $this->order->shipping_city,
            'country' => $shippingCountry->name ?? '',
            'address' => $this->order->shipping_address_1,
            'postal_code' => $this->order->shipping_zipcode,
        ];
    }
}
