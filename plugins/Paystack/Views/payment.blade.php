@php
  $orderNumber = request()->get('order_number');
@endphp

<div id="paystack-payment" class="paystack-container">
  <div class="payment-info">
    <h3>@lang('Paystack::common.payment_title')</h3>
    <button id="pay-btn" class="btn btn-primary" type="button">
      @lang('Paystack::common.proceed_payment')
    </button>
  </div>
  <div id="loading-spinner" style="display:none; text-align:center; margin-top:15px;">
    <div class="spinner-border" role="status">
      <span class="sr-only">@lang('Paystack::common.loading')</span>
    </div>
  </div>
</div>

<script>
  const payBtn = document.getElementById('pay-btn');
  const loadingSpinner = document.getElementById('loading-spinner');
  const orderNumber = '{{ $orderNumber }}';

  payBtn.addEventListener('click', initializePayment);

  function initializePayment() {
    payBtn.disabled = true;
    loadingSpinner.style.display = 'block';

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
      if (data.status && data.data.authorization_url) {
        // Redirect to Paystack checkout page
        window.location.href = data.data.authorization_url;
      } else {
        showError(data.message || '@lang("Paystack::common.initialize_fail")');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      showError('@lang("Paystack::common.initialize_fail")');
    });
  }

  function showError(message) {
    loadingSpinner.style.display = 'none';
    payBtn.disabled = false;
    alert(message);
  }
</script>

<style>
  .paystack-container {
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 5px;
    background-color: #f9f9f9;
  }

  .payment-info {
    text-align: center;
  }

  .payment-info h3 {
    margin-bottom: 20px;
    color: #333;
  }

  .btn {
    padding: 10px 30px;
    font-size: 16px;
  }

  .btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }

  .spinner-border {
    width: 2rem;
    height: 2rem;
  }
</style>
