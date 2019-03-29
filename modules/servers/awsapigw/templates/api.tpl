<h2 class="text-center">API Information</h2>

<div class="alert alert-info">
    Notice: Sharing of your API key is strongly discouraged as it will potentially allow unauthorised access to your API account.
</div>

{if $api_key}
    <div class="row">
        <div class="col-sm-4">
            API Key
        </div>
        <div class="col-sm-8">
            {$api_key}
        </div>
    </div>
{/if}

{if $api_endpoint}
    <div class="row">
        <div class="col-sm-4">
            API Endpoint
        </div>
        <div class="col-sm-8">
            {$api_endpoint}
        </div>
    </div>
{/if}

{if $deploy_region}
    <div class="row">
        <div class="col-sm-4">
            Deployment Region
        </div>
        <div class="col-sm-8">
            {$deploy_region}
        </div>
    </div>
{/if}

<hr />

<div class="row">
    <div class="col-sm-3">
        <form method="post" action="clientarea.php?action=productdetails">
            <input type="hidden" name="id" value="{$serviceid}" />
            <input type="hidden" name="modop" value="custom" />
            <input type="hidden" name="a" value="ResetApiKey" />
            <button type="submit" class="btn btn-danger btn-block" onclick="return confirm('Are you sure to reset your API key?\n\nAll access to your old API key will be revoked immediately.');">
                <i class="fa fa-recycle"></i> Reset API Key
            </button>
        </form>
    </div>
</div>
