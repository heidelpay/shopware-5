{block name="frontend_checkout_confirm_heidelpay_frames_sepa_vault"}
    <div class="heidelpay--sepa-vault">
        {block name="frontend_checkout_confirm_heidelpay_frames_sepa_vault_message"}
            <div class="heidelpay--vault-message">
                {s name="customerMessage"}{/s}
            </div>
        {/block}
        {foreach $mandates as $sepaMandate}
            {block name="frontend_checkout_confirm_heidelpay_frames_sepa_vault_mandate"}
                <div class="heidelpay--sepa-vault-item panel has--border">
                    <div class="panel--body">
                        {if $sepaMandate->getTypeId() && $sepaMandate->getIban()}
                            {block name="frontend_checkout_confirm_heidelpay_frames_sepa_vault_radio_input"}
                                <input type="radio" id="{$sepaMandate->getTypeId()}"
                                       name="mandateSelection"{if $sepaMandate@first} checked="checked"{/if}>
                            {/block}
                            {block name="frontend_checkout_confirm_heidelpay_frames_sepa_vault_radio_input_label"}
                                <label for="{$sepaMandate->getTypeId()}">
                                    {$sepaMandate->getIban()}
                                </label>
                            {/block}

                            {block name="frontend_checkout_confirm_heidelpay_frames_sepa_vault_birthday_input"}
                                {if $sepaMandate->getBirthDate() !== ''}
                                    <input type="hidden" id="{$sepaMandate->getTypeId()}_birthDate" value="{$sepaMandate->getBirthDate()}">
                                {elseif $sepaMandate->getDeviceType() === 'sepa_mandate_g'}
                                    <br />
                                    <br />
                                    <input type="text"
                                           class="heidelpay--vault-birthday"
                                           id="{$sepaMandate->getTypeId()}_birthDate"
                                           placeholder="{s name="placeholder/birthday" namespace="frontend/heidelpay/frames/invoice"}{/s}"
                                           {if $sUserData.additional.user.birthday !== ''}value="{$sUserData.additional.user.birthday}"{/if}
                                           data-datepicker="true"
                                           data-allowInput="true"
                                           data-dateFormat="d.m.Y"
                                           data-altInput="false"/>
                                {/if}
                            {/block}
                        {/if}
                    </div>
                </div>
            {/block}
        {/foreach}
    </div>
{/block}
