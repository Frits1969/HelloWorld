<?php
// Controleer of Prestashop geladen is, anders direct stoppen
if (!defined('_PS_VERSION_')) {
    exit;
}

// Gebruik de Prestashop vertaalinterface via Symfony
use PrestaShop\PrestaShop\Core\Translation\TranslatorInterface;

class HelloWorld extends Module
{
    public function __construct()
    {
        // Basisinformatie over de module instellen
        $this->name = 'helloworld'; // let op: gebruik alleen kleine letters
        $this->tab = 'administration';
        $this->version = '1.0.75';
        $this->author = 'Frits van Leeuwen';
        $this->bootstrap = true;

        parent::__construct();
        // Haal de vertalingen op in een aparte functie
        $this->loadTranslations();
    }

    private function loadTranslations()
    {
        $translator = Context::getContext()->getTranslator();
        $domain = 'Modules.Helloworld';

        // Direct de vertaling ophalen uit de database (handmatige test)
        $forcedTranslation = Db::getInstance()->getValue("
            SELECT translation FROM " . _DB_PREFIX_ . "translation
            WHERE domain = '" . pSQL($domain) . "' AND `key` = 'Hello World'
        ");

        // Controleer of de database een vertaling teruggeeft, anders fallback naar trans()
        if (!empty($forcedTranslation)) {
            $this->displayName = $forcedTranslation;
        } else {
            $this->displayName = $translator->trans('Hello World', [], $domain);
        }

        // Zelfde methode voor beschrijving
        $forcedDescription = Db::getInstance()->getValue("
            SELECT translation FROM " . _DB_PREFIX_ . "translation
            WHERE domain = '" . pSQL($domain) . "' AND `key` = 'A simple module to test translations.'
        ");

        if (!empty($forcedDescription)) {
            $this->description = $forcedDescription;
        } else {
            $this->description = $translator->trans('A simple module to test translations.', [], $domain);
        }
    }

    public function install()
    {
        if (parent::install()) {
            $this->registerHook('displayBackOfficeHeader');

            // Forceer vertaling opnieuw in database
            Db::getInstance()->execute("
                INSERT INTO " . _DB_PREFIX_ . "translation (id_lang, `key`, translation, domain, theme)
                VALUES (1, 'Hello World', 'Hallo Wereld', 'Modules.Helloworld', 'classic')
                ON DUPLICATE KEY UPDATE translation = 'Hallo Wereld'
            ");

            // Dwing PrestaShop om vertalingen opnieuw te registreren
            Tools::clearCache();
            return true;
        }
        return false;
    }

    public function uninstall()
    {
        if (parent::uninstall()) {
            // Reset de module-naam naar de standaard Engelse tekst
            $sql = "UPDATE " . _DB_PREFIX_ . "module 
                    SET name = 'Hello World' 
                    WHERE id_module = " . (int)$this->id;
            Db::getInstance()->execute($sql);
            return true;
        }
        return false;
    }

    public function isUsingNewTranslationSystem()
    {
        // Geef aan dat deze module gebruikmaakt van het nieuwe vertaal-systeem
        return true;
    }

    // Configuratiepagina voor live testen van de vertalingen
    public function getContent()
    {
        $context = Context::getContext();
        $translator = $context->getTranslator();
        $domain = 'Modules.Helloworld';

        // Haal alle beschikbare talen uit de database
        $availableLanguages = Db::getInstance()->executeS("
            SELECT iso_code, name FROM " . _DB_PREFIX_ . "lang WHERE active = 1
        ");

        // Haal de huidige taalkeuze op, standaard is de actieve taal
        $selectedLang = Tools::getValue('module_lang', $context->language->iso_code);

        // Haal de volledige taal-objecten op basis van de ISO-code
        $langId = Db::getInstance()->getValue("
            SELECT id_lang FROM " . _DB_PREFIX_ . "lang WHERE iso_code = '" . pSQL($selectedLang) . "'
        ");

        if ($langId) {
            // Forceer de taal aan PrestaShop
            $context->language = new Language($langId);
            $context->cookie->id_lang = $langId;
            $context->cookie->write(); // Sla de wijziging op in de sessie

            // Sla de gekozen taal permanent op voor de gebruiker
            if (Tools::isSubmit('module_lang')) {
                Db::getInstance()->execute("
                    UPDATE " . _DB_PREFIX_ . "employee 
                    SET id_lang = " . (int)$langId . "
                    WHERE id_employee = " . (int)$context->employee->id . "
                ");

                // Leeg de cache direct
                Tools::clearCache();
            }
        }

        // Haal vertalingen op basis van de gekozen taal
        $translatedName = $translator->trans('Hello World', [], $domain, $selectedLang);
        $translatedDescription = $translator->trans('A simple module to test translations.', [], $domain, $selectedLang);

        // Bouw de taalkeuzelijst dynamisch op uit de database
        $languageOptions = '';
        foreach ($availableLanguages as $lang) {
            $languageOptions .= '<option value="' . $lang['iso_code'] . '"' . 
                ($selectedLang === $lang['iso_code'] ? ' selected' : '') . '>' . 
                $lang['name'] . '</option>';
        }

        return '
            <div class="panel">
                <h2>Configuratiepagina voor HelloWorld</h2>
                <form method="POST">
                    <label for="module_lang">Kies een taal:</label>
                    <select name="module_lang" id="module_lang" onchange="this.form.submit()">
                        ' . $languageOptions . '
                    </select>
                </form>
                <hr />
                <p><strong>Geselecteerde taal:</strong> ' . strtoupper($selectedLang) . '</p>
                <p><strong>Module naam:</strong> ' . $translatedName . '</p>
                <p><strong>Beschrijving:</strong> ' . $translatedDescription . '</p>
            </div>
        ';
    }

}
