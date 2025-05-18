<?php
/**
 * HelloWorld Module for PrestaShop
 *
 * @author    Frits van Leeuwen
 * @copyright 2025 Geschenkenlaantje
 * @license   https://opensource.org/licenses/MIT MIT License
 * @version   1.0.94
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminHelloWorldController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->bootstrap = true;
    }

    public function initContent()
    {
        parent::initContent();

        // Zorg ervoor dat vertalingen correct worden opgehaald met de PrestaShop-translator
        $helloText = $this->trans('Hello World', [], 'Modules.Helloworld.Admin', $this->context->language->id);

        // Variabelen naar de Smarty-template sturen
        $this->context->smarty->assign([
            'helloText' => $helloText,
        ]);

        // Template instellen (zorg ervoor dat het zich bevindt in: views/templates/admin/)
        $this->setTemplate('modules/helloworld/views/templates/admin/helloworld.tpl');
    }
}
