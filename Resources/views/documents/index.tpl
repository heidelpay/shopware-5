{extends file="parent:documents/index.tpl"}

{block name="document_index_info_ordercomment"}
    {$smarty.block.parent}

    {if ({config name="populate_document_invoice" namespace="heidel_payment"} == true && $heidelPaymentIsInvoice)
        || ({config name="populate_document_prepayment" namespace="heidel_payment"} == true && $heidelPaymentIsPrePayment)}
        <pagebreak />
        {if $CustomDocument.HeidelPayment_Info}
            {eval var=$CustomDocument.HeidelPayment_Info.value}
        {/if}
    {/if}
{/block}

{block name="document_index_footer"}
    {if ({config name="populate_document_invoice" namespace="heidel_payment"} == true && $heidelPaymentIsInvoice)
        || ({config name="populate_document_prepayment" namespace="heidel_payment"} == true && $heidelPaymentIsPrePayment)}
        <div id="footer">
            {if $CustomDocument.HeidelPayment_Footer}
                {eval var=$CustomDocument.HeidelPayment_Footer.value}
            {/if}
        </div>
        {if !$smarty.foreach.pagingLoop.last}
            <pagebreak />
        {/if}
    {else}
        {$smarty.block.parent}
    {/if}
{/block}
