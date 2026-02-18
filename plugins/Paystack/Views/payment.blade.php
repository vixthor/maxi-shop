@php
  $orderNumber = request()->get('order_number') ?? (isset($order) ? $order->number : '');
@endphp

<button id="paystack-button" class="btn btn-primary">
  @lang('Paystack::common.proceed_payment')
</button>

<script>
  document.getElementById('paystack-button').addEventListener('click', function () {
    const orderNumber = '{{ $orderNumber }}';
    const button = this;
    button.disabled = true;
    button.textContent = '@lang("Paystack::common.loading")';

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
        // Redirect to Paystack checkout
        window.location.href = data.data.authorization_url;
      } else {
        alert(data.message || '@lang("Paystack::common.initialize_fail")');
        button.disabled = false;
        button.textContent = '@lang("Paystack::common.proceed_payment")';
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('@lang("Paystack::common.initialize_fail")');
      button.disabled = false;
      button.textContent = '@lang("Paystack::common.proceed_payment")';
    });
  });
</script>
