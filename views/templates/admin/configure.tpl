{*
 * HelloWorld Module for PrestaShop
 *
 * @author    Frits van Leeuwen
 * @copyright 2025 Geschenkenlaantje
 * @license   https://opensource.org/licenses/MIT MIT License
 * @version   1.0.98
 *}
<div class="panel">
    <h2>{l s='Configuration page for HelloWorld' d='Modules.Helloworld.Admin'}</h2>
    <form method="POST">
        <label for="module_lang">{l s='Select a language:' d='Modules.Helloworld.Admin'}</label>
        <select name="module_lang" id="module_lang" onchange="this.form.submit()">
            {$languageOptions nofilter}
        </select>
    </form>
    <hr />
<p><strong>{l s='Module name:' d='Modules.Helloworld.Admin'}</strong> {$translatedName|escape:'html':'UTF-8'}</p>
<p><strong>{l s='Description:' d='Modules.Helloworld.Admin'}</strong> {$translatedDescription|escape:'html':'UTF-8'}</p>
</div>
