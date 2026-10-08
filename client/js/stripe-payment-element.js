/**
 * Mounts the Stripe Payment Element when the mount node has data-publishable-key and data-options.
 * Expects Stripe.js to be loaded first (registered separately via Requirements).
 *
 * data-options holds the options for stripe.elements() without a PaymentIntent ("deferred intent" mode:
 * mode, amount, currency, paymentMethodCreation: 'manual', ...), see
 * https://docs.stripe.com/js/elements_object/create_without_intent
 * On submit, a payment method is created with createPaymentMethod({ elements }) and its ID is stored in the
 * hidden payment method field. The PaymentIntent is created and confirmed server-side.
 *
 * Submit controls stay disabled until the Payment Element is usable and `change` reports
 * `event.complete` (see https://docs.stripe.com/js/custom_checkout/element_events ).
 *
 * When data-publishable-key contains `_test_`, Stripe is constructed with
 * developerTools.assistant.enabled so the Stripe.js testing assistant is available
 * (see https://docs.stripe.com/sdks/stripejs-testing-assistant ).
 *
 * While elements.submit / createPaymentMethod runs, the form has aria-busy and
 * class stripe-payment-element--submitting so submit controls can show a loading state.
 */
(function () {
  function querySubmitControls(form) {
    return form.querySelectorAll(
      'button[type="submit"], input[type="submit"], button:not([type])',
    );
  }

  function setSubmitEnabled(form, enabled) {
    var controls = querySubmitControls(form);
    for (var i = 0; i < controls.length; i++) {
      controls[i].disabled = !enabled;
    }
  }

  function startSubmitLoading(form) {
    form.setAttribute("aria-busy", "true");
    form.classList.add("stripe-payment-element--submitting");
    setSubmitEnabled(form, false);
  }

  function endSubmitLoading(form, paymentElementComplete) {
    form.removeAttribute("aria-busy");
    form.classList.remove("stripe-payment-element--submitting");
    setSubmitEnabled(form, paymentElementComplete);
  }

  function initStripePaymentElement() {
    if (typeof Stripe === "undefined") {
      console.error(
        "[stripe-payment-element] Stripe.js is not loaded; expected global Stripe.",
      );
      return;
    }

    var mount = document.querySelector("[data-stripe-payment-element]");
    if (!mount) {
      console.error(
        "[stripe-payment-element] No mount node found; expected an element with [data-stripe-payment-element].",
      );
      return;
    }

    var pk = mount.getAttribute("data-publishable-key");
    var options = null;
    try {
      options = JSON.parse(mount.getAttribute("data-options") || "null");
    } catch (e) {
      options = null;
    }

    if (
      !pk ||
      options === null ||
      typeof options !== "object" ||
      Array.isArray(options)
    ) {
      console.error(
        "[stripe-payment-element] Missing data-publishable-key or invalid data-options on the mount node.",
        { hasPublishableKey: !!pk, hasOptions: !!options },
      );
      return;
    }

    var stripeOptions = {};
    if (pk.indexOf("_test_") !== -1) {
      stripeOptions = {
        developerTools: {
          assistant: {
            enabled: true,
          },
        },
      };
    }

    var stripe = Stripe(pk, stripeOptions);
    var elements = stripe.elements(options);

    var paymentElement = elements.create("payment");

    var form = mount.closest("form");
    if (!form) {
      console.error(
        "[stripe-payment-element] Mount node is not inside a <form>; Payment Element must live within the form to submit.",
      );
      return;
    }

    // SilverStripe HiddenField puts extraClass on the input itself (no holder wrapper).
    // Also support a wrapper .stripe-payment-element__payment-method containing the hidden input.
    var hiddenInput = form.querySelector(
      'input.stripe-payment-element__payment-method[type="hidden"], .stripe-payment-element__payment-method input[type="hidden"]',
    );

    if (!hiddenInput) {
      console.error(
        '[stripe-payment-element] Missing hidden payment method field; expected input.stripe-payment-element__payment-method[type="hidden"] (or that class on a wrapper around the hidden input).',
      );
      return;
    }

    // Stripe / Payment Element not ready for submission yet
    var paymentElementComplete = false;
    setSubmitEnabled(form, false);

    paymentElement.on("change", function (event) {
      if (event && typeof event.complete === "boolean") {
        paymentElementComplete = event.complete;
        setSubmitEnabled(form, event.complete);
      }
    });

    paymentElement.on("loaderror", function () {
      paymentElementComplete = false;
      setSubmitEnabled(form, false);
    });

    paymentElement.mount(mount);

    form.addEventListener("submit", function (ev) {
      ev.preventDefault();
      startSubmitLoading(form);
      elements
        .submit()
        .then(function (submitResult) {
          if (submitResult && submitResult.error) {
            endSubmitLoading(form, paymentElementComplete);
            return;
          }
          return stripe
            .createPaymentMethod({ elements: elements })
            .then(function (pmResult) {
              if (!pmResult || pmResult.error || !pmResult.paymentMethod) {
                endSubmitLoading(form, paymentElementComplete);
                return;
              }
              hiddenInput.value = pmResult.paymentMethod.id;
              if (
                typeof HTMLFormElement !== "undefined" &&
                HTMLFormElement.prototype.submit
              ) {
                HTMLFormElement.prototype.submit.call(form);
              } else {
                form.submit();
              }
            });
        })
        .catch(function () {
          endSubmitLoading(form, paymentElementComplete);
        });
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initStripePaymentElement);
  } else {
    initStripePaymentElement();
  }
})();
