{dependency type="component" name="utils.exception"}
<div class="exception exception_{$exception.type}">
 {img src="components/elation/stop.png" class="exception_icon"}
 <h2>
  {$exception.type}: {$exception.message}
  {if $debug}<address>{$exception.file}:{$exception.line}</address>{/if}
 </h2>
{if $debug && !empty($exception.trace)}
 <ol class="exception_trace">
  {foreach from=$exception.trace item=trace}
   <li>
    {$trace.class}{$trace.type}{$trace.function}(
     {foreach from=$trace.args item=arg name=trace}
       <code>{if is_string($arg)}'{$arg|escape:html}'{else}{php}print gettype($this->_tpl_vars["arg"]){/php}{/if}</code>{if !$smarty.foreach.trace.last},{/if}
     {/foreach}
    )
   </li>
  {/foreach}
 </ol>
{/if}
</div>
