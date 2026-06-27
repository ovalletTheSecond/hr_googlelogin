<?php
/**
 * Module: hr_googlelogin
 * Connexion "Se connecter avec Google" via Google Identity Services (OAuth2 / JWT).
 * Le token JWT est vérifié côté serveur via l'API Google tokeninfo (HTTPS).
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Hr_googlelogin extends Module
{
    /** Hooks disponibles dans le sélecteur du back-office */
    const AVAILABLE_HOOKS = [
        'displayCustomerLoginFormAfter' => 'Formulaire de connexion (après)',
        'displayCustomerLoginFormBefore' => 'Formulaire de connexion (avant)',
        'displayNav1'      => 'Nav 1',
        'displayNav2'      => 'Nav 2',
        'displayTop'       => 'En-tête (Top)',
        'displayBanner'    => 'Bannière',
        'displayHeader'    => 'Header (balise <head>)',
        'actionDispatcher' => 'Dispatcher (Symfony response)',
    ];

    public function __construct()
    {
        $this->name          = 'hr_googlelogin';
        $this->tab           = 'front_office_features';
        $this->version       = '1.0.0';
        $this->author        = 'HampterShop';
        $this->need_instance = 0;
        $this->bootstrap     = true;

        parent::__construct();
        $this->repairHookRegistration();
        // adds log
        PrestaShopLogger::addLog('hr_googlelogin: Module loaded.', 1);

        $this->displayName = $this->l('Google Login — HampterShop');
        $this->description = $this->l('Ajoute un bouton "Se connecter avec Google" via Google Identity Services (OAuth 2.0 / JWT).');
        $this->ps_versions_compliancy = ['min' => '1.7.0', 'max' => _PS_VERSION_];
    }

    protected function repairHookRegistration()
    {
        if (!$this->id) {
            return;
        }

        $oldHookId = Hook::getIdByName('actionFrontControllerInit');
        if ($oldHookId) {
            $oldRegistration = Db::getInstance()->getValue('SELECT id_module FROM '._DB_PREFIX_.'hook_module WHERE id_module='.(int) $this->id.' AND id_hook='.(int) $oldHookId);
            if ($oldRegistration) {
                $this->unregisterHook('actionFrontControllerInit');
            }
        }

        $this->registerHook('actionFrontControllerInitBefore');
        $this->registerHook('actionFrontControllerInitAfter');
        $this->registerHook('actionDispatcher');
    }

    /* ─────────────────────────────────────────────────────────────── */
    /*  Install / Uninstall                                             */
    /* ─────────────────────────────────────────────────────────────── */

    public function install()
    {
        return parent::install()
            && $this->registerHook('actionDispatcher')
            && $this->registerHook('actionFrontControllerInitBefore')
            && $this->registerHook('actionFrontControllerInitAfter')
            && $this->registerConfiguredHook();
    }

    public function uninstall()
    {
        // Unregister all possible hooks
        foreach (array_keys(self::AVAILABLE_HOOKS) as $hook) {
            $this->unregisterHook($hook);
        }
        Configuration::deleteByName('HR_GOOGLELOGIN_CLIENT_ID');
        Configuration::deleteByName('HR_GOOGLELOGIN_HOOK');
        return parent::uninstall();
    }

    /* ─────────────────────────────────────────────────────────────── */
    /*  Back-office configuration page                                  */
    /* ─────────────────────────────────────────────────────────────── */

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitHrGoogleLogin')) {
            $clientId = trim(Tools::getValue('HR_GOOGLELOGIN_CLIENT_ID'));
            $hook     = Tools::getValue('HR_GOOGLELOGIN_HOOK');

            // Validate client ID format
            if (empty($clientId) || !preg_match('/^[a-zA-Z0-9\-]+\.apps\.googleusercontent\.com$/', $clientId)) {
                $output .= $this->displayError($this->l('L\'ID client Google semble invalide (doit se terminer par .apps.googleusercontent.com).'));
            } elseif (!array_key_exists($hook, self::AVAILABLE_HOOKS)) {
                $output .= $this->displayError($this->l('Hook invalide.'));
            } else {
                // Unregister previous hook
                $previousHook = Configuration::get('HR_GOOGLELOGIN_HOOK');
                if ($previousHook && $previousHook !== $hook) {
                    $this->unregisterHook($previousHook);
                }

                Configuration::updateValue('HR_GOOGLELOGIN_CLIENT_ID', $clientId);
                Configuration::updateValue('HR_GOOGLELOGIN_HOOK', $hook);

                // Register new hook
                $this->registerHook($hook);

                $output .= $this->displayConfirmation($this->l('Paramètres sauvegardés. Le bouton Google est actif.'));
            }
        }

        return $output . $this->renderConfigForm();
    }

    protected function renderConfigForm()
    {
        $this->context->controller->addJS(
            'https://accounts.google.com/gsi/client',
            false
        );

        $smarty = $this->context->smarty;
        $smarty->assign([
            'module_dir'   => $this->_path,
            'client_id'    => Configuration::get('HR_GOOGLELOGIN_CLIENT_ID'),
            'current_hook' => Configuration::get('HR_GOOGLELOGIN_HOOK'),
            'hooks'        => self::AVAILABLE_HOOKS,
            'callback_url' => $this->context->link->getModuleLink($this->name, 'callback'),
            'shop_url'     => (string) $this->context->shop->getBaseURL(true),
        ]);

        return $this->display(__FILE__, 'views/templates/admin/configure.tpl');
    }

    /* ─────────────────────────────────────────────────────────────── */
    /*  Hook registration helpers                                       */
    /* ─────────────────────────────────────────────────────────────── */

    protected function registerConfiguredHook()
    {
        $hook = Configuration::get('HR_GOOGLELOGIN_HOOK');
        if ($hook && array_key_exists($hook, self::AVAILABLE_HOOKS)) {
            return $this->registerHook($hook);
        }
        // Default
        return $this->registerHook('displayCustomerLoginFormAfter');
    }

    /* ─────────────────────────────────────────────────────────────── */
    /*  Hook handlers — the module dispatches to one active hook        */
    /* ─────────────────────────────────────────────────────────────── */

    protected function renderButton()
    {

    // var_dump('AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA');die();
        $clientId = Configuration::get('HR_GOOGLELOGIN_CLIENT_ID');
        if (empty($clientId)) {
            return '';
        }

        $this->context->smarty->assign([
            'hr_google_client_id' => $clientId,
            'hr_google_callback'  => $this->context->link->getModuleLink($this->name, 'callback'),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/google_login_button.tpl');
    }

    public function hookDisplayCustomerLoginFormAfter($params)  { return $this->renderButton(); }
    public function hookDisplayCustomerLoginFormBefore($params) { return $this->renderButton(); }
    public function hookDisplayNav1($params)                    { return $this->renderButton(); }
    public function hookDisplayNav2($params)                    { return $this->renderButton(); }
    public function hookDisplayTop($params)                     { return $this->renderButton(); }
    public function hookDisplayBanner($params)                  { return $this->renderButton(); }
    public function hookActionFrontControllerInit($params)
    {
        $this->setCoopHeader();
    }

    public function hookActionFrontControllerInitBefore($params)
    {
        $this->setCoopHeader();
    }

    public function hookActionFrontControllerInitAfter($params)
    {
        $this->setCoopHeader();
    }

    public function hookActionDispatcher($params)
    {
        if (isset($params['controller_type']) && $params['controller_type'] === Dispatcher::FC_FRONT) {
            if ($this->attachSymfonyResponseListener()) {
                return;
            }
        }

        $this->setCoopHeader();
    }

    protected function attachSymfonyResponseListener()
    {
        global $kernel;

        if (!$kernel || !method_exists($kernel, 'getContainer')) {
            return false;
        }

        PrestaShopLogger::addLog('hr_googlelogin: Attaching Symfony response listener for Cross-Origin-Opener-Policy header.', 1);

        try {
            $container = $kernel->getContainer();
            if (!$container->has('event_dispatcher')) {
                PrestaShopLogger::addLog('hr_googlelogin: Symfony container does not have event_dispatcher service.', 3);
                return false;
            }

            $eventDispatcher = $container->get('event_dispatcher');
            if (!method_exists($eventDispatcher, 'addListener')) {
                PrestaShopLogger::addLog('hr_googlelogin: Symfony event_dispatcher does not support addListener method.', 3);
                return false;
            }

            $eventDispatcher->addListener('kernel.response', function ($event) {
                if (!method_exists($event, 'getResponse')) {
                    PrestaShopLogger::addLog('hr_googlelogin: Event does not have getResponse method.', 3);
                    return;
                }

                $response = $event->getResponse();
                if ($response && isset($response->headers) && method_exists($response->headers, 'set')) {
                    PrestaShopLogger::addLog('hr_googlelogin: Setting Cross-Origin-Opener-Policy header.', 1);
                    $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
                }
            });

            return true;
        } catch (\Exception $e) {
            PrestaShopLogger::addLog('hr_googlelogin: Symfony response listener attach failed: ' . $e->getMessage(), 3);
            return false;
        }
    }

    public function hookDisplayHeader($params)
    {
        $this->setCoopHeader();
        return '';
    }

    protected function setCoopHeader()
    {
        if (!headers_sent()) {
            header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
        } else {
            PrestaShopLogger::addLog('hr_googlelogin: Cannot set Cross-Origin-Opener-Policy header because headers already sent.', 3);
        }
    }
}
