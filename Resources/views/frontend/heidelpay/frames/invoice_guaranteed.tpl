{block name="frontend_checkout_confirm_heidelpay_frames_invoice_guaranteed"}
    <div class="heidelpay--invoice-wrapper"
        {block name="frontend_checkout_confirm_heidelpay_frames_invoice_guaranteed_wrapper_data"}
            data-heidelpay-invoice-guaranteed="true"
            data-heidelpayCreatePaymentUrl="{url controller=HeidelpayInvoiceGuaranteed action=createPayment module=widgets}"
            data-isB2bCustomer="{$sUserData.billingaddress.company}"
            data-heidelpayCustomerDataUrl="{url controller=Heidelpay action=getCustomerData module=frontend}"
        {/block}>

        {if !$sUserData.billingaddress.company}
            <div class="heidelpay--b2c-form">
                {block name="frontend_checkout_confirm_heidelpay_frames_invoice_guaranteed_birthday_label"}
                    <label for="heidelpayBirthday" class="is--block">
                        {s name="label/birthday" namespace="frontend/heidelpay/frames"}{/s}
                    </label>
                {/block}

                {block name="frontend_checkout_confirm_heidelpay_frames_invoice_guaranteed_birthday"}
                    <input type="text"
                           id="heidelpayBirthday"
                           placeholder="{s name="placeholder/birthday" namespace="frontend/heidelpay/frames"}{/s}"
                           {if $sUserData.additional.user.birthday !== ''}value="{$sUserData.additional.user.birthday}"{/if}
                           data-datepicker="true"
                           data-allowInput="true"
                           data-dateFormat="d.m.Y"
                           data-altInput="false"/>
                {/block}
            </div>
        {/if}

        {block name="frontend_checkout_confirm_heidelpay_frames_invoice_guaranteed_b2b_fields"}
            <div id="heidelpay--invoice-guaranteed-container" class="heidelpay-b2b-form heidelpayUI form">
            </div>
        {/block}
    </div>
{/block}
