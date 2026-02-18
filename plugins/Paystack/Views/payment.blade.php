@php
  $orderNumber = request()->get('order_number');
@endphp

<div id="paystack-payment" class="paystack-container">
  <div class="payment-info">
    <h3>@lang('Paystack::common.payment_title')</h3>
    <button id="pay-btn" class="btn btn-primary">
      @lang('Paystack::common.proceed_payment')
    </button>
  </div>
</div>

<script src="https://js.paystack.co/v1/inline.js"></script>
<script>
  const payBtn = document.getElementById('pay-btn');
  const orderNumber = '{{ $orderNumber }}';

  payBtn.addEventListener('click', initializePayment);

  function initializePayment() {
    payBtn.disabled = true;
    payBtn.textContent = '@lang("Paystack::common.loading")';

    fetch('/paystack/initialize', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify({
        order_number: orderNumber
      })
    })
    .then(response => response.json())
    .then(data => {
      if (data.status) {
        payWithPaystack(data.data);
      } else {
        alert(data.message || '@lang("Paystack::common.initialize_fail")');
        payBtn.disabled = false;
        payBtn.textContent = '@lang("Paystack::common.proceed_payment")';
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('@lang("Paystack::common.initialize_fail")');
      payBtn.disabled = false;
      payBtn.textContent = '@lang("Paystack::common.proceed_payment")';
    });
  }

  function payWithPaystack(paymentData) {
    const handler = PaystackPop.setup({
      key: paymentData.authorization_url.split('key=')[1],
      email: '{{ $order->email ?? "" }}',
      amount: {{ ($order->total ?? 0) * 100 }},
      ref: paymentData.reference,
      currency: '{{ $order->currency_code ?? "NGN" }}',
      onClose: function() {
        alert('@lang("Paystack::common.payment_fail")');
        payBtn.disabled = false;
        payBtn.textContent = '@lang("Paystack::common.proceed_payment")';
      },
      onSuccess: function(response) {
        verifyPayment(response.reference);
      }
    });
    handler.openIframe();
  }

  function verifyPayment(reference) {
    fetch('/paystack/verify', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify({
        reference: reference,
        order_number: orderNumber
      })
    })
    .then(response => response.json())
    .then(data => {
      if (data.status) {
        alert('@lang("Paystack::common.payment_success")');
        window.location.href = '/checkout/success?order_number=' + orderNumber;
      } else {
        alert(data.message || '@lang("Paystack::common.payment_fail")');
        payBtn.disabled = false;
        payBtn.textContent = '@lang("Paystack::common.proceed_payment")';
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('@lang("Paystack::common.payment_fail")');
      payBtn.disabled = false;
      payBtn.textContent = '@lang("Paystack::common.proceed_payment")';
    });
  }
</script>

<style>
  .paystack-container {
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 5px;
  }

  .payment-info {
    text-align: center;
  }

  .pay-btn {
    margin-top: 15px;
    padding: 10px 30px;
    font-size: 16px;
  }
</style>
