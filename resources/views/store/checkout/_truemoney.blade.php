{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: paying with a TrueMoney gift voucher (payments-service). --}}
<form method="POST" action="{{ route('store.checkout.tomyum.truemoney', ['checkout' => $order->getKey()]) }}" class="store-payment-method js-tomyum-payment-form" data-turbo="false">
    @csrf
    <input type="hidden" name="hide_from_activity" value="0">

    <div class="store-payment-method__cell">
        <button type="submit" class="store-payment-button store-payment-button--truemoney" title="Pay {{ currency_thb($order->getTotal()) }} with TrueMoney Wallet"></button>
    </div>

    <div class="store-payment-method__cell">
        <div class="store-text store-text--header">Pay with TrueMoney Wallet</div>

        <div class="store-text store-text--block">
            In the TrueMoney app, make a gift voucher (ซองของขวัญ) for exactly
            <span class="store-text store-text--emphasis">{{ currency_thb($order->getTotal()) }}</span>,
            paste its link below, then click the TrueMoney button.
        </div>

        <input
            class="store-supporter-tag__input"
            type="text"
            name="voucher"
            required
            autocomplete="off"
            placeholder="https://gift.truemoney.com/campaign/?v=…"
        >

        <div class="store-text store-text--block">A voucher for a different amount isn't redeemed, so nothing is taken from it.</div>
    </div>
</form>
