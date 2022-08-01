{if $addons.maib.status == "D"}
    <div class="alert alert-block">
	<p>{__("maib.addon_is_disabled")}</p>
    </div>
{else}

<div class="control-group">
    <label class="control-label" for="maib_mode">{__("test_live_mode")}:</label>
    <div class="controls">
        <select name="payment_data[processor_params][mode]" id="maib_mode">
            <option value="test" {if $processor_params.mode == "test"}selected="selected"{/if}>{__("test")}</option>
            <option value="live" {if $processor_params.mode == "live"}selected="selected"{/if}>{__("live")}</option>
        </select>
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="maib_debug_log">{__("maib.debug_log")}:</label>
    <div class="controls">
        <input name="payment_data[processor_params][debug_log]" id="maib_debug_log" type="checkbox" {if $processor_params.debug_log}checked="true"{/if}>
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="maib_transaction_type">{__("maib.transaction_type")}:</label>
    <div class="controls">
        <select name="payment_data[processor_params][transaction_type]" id="maib_transaction_type">
            <option value="capture" {if $processor_params.transaction_type == "capture"}selected="selected"{/if}>{__("maib.capture")}</option>
            <option value="authorize" {if $processor_params.transaction_type == "authorize"}selected="selected"{/if}>{__("maib.authorize")}</option>
        </select>
    </div>
</div>

<div class="control-group">
    <label class="control-label">{__("maib.return_urls")}:</label>
    <div class="controls">
        <div>{fn_url('payment_notification.return?payment=maib', 'C')}</div>
        <div>{fn_url('payment_notification.fail?payment=maib', 'C')}</div>
    </div>
</div>

<hr class="maib-key-settings3">
    <h3 class="maib-key-settings3">{__("maib.pem_settings")}</h3>
<hr class="maib-key-settings3">

<div class="control-group">
    <label class="control-label"><b>{__("maib.auto_pfx_extract")}:</b></label>
    <div class="controls">
        <input type="checkbox" onclick="togglePfx(this.checked)" value="1" name="maib_pfx_import" />
        <div>{__("maib.auto_pfx_text")}</div>
    </div>
    <hr>
    <div class="controls maib-pfx-settings" style="display: none;">
        <div class="text-warning warning">{__("maib.auto_pfx_warning")}</div>
        <label for="maib_pfx_file">{__("maib.auto_pfx_file")}:<br>
            <input type="file" name="maib_pfx_file" id="maib_pfx_file">
        </label>
        <label for="maib_pfx_pass">{__("maib.auto_pfx_pass")}:<br>
            <input type="text" name="maib_pfx_pass" id="maib_pfx_pass" value="" class="input-text" size="60" />
        </label>
    </div>
</div>

<div class="control-group maib-key-settings maib-key-settings2">
    <label class="control-label" for="maib_private_key">{__("maib.private_key")}:</label>
    <div class="controls">
        {$processor_params.private_key}<br>
        <input type="file" name="maib_private_key_file" />
        <input type="hidden" name="payment_data[processor_params][private_key]" value="{$processor_params.private_key}" /><br>
        <span>{__("maib.private_note")}</span>
    </div>
</div>

<div class="control-group maib-key-settings maib-key-settings2">
    <label class="control-label" for="maib_pkey_pass">{__("maib.pkey_pass")}:</label>
    <div class="controls">
        <input type="password" name="payment_data[processor_params][pkey_pass]" id="maib_pkey_pass" value="{$processor_params.pkey_pass}" class="input-text" size="60" autocomplete="new-password" />
    </div>
</div>

<div class="control-group maib-key-settings maib-key-settings2">
    <label class="control-label" for="maib_public_key">{__("maib.public_key")}:</label>
    <div class="controls">
        {$processor_params.public_key}<br>
        <input type="file" name="maib_public_key_file" />
        <input type="hidden" name="payment_data[processor_params][public_key]" value="{$processor_params.public_key}" />
    </div>
</div>

<div class="control-group maib-key-settings2">
    <label class="control-label" for="maib_private_key">{__("maib.delete_keys")}:</label>
    <div class="controls">
        <input type="checkbox" name="maib_delete_keys" value="1" onclick="if (this.checked) jQuery('.maib-key-settings').hide(); else jQuery('.maib-key-settings').show();" /><br>
        <span class="text-warning warning">{__("maib.delete_warning")}</span>
    </div>
</div>

<script type="text/javascript">
function togglePfx(showPfx) {
    if (showPfx) {
        jQuery('.maib-key-settings2').hide();
        jQuery('.maib-pfx-settings').show();
    }
    else {
        jQuery('.maib-key-settings2').show();
        jQuery('.maib-pfx-settings').hide();
        var delKeys = jQuery("input[name=maib_delete_keys]");
        if (delKeys.length && delKeys[0].checked) jQuery('.maib-key-settings').hide();
    } 
}
</script>

{/if}
