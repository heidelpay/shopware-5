{block name="frontend_account_payment_content_heidelpay_vault_paypal"}
    <div class="panel heidelpay-vault--device-group">
        {block name="frontend_account_payment_content_heidelpay_vault_paypal_title"}
            <div class="panel--title">
                {s name="title"}{/s}
            </div>
        {/block}

        {block name="frontend_account_payment_content_heidelpay_vault_paypal_body"}
            <div class="panel--body">
                {foreach $devices as $paypalAccount}
                    <div class="panel has--border is--rounded heidelpay--paypal-wrapper">
                        <div class="panel--body">
                            {block name="frontend_account_payment_content_heidelpay_vault_paypal_body_email"}
                                <div class="heidelpay-vault--paypal-email is--bold">
                                    {s name="field/email"}{/s} {$paypalAccount->getEmail()}
                                </div>
                            {/block}
                            {block name="frontend_account_payment_content_heidelpay_vault_credit_card_body_actions"}
                                <div class="heidelpay-vault--credit-card-actions">
                                    <a href="{url controller=HeidelpayDeviceVault action=deleteDevice id=$paypalAccount->getId()}">{s name="link/delete" namespace="frontend/heidelpay/account/payment_device_vault"}{/s}</a>
                                </div>
                            {/block}
                        </div>
                    </div>
                {/foreach}
            </div>
        {/block}
    </div>
{/block}
