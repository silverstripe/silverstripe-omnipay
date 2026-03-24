/**
 * Mounts Stripe Payment Element when the mount node has data-publishable-key and data-client-secret.
 * Expects Stripe.js to be loaded first (registered separately via Requirements).
 */
(function () {
  function initStripePaymentElement() {
    var mount = document.querySelector('[data-stripe-payment-element]');
    if (!mount || typeof Stripe === 'undefined') {
      return;
    }
    var pk = mount.getAttribute('data-publishable-key');
    var clientSecret = mount.getAttribute('data-client-secret');
    if (!pk || !clientSecret) {
      return;
    }
    var appearance = {};
    try {
      appearance = JSON.parse(mount.getAttribute('data-appearance') || '{}');
    } catch (e) {
      appearance = {};
    }
    var stripe = Stripe(pk);
    var elements = stripe.elements({ clientSecret: clientSecret, appearance: appearance });
    var paymentElement = elements.create('payment');
    paymentElement.mount(mount);
    var form = mount.closest('form');
    if (!form) {
      return;
    }
    var hiddenInput = form.querySelector('.stripe-payment-element__payment-method input[type="hidden"]');
    if (!hiddenInput) {
      return;
    }
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      elements.submit().then(function (submitResult) {
        if (submitResult && submitResult.error) {
          return;
        }
        return stripe.createPaymentMethod({ elements: elements }).then(function (pmResult) {
          if (!pmResult || pmResult.error || !pmResult.paymentMethod) {
            return;
          }
          hiddenInput.value = pmResult.paymentMethod.id;
          if (typeof HTMLFormElement !== 'undefined' && HTMLFormElement.prototype.submit) {
            HTMLFormElement.prototype.submit.call(form);
          } else {
            form.submit();
          }
        });
      });
    });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initStripePaymentElement);
  } else {
    initStripePaymentElement();
  }
})();
