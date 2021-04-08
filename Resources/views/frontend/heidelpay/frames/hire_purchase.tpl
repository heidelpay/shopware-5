{block name="frontend_checkout_confirm_heidelpay_frames_hire_purchase"}
    <div class="heidelpay--hire-purchase-wrapper"
        {block name="frontend_checkout_confirm_heidelpay_frames_hire_purchase_data"}
        data-heidelpay-hire-purchase="true"
        data-heidelpayCreatePaymentUrl="{url controller=HeidelpayHirePurchase action=createPayment module=widgets}"
        data-basketAmount="{$sBasket.AmountNumeric}"
        data-currencyIso="{$sBasket.sCurrencyName}"
        data-locale="{$heidelpayLocale}"
        data-starSign="{s name="Star" namespace="frontend/listing/box_article"}{/s}"
        data-effectiveInterest="{$heidelpayEffectiveInterest}"
        {/block}>

        {block name="frontend_checkout_confirm_heidelpay_frames_hire_purchase_container"}
            {if {config name="transaction_mode" namespace="heidel_payment"} === 'test'}
                {include file="frontend/heidelpay/frames/test_data/hire_purchase.tpl"}
            {/if}
        {/block}

        {block name="frontend_checkout_confirm_heidelpay_frames_hire_purchase_container"}
            <div id="heidelpay--hire-purchase-container" class="heidelpayUI form"></div>

            {block name="frontend_checkout_confirm_heidelpay_frames_hire_purchase_birthday_label"}
                <label for="heidelpayBirthday" class="is--block">
                    {s name="label/birthday" namespace="frontend/heidelpay/frames"}{/s}
                </label>
            {/block}

            {block name="frontend_checkout_confirm_heidelpay_frames_hire_purchase_birthday_field"}
                <input type="text"
                       id="heidelpayBirthday"
                       placeholder="{s name="placeholder/birthday" namespace="frontend/heidelpay/frames"}{/s}"
                       {if $sUserData.additional.user.birthday !== ''}value="{$sUserData.additional.user.birthday}"{/if}
                       data-datepicker="true"
                       data-allowInput="true"
                       data-dateFormat="d.m.Y"
                       data-altInput="false"/>
            {/block}
        {/block}
    </div>
{/block}
