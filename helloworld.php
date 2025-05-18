<?php
/**
 * HelloWorld Module for PrestaShop
 *
 * @author    Frits van Leeuwen
 * @copyright 2025 Geschenkenlaantje
 * @license   https://opensource.org/licenses/MIT MIT License
 * @version   1.0.98
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class HelloWorld extends Module
{
    // Definieer een constante voor het vertaal-domein (met hoofdletter 'H')
    const TRANSLATION_DOMAIN = 'Modules.Helloworld';

    public function __construct()
    {
        // Basisinformatie over de module instellen
        $this->name = 'helloworld'; // let op: gebruik alleen kleine letters voor de mapnaam!
        $this->tab = 'administration';
        $this->version = '1.0.98';
        $this->author = 'Frits van Leeuwen';
        $this->bootstrap = true;

        // Compatibiliteit met PrestaShop-versies instellen
        $this->ps_versions_compliancy = [
            'min' => '1.7.0.0',
            'max' => _PS_VERSION_,
        ];
        parent::__construct();
        // Haal de vertalingen op in een aparte functie
        $this->loadTranslations();
    }

    private function loadTranslations()
    {
        $context = Context::getContext();
        $translator = $context->getTranslator();
        $domain = self::TRANSLATION_DOMAIN;

        // Bepaal de actieve taal (id)
        $currentLanguageId = (int) $context->language->id;

        // Indien je meerdere thema’s hebt, kun je ook het huidige thema filteren.
        $themeColumnResult = Db::getInstance()->executeS(
            'SHOW COLUMNS FROM ' . _DB_PREFIX_ . 'translation LIKE \'theme\''
        );
        $themeColumnExists = !empty($themeColumnResult);
        $currentTheme = (Configuration::get('PS_THEME')) ? Configuration::get('PS_THEME') : 'classic';
        $whereTheme = $themeColumnExists ? 'AND theme = \'' . pSQL($currentTheme) . '\'' : '';

        // Haal de vertaling voor 'Hello World' op, ook filteren op id_lang
        $forcedTranslation = Db::getInstance()->getValue(
            'SELECT translation FROM ' . _DB_PREFIX_ . 'translation
            WHERE domain = \'' . pSQL($domain) . '\'
            AND `key` = \'Hello World\'
            AND id_lang = ' . $currentLanguageId . ' ' . $whereTheme . ' LIMIT 1'
        );

        if (!empty($forcedTranslation)) {
            $this->displayName = $forcedTranslation;
        } else {
            $this->displayName = $translator->trans('Hello World', [], $domain);
        }

        // Zelfde filtering toepassen voor de beschrijving
        $forcedDescription = Db::getInstance()->getValue(
            'SELECT translation FROM ' . _DB_PREFIX_ . 'translation
            WHERE domain = \'' . pSQL($domain) . '\'
            AND `key` = \'A simple module to test translations.\'
            AND id_lang = ' . $currentLanguageId . ' ' . $whereTheme . ' LIMIT 1'
        );

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
        Db::getInstance()->execute(
            'DELETE FROM ' . _DB_PREFIX_ . 'translation WHERE domain = \'' . pSQL(self::TRANSLATION_DOMAIN) . '\''
        );

        // Haal alle actieve talen op
        $activeLanguages = Db::getInstance()->executeS(
            'SELECT id_lang, iso_code FROM ' . _DB_PREFIX_ . 'lang WHERE active = 1'
        );

        // Definieer het pad naar de map met vertaalbestanden (bijv. /modules/helloworld/translations/)
        $translationsFolder = __DIR__ . '/translations/';

        // Controleer of het 'theme' veld bestaat in de translation-tabel
        $themeColumnResult = Db::getInstance()->executeS(
            'SHOW COLUMNS FROM ' . _DB_PREFIX_ . 'translation LIKE \'theme\''
        );
        $themeColumnExists = !empty($themeColumnResult);

        // Probeer het actuele thema op te halen, bijvoorbeeld via de configuratie. Fallback naar 'classic'.
        $currentTheme = (Configuration::get('PS_THEME')) ? Configuration::get('PS_THEME') : 'classic';

        // Voor iedere actieve taal:
        if ($activeLanguages) {
            foreach ($activeLanguages as $lang) {
                $id_lang = (int) $lang['id_lang'];
                $iso_code = $lang['iso_code'];
                $translationFile = $translationsFolder . $iso_code . '.php';

                if (file_exists($translationFile)) {
                    $translations = include $translationFile;
                    if (is_array($translations)) {
                        foreach ($translations as $key => $translatedText) {
                            if ($themeColumnExists) {
                                $sql = 'INSERT INTO ' . _DB_PREFIX_ . 'translation 
                                    (id_lang, `key`, translation, domain, theme)
                                    VALUES (
                                        ' . (int)$id_lang . ',
                                        \'' . pSQL($key) . '\',
                                        \'' . pSQL($translatedText) . '\',
                                        \'' . pSQL(self::TRANSLATION_DOMAIN) . '\',
                                        \'' . pSQL($currentTheme) . '\'
                                    )';
                            } else {
                                $sql = 'INSERT INTO ' . _DB_PREFIX_ . 'translation 
                                    (id_lang, `key`, translation, domain)
                                    VALUES (
                                        ' . (int)$id_lang . ',
                                        \'' . pSQL($key) . '\',
                                        \'' . pSQL($translatedText) . '\',
                                        \'' . pSQL(self::TRANSLATION_DOMAIN) . '\'
                                    )';
                            }
                            Db::getInstance()->execute($sql);
                        }
                    }
                } else {
                    error_log('Translation file for ISO-code \'' . $iso_code . '\' not found at path: ' . $translationFile);
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
        Db::getInstance()->execute(
            'DELETE FROM ' . _DB_PREFIX_ . 'translation 
            WHERE domain = \'' . pSQL(self::TRANSLATION_DOMAIN) . '\''
        );

        return true;
    }

    public function isUsingNewTranslationSystem()
    {
        // Geef aan dat deze module gebruikmaakt van het nieuwe vertaal-systeem
        return true;
    }

    public function hookDisplayBackOfficeHeader()
    {
        // Hier kun je CSS- of JavaScript-bestanden laden voor de backoffice
        $this->context->controller->addCSS($this->_path . 'views/css/admin.css');
        $this->context->controller->addJS($this->_path . 'views/js/admin.js');
    }

    // Configuratiepagina voor live testen van de vertalingen
    public function getContent()
    {
        $context = Context::getContext();
        $translator = $context->getTranslator();
        $domain = self::TRANSLATION_DOMAIN;

        // Haal alle beschikbare talen uit de database
        $availableLanguages = Db::getInstance()->executeS(
            'SELECT iso_code, name FROM ' . _DB_PREFIX_ . 'lang WHERE active = 1'
        );

        // Haal de huidige taalkeuze op; standaard is de actieve taal
        $selectedLang = Tools::getValue('module_lang', $context->language->iso_code);

        // Haal de taal-ID op basis van de ISO-code
        $langId = Db::getInstance()->getValue(
            'SELECT id_lang FROM ' . _DB_PREFIX_ . 'lang WHERE iso_code = \'' . pSQL($selectedLang) . '\''
        );

        if ($langId) {
            // Forceer de taal in de context en sla deze op in de cookie
            $context->language = new Language($langId);
            $context->cookie->id_lang = $langId;
            $context->cookie->write();

            // Sla de gekozen taal permanent op voor de gebruiker als de keuze is verzonden
            if (Tools::isSubmit('module_lang')) {
                Db::getInstance()->execute(
                    'UPDATE ' . _DB_PREFIX_ . 'employee 
                    SET id_lang = ' . (int) $langId . ' 
                    WHERE id_employee = ' . (int) $context->employee->id
                );

                Tools::clearCache();
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

        // **Smarty-variabelen toewijzen**
        $this->context->smarty->assign([
            'languageOptions' => $languageOptions,
            'translatedName' => $translatedName,
            'translatedDescription' => $translatedDescription,
        ]);

        // **Laad de Smarty-template**
        return $this->display(__FILE__, 'views/templates/admin/configure.tpl');
    }
}
