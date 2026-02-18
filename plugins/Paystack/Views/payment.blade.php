@php
  $orderNumber = request()->get('order_number');
  $publicKey = plugin_setting('paystack', 'public_key');
@endphp

<div id="paystack-payment" class="paystack-container">
  <div class="payment-info">
    <h3>@lang('Paystack::common.payment_title')</h3>
    <p>@lang('Paystack::common.proceed_payment')</p>
    <button id="pay-btn" class="btn btn-primary" onclick="initializePayment()">
      @lang('Paystack::common.proceed_payment')
    </button>
  </div>
  <div id="loading" style="display:none; text-align:center; margin-top: 20px;">
    <p>@lang('Paystack::common.loading')</p>
  </div>
</div>

<script>
  const orderNumber = '{{ $orderNumber }}';
  const publicKey = '{{ $publicKey }}';

  function initializePayment() {
    const payBtn = document.getElementById('pay-btn');
    const loading = document.getElementById('loading');
    
    payBtn.disabled = true;
    loading.style.display = 'block';

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
        // Redirect to Paystack authorization URL
        window.location.href = data.data.authorization_url;
      } else {
        alert(data.message || '@lang("Paystack::common.initialize_fail")');
        payBtn.disabled = false;
        loading.style.display = 'none';
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('@lang("Paystack::common.initialize_fail")');
      payBtn.disabled = false;
      loading.style.display = 'none';
    });
  }
</script>

<style>
  .paystack-container {
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 5px;
    max-width: 500px;
    margin: 20px auto;
  }

  .payment-info {
    text-align: center;
  }

  .payment-info h3 {
    margin-bottom: 15px;
    color: #333;
  }

  .payment-info p {
    margin-bottom: 20px;
    color: #666;
  }

  #pay-btn {
    padding: 12px 40px;
    font-size: 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
  }

  #pay-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
</style>

