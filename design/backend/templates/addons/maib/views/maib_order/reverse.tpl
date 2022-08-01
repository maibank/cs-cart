
<form action="{"maib_order.reverse"|fn_url}" method="post">
<div class="control-group">
    <div class="control-label">
        {__("maib.amount_to_reverse")} ({$maib_currency}):
    </div>
    <div class="controls">
        <div id="tracking_number_{$shipment_key}">
            <input class="input-small" type="number" min="0" max="{$order_info.total}" step="any" value="0.00" name="maib_reverse_amount"/>
        </div>
    </div>
    <a class="cm-dialog-closer cm-cancel tool-link btn">{__("cancel")}</a>
    <input class="btn cm-process-items btn-primary" type="submit"  value="{__("maib.reverse_amount")}" />
    <input type="hidden" name="order_id" value="{$order_info.order_id}" />
</div>
</form>
