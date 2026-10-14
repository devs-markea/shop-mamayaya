{{--
    Mercado Pago's device fingerprint (`security.js`). Port of
    src/components/DeviceFingerprint.astro.

    It leaves in the global `MP_DEVICE_SESSION_ID` an identifier of the device the
    purchase is made from: a signal for the gateway's risk engine. It is NOT Mercado
    Pago's SDK and carries no key; it only measures the browser. The value travels as
    `X-Meli-Session-Id` (deviceHeader() in resources/js/lib/checkout.ts), through this
    store's proxy, up to the backend that creates the charge.

    Only the two screens where a charge is born mount it: /mamayaya/pago (checkout with
    Mercado Pago) and /mamayaya/recibido (when it offers to retry). `defer` so it doesn't
    compete with the paint: if it isn't ready in time, the charge goes out without it.

    Docs: mercadopago.com/developers -> Device ID.
--}}
<script defer src="https://www.mercadopago.com/v2/security.js" view="checkout"></script>
