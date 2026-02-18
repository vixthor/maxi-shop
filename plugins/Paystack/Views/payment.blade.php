@php
  $orderNumber = request()->get('order_number') ?? (isset($order) ? $order->number : '');
@endphp

<div id="paystack-payment" class="paystack-container">
  <div class="payment-info">
    <h3>@lang('Paystack::common.payment_title')</h3>
    <p style="font-size: 14px; color: #666;">@lang('Paystack::common.loading')</p>
    <button id="pay-btn" class="btn btn-primary" type="button" style="margin-top: 15px;">
      @lang('Paystack::common.proceed_payment')
    </button>
  </div>
  <div id="loading-spinner" style="display:none; text-align:center; margin-top:15px;">
    <div class="spinner-border" role="status">
      <span class="sr-only">@lang('Paystack::common.loading')</span>
    </div>
    <p style="margin-top: 10px;">@lang('Paystack::common.loading')</p>
  </div>
  <div id="error-message" style="display:none; text-align:center; margin-top:15px; color: #d32f2f; padding: 10px; background-color: #ffebee; border-radius: 4px;">
  </div>
</div>

<script>
  const payBtn = document.getElementById('pay-btn');
  const loadingSpinner = document.getElementById('loading-spinner');
  const errorMessage = document.getElementById('error-message');
  const orderNumber = '{{ $orderNumber }}';

  // Auto-initialize if order number exists
  if (orderNumber) {
    payBtn.style.display = 'none';
    loadingSpinner.style.display = 'block';
    initializePayment();
  } else {
    payBtn.addEventListener('click', initializePayment);
  }

  function initializePayment() {
    loadingSpinner.style.display = 'block';
    errorMessage.style.display = 'none';
    payBtn.disabled = true;

    fetch('/paystack/initialize', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
      },
      body: JSON.stringify({
        order_number: orderNumber
      })
    })
    .then(response => response.json())
    .then(data => {
      console.log('Initialize response:', data);
      
      if (data.status && data.data && data.data.authorization_url) {
        // Redirect to Paystack checkout page
        window.location.href = data.data.authorization_url;
      } else {
        const message = (data.data && typeof data.data === 'string') ? data.data : 
                       (data.message || '@lang("Paystack::common.initialize_fail")');
        showError(message);
      }
    })
    .catch(error => {
      console.error('Error:', error);
      showError('@lang("Paystack::common.initialize_fail")');
    });
  }

  function showError(message) {
    loadingSpinner.style.display = 'none';
    errorMessage.style.display = 'block';
    errorMessage.textContent = message;
    payBtn.disabled = false;
    payBtn.style.display = 'block';
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
    cursor: pointer;
  }

  .btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }

  .spinner-border {
    display: inline-block;
    width: 2rem;
    height: 2rem;
    vertical-align: text-bottom;
    border: 0.25em solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;
    -webkit-animation: spinner-border 0.75s linear infinite;
    animation: spinner-border 0.75s linear infinite;
  }

  @keyframes spinner-border {
    to {
      -webkit-transform: rotate(360deg);
      transform: rotate(360deg);
    }
  }
</style>
