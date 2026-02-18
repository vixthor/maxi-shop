@php
  $orderNumber = request()->get('order_number') ?? (isset($order) ? $order->number : '');
@endphp

<div id="paystack-payment" class="paystack-container">
  <div class="payment-info">
    <h3>@lang('Paystack::common.payment_title')</h3>
    <p style="font-size: 14px; color: #666;">Initializing payment...</p>
  </div>
  <div id="loading-spinner">
    <div class="spinner-border" role="status">
      <span class="sr-only">Loading...</span>
    </div>
    <p style="margin-top: 10px;">Please wait, redirecting to payment...</p>
  </div>
  <div id="error-message" style="display:none; text-align:center; margin-top:15px; color: #d32f2f; padding: 10px; background-color: #ffebee; border-radius: 4px;">
  </div>
</div>

<script>
  const loadingSpinner = document.getElementById('loading-spinner');
  const errorMessage = document.getElementById('error-message');
  const orderNumber = '{{ $orderNumber }}';

  console.log('Payment page loaded. Order number:', orderNumber);

  if (orderNumber) {
    initializePayment();
  } else {
    showError('No order number provided');
  }

  function initializePayment() {
    console.log('Initializing Paystack payment...');
    
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
    .then(response => {
      console.log('Initialize response status:', response.status);
      return response.json();
    })
    .then(data => {
      console.log('Initialize response data:', data);
      
      if (data.status && data.data && data.data.authorization_url) {
        console.log('Redirecting to:', data.data.authorization_url);
        // Redirect to Paystack checkout page
        window.location.href = data.data.authorization_url;
      } else {
        const message = (data.data && typeof data.data === 'string') ? data.data : 
                       (data.message || 'Payment initialization failed');
        console.error('Initialize error:', message);
        showError(message);
      }
    })
    .catch(error => {
      console.error('Fetch error:', error);
      showError('Network error: ' + error.message);
    });
  }

  function showError(message) {
    loadingSpinner.style.display = 'none';
    errorMessage.style.display = 'block';
    errorMessage.textContent = message;
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

  #loading-spinner {
    text-align: center;
    margin-top: 20px;
  }

  .spinner-border {
    display: inline-block;
    width: 2rem;
    height: 2rem;
    vertical-align: text-bottom;
    border: 0.25em solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;
    animation: spinner-border 0.75s linear infinite;
  }

  @keyframes spinner-border {
    to {
      transform: rotate(360deg);
    }
  }
</style>
