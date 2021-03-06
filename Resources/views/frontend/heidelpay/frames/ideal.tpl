{block name="frontend_checkout_confirm_heidelpay_frames_ideal"}
    <div class="heidelpay--ideal-wrapper"
        {block name="frontend_checkout_confirm_heidelpay_frames_ideal_wrapper_data"}
             data-heidelpay-ideal="true"
             data-heidelpayCreatePaymentUrl="{url controller=HeidelpayIdeal action=createPayment module=widgets}"
            {/block}>

        {block name="frontend_checkout_confirm_heidelpay_frames_ideal_container_label"}
            <label for="heidelpay--ideal-container">
                {s name="label/bankSelection"}{/s}
            </label>
        {/block}

        {block name="frontend_checkout_confirm_heidelpay_frames_ideal_container"}
            <div id="heidelpay--ideal-container" class="heidelpayUI form">
            </div>
        {/block}
    </div>
{/block}
