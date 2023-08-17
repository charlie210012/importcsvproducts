<div class="panel">
    <h3>{l s='Importar Productos desde CSV' mod='importcsvproducts'}</h3>
    <form action="{$_SERVER['REQUEST_URI']}" method="post" enctype="multipart/form-data">
        <label for="csv_file">{l s='Seleccionar archivo CSV:' mod='importcsvproducts'}</label>
        <input type="file" name="csv_file" id="csv_file" accept=".csv">
        <br>
        <button type="submit" name="import_csv" class="btn btn-default">{l s='Importar' mod='importcsvproducts'}</button>
    </form>
    {if isset($error)}
        <div class="alert alert-danger">
                <p>{$output}</p>
        </div>
    {/if}
    {if $confirmation}
        <div class="alert alert-success">
            <p>{$output}</p>
        </div>
    {/if}
</div>
