{block name="frontend_checkout_confirm_heidelpay_frames_hire_purchase"}
    <div class="heidelpay--hire-purchase-wrapper"
            {block name="frontend_checkout_confirm_heidelpay_frames_hire_purchase_data"}
        data-heidelpay-hire-purchase="true"
        data-heidelpayCreatePaymentUrl="{url controller=HeidelpayHirePurchase action=createPayment module=widgets}"
        data-basketAmount="{$sBasket.AmountNumeric}"
        data-currencyIso="{$sBasket.sCurrencyName}"
        data-effectiveInterest="{$heidelpayEffectiveInterest}"
            {/block}>
        {block name="frontend_checkout_confirm_heidelpay_frames_hire_purchase_container"}
            <div id="heidelpay--hire-purchase-container" class="heidelpayUI form">
            </div>
            <input type="text"
                   id="heidelpayBirthday"
                   placeholder="{s name="placeholder/birthday" namespace="frontend/heidelpay/frames/invoice"}{/s}"
                   {if $sUserData.additional.user.birthday !== ''}value="{$sUserData.additional.user.birthday}"{/if}
                   data-datepicker="true"
                   data-allowInput="true"/>
        {/block}
    </div>
{/block}
