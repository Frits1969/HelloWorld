<?php
// Controleer of Prestashop geladen is, anders direct stoppen
if (!defined('_PS_VERSION_')) {
    exit;
}

// Gebruik de Prestashop vertaalinterface via Symfony
use PrestaShop\PrestaShop\Core\Translation\TranslatorInterface;

class HelloWorld extends Module
{
    // Definieer een constante voor het vertaal-domein (met hoofdletter 'H')
    const TRANSLATION_DOMAIN = 'Modules.Helloworld';
    
    public function __construct()
    {
        // Basisinformatie over de module instellen
        $this->name = 'helloworld'; // let op: gebruik alleen kleine letters voor de mapnaam!
        $this->tab = 'administration';
        $this->version = '1.0.87';
        $this->author = 'Frits van Leeuwen';
        $this->bootstrap = true;

        parent::__construct();
        // Haal de vertalingen op in een aparte functie
        $this->loadTranslations();
    }

    private function loadTranslations()
    {
        $translator = Context::getContext()->getTranslator();
        $domain = self::TRANSLATION_DOMAIN;

        // Haal de vertaling voor de module-naam op (key: 'Hello World')
        $forcedTranslation = Db::getInstance()->getValue("
            SELECT translation FROM " . _DB_PREFIX_ . "translation
            WHERE domain = '" . pSQL($domain) . "' AND `key` = 'Hello World'
            LIMIT 1
        ");

        if (!empty($forcedTranslation)) {
            $this->displayName = $forcedTranslation;
        } else {
            $this->displayName = $translator->trans('Hello World', [], $domain);
        }

        // Zelfde methode voor de beschrijving (key: 'A simple module to test translations.')
        $forcedDescription = Db::getInstance()->getValue("
            SELECT translation FROM " . _DB_PREFIX_ . "translation
            WHERE domain = '" . pSQL($domain) . "' AND `key` = 'A simple module to test translations.'
            LIMIT 1
        ");

        if (!empty($forcedDescription)) {
            $this->description = $forcedDescription;
        } else {
            $this->description = $translator->trans('A simple module to test translations.', [], $domain);
        }
    }

    public function install()
    {
        // Basis-installatie van de module uitvoeren
        if (!parent::install()) {
            return false;
        }
        
        // Registreer de hook voor de backoffice
        $this->registerHook('displayBackOfficeHeader');
        
        // Verwijder alle bestaande vertalingen voor het opgegeven domein
        Db::getInstance()->execute("
            DELETE FROM " . _DB_PREFIX_ . "translation 
            WHERE domain = '" . pSQL(self::TRANSLATION_DOMAIN) . "'
        ");
        
        // Haal alle actieve talen op (zodat ook Engels meekomt)
        $activeLanguages = Db::getInstance()->executeS("
            SELECT id_lang, iso_code 
            FROM " . _DB_PREFIX_ . "lang 
            WHERE active = 1
        ");
        
        // Definieer het pad naar de map met vertaalbestanden (bijv. /modules/helloworld/translations/)
        $translationsFolder = __DIR__ . '/translations/';
        
        // Controleer of het 'theme' veld bestaat in de translation-tabel
        $themeColumnResult = Db::getInstance()->executeS("SHOW COLUMNS FROM " . _DB_PREFIX_ . "translation LIKE 'theme'");
        $themeColumnExists = !empty($themeColumnResult);
        
        // Probeer het actuele thema op te halen, bijvoorbeeld via de configuratie. Fallback naar 'classic'.
        $currentTheme = (Configuration::get('PS_THEME')) ? Configuration::get('PS_THEME') : 'classic';
        
        // Voor iedere actieve taal:
        if ($activeLanguages) {
            foreach ($activeLanguages as $lang) {
                $id_lang  = (int)$lang['id_lang'];
                $iso_code = $lang['iso_code'];
                
                // Verwacht dat er per taal een bestand bestaat, bijvoorbeeld 'nl.php' of 'en.php'
                $translationFile = $translationsFolder . $iso_code . '.php';
                
                if (file_exists($translationFile)) {
                    // Het bestand moet een PHP-array retourneren met de vertalingen:
                    // return [
                    //     'Hello World' => 'Hallo Wereld',
                    //     'A simple module to test translations.' => 'Een eenvoudige module om vertalingen te testen.'
                    // ];
                    $translations = include $translationFile;
                    
                    if (is_array($translations)) {
                        foreach ($translations as $key => $translatedText) {
                            if ($themeColumnExists) {
                                // Als het 'theme' veld bestaat, gebruik dan het actieve thema
                                $sql = "
                                    INSERT INTO " . _DB_PREFIX_ . "translation (id_lang, `key`, translation, domain, theme)
                                    VALUES (
                                        " . $id_lang . ",
                                        '" . pSQL($key) . "',
                                        '" . pSQL($translatedText) . "',
                                        '" . pSQL(self::TRANSLATION_DOMAIN) . "',
                                        '" . pSQL($currentTheme) . "'
                                    )
                                    ON DUPLICATE KEY UPDATE translation = '" . pSQL($translatedText) . "'
                                ";
                            } else {
                                // Als het 'theme' veld niet bestaat, sla het veld over
                                $sql = "
                                    INSERT INTO " . _DB_PREFIX_ . "translation (id_lang, `key`, translation, domain)
                                    VALUES (
                                        " . $id_lang . ",
                                        '" . pSQL($key) . "',
                                        '" . pSQL($translatedText) . "',
                                        '" . pSQL(self::TRANSLATION_DOMAIN) . "'
                                    )
                                    ON DUPLICATE KEY UPDATE translation = '" . pSQL($translatedText) . "'
                                ";
                            }
                            Db::getInstance()->execute($sql);
                        }
                    }
                } else {
                    // Log een foutmelding als er voor de ISO-code geen vertaalbestand is gevonden
                    error_log("Translation file for ISO-code '{$iso_code}' not found at path: {$translationFile}");
                }
            }
        }
        
        // Maak de cache leeg zodat de nieuwe vertalingen direct actief worden.
        Tools::clearCache();
        
        return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }
        
        // Verwijder alle vertalingen die specifiek bij dit module-domein horen
        Db::getInstance()->execute("
            DELETE FROM " . _DB_PREFIX_ . "translation 
            WHERE domain = '" . pSQL(self::TRANSLATION_DOMAIN) . "'
        ");
        
        return true;
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
        $domain = self::TRANSLATION_DOMAIN;

        // Haal alle beschikbare talen uit de database
        $availableLanguages = Db::getInstance()->executeS("
            SELECT iso_code, name FROM " . _DB_PREFIX_ . "lang WHERE active = 1
        ");

        // Haal de huidige taalkeuze op; standaard is de actieve taal
        $selectedLang = Tools::getValue('module_lang', $context->language->iso_code);

        // Haal de taal-ID op basis van de ISO-code
        $langId = Db::getInstance()->getValue("
            SELECT id_lang FROM " . _DB_PREFIX_ . "lang WHERE iso_code = '" . pSQL($selectedLang) . "'
        ");

        if ($langId) {
            // Forceer de taal in de context en sla deze op in de cookie
            $context->language = new Language($langId);
            $context->cookie->id_lang = $langId;
            $context->cookie->write();

            // Sla de gekozen taal permanent op voor de gebruiker als de keuze is verzonden
            if (Tools::isSubmit('module_lang')) {
                Db::getInstance()->execute("
                    UPDATE " . _DB_PREFIX_ . "employee 
                    SET id_lang = " . (int)$langId . "
                    WHERE id_employee = " . (int)$context->employee->id . "
                ");
                Tools::clearCache();
                // Redirect om te zorgen dat de nieuwe taal volledig geladen wordt
                Tools::redirectAdmin($_SERVER['REQUEST_URI']);
            }
        }

        // Haal vertalingen op basis van de gekozen taal
        $translatedName = $translator->trans('Hello World', [], $domain);
        $translatedDescription = $translator->trans('A simple module to test translations.', [], $domain);

        // Bouw de taalkeuzelijst dynamisch op uit de database
        $languageOptions = '';
        foreach ($availableLanguages as $lang) {
            $languageOptions .= '<option value="' . $lang['iso_code'] . '"' .
                                ($selectedLang === $lang['iso_code'] ? ' selected' : '') .
                                '>' . $lang['name'] . '</option>';
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
