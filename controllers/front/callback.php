<?php
/**
 * Controller front : hr_googlelogin — callback
 *
 * Reçoit le JWT émis par Google (response.credential), le valide
 * de façon sécurisée côté serveur, puis connecte ou crée le client PS.
 *
 * Sécurité :
 *   - Vérification du jeton via l'endpoint HTTPS Google tokeninfo (pas côté client).
 *   - Vérification de `aud` (audience) = notre Client ID pour éviter les jetons d'autres apps.
 *   - Vérification de `exp` (expiration).
 *   - Vérification que `email_verified` est true.
 *   - Protection CSRF : uniquement méthode POST acceptée.
 *   - Aucune donnée sensible ne transite côté client.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Hr_googleloginCallbackModuleFrontController extends ModuleFrontController
{
    public $ajax = true;

    public function init()
    {
        try {
            // Envoi des en-têtes CORS pour autoriser Google / application externe à appeler ce callback.
            // Remplacez l'origine par le domaine de votre application externe si nécessaire.
            $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

            $shopOrigin = $this->context->shop->getBaseURL(true);
            $allowedOrigins = [
                'https://accounts.google.com',
                $shopOrigin,
            ];

            if (in_array($origin, $allowedOrigins, true)) {
                header('Access-Control-Allow-Origin: ' . $origin);
                header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type, Authorization');
                header('Access-Control-Allow-Credentials: true');
            }

            if (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'OPTIONS') {
                exit;
            }

            parent::init();
        } catch (\Throwable $e) {
            header('Content-Type: text/plain; charset=utf-8');
            var_dump('hr_googlelogin callback init exception', $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
            die();
        }
    }

    public function initContent()
    {
        try {
            parent::initContent();
            $this->sendJsonResponse($this->processGoogleToken());
        } catch (\Throwable $e) {
            header('Content-Type: text/plain; charset=utf-8');
            var_dump('hr_googlelogin callback initContent exception', $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
            die();
        }
    }

    /* ─────────────────────────────────────────────────────────────── */
    /*  Main processing                                                 */
    /* ─────────────────────────────────────────────────────────────── */

    protected function processGoogleToken(): array
    {
        // Only accept POST requests
        if (!$this->isPost()) {
            return $this->error('Méthode non autorisée.', 405);
        }

        $rawToken = $this->getPostedToken();
        if (empty($rawToken)) {
            return $this->error('Jeton manquant.');
        }

        // ── 1. Validate JWT via Google tokeninfo (server-side HTTPS) ──
        $payload = $this->verifyGoogleToken($rawToken);
        if ($payload === null) {
            return $this->error('Jeton Google invalide ou expiré.');
        }

        // ── 2. Verify audience matches our Client ID ──
        $configuredClientId = Configuration::get('HR_GOOGLELOGIN_CLIENT_ID');
        if (empty($configuredClientId) || $payload['aud'] !== $configuredClientId) {
            return $this->error('Audience du jeton invalide.');
        }

        // ── 3. Require a verified email ──
        if (empty($payload['email']) || ($payload['email_verified'] ?? 'false') !== 'true') {
            return $this->error('L\'adresse e-mail Google n\'est pas vérifiée.');
        }

        $email     = strtolower(trim($payload['email']));
        $firstName = $this->sanitizeName($payload['given_name']  ?? 'Client');
        $lastName  = $this->sanitizeName($payload['family_name'] ?? 'Google');
        $googleId  = $payload['sub'] ?? null; // unique persistent Google user ID

        if (!Validate::isEmail($email)) {
            return $this->error('Adresse e-mail invalide.');
        }

        // ── 4. Find or create PS customer ──
        $customer = $this->findOrCreateCustomer($email, $firstName, $lastName, $googleId);
        if ($customer === null) {
            return $this->error('Impossible de créer ou trouver le compte client.');
        }

        // ── 5. Log the customer in ──
        $this->loginCustomer($customer);

        return ['success' => true, 'redirect' => $this->context->link->getPageLink('my-account')];
    }

    /* ─────────────────────────────────────────────────────────────── */
    /*  Token verification (server-side, no client library needed)     */
    /* ─────────────────────────────────────────────────────────────── */

    /**
     * Verifies the Google JWT by calling Google's tokeninfo endpoint over HTTPS.
     * Returns the decoded payload array on success, or null on failure.
     */
    protected function verifyGoogleToken(string $token): ?array
    {
        // Sanitize: token should only contain base64url chars and dots
        if (!preg_match('/^[a-zA-Z0-9\-_\.]+$/', $token)) {
            return null;
        }

        $url      = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($token);
        $response = $this->httpsGet($url);

        if ($response === null) {
            return null;
        }

        $payload = json_decode($response, true);
        if (!is_array($payload) || isset($payload['error'])) {
            return null;
        }

        // Verify token is not expired
        if (empty($payload['exp']) || (int)$payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    /**
     * Performs a secure HTTPS GET request using cURL with strict SSL verification.
     */
    protected function httpsGet(string $url): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,   // always verify Google's SSL cert
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,  // never follow redirects
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS, // HTTPS only
            CURLOPT_USERAGENT      => 'HampterShop-GoogleLogin/' . $this->module->version,
        ]);

        $body  = curl_exec($ch);
        $error = curl_errno($ch);
        curl_close($ch);

        if ($error !== 0 || $body === false) {
            return null;
        }

        return (string) $body;
    }

    /* ─────────────────────────────────────────────────────────────── */
    /*  Customer management                                             */
    /* ─────────────────────────────────────────────────────────────── */

    protected function findOrCreateCustomer(
        string $email,
        string $firstName,
        string $lastName,
        ?string $googleId
    ): ?Customer {
        $customer = new Customer();
        $customer->getByEmail($email);

        if ($customer->id) {
            // Existing customer — just return it
            return $customer;
        }

        // Create a new customer
        $customer = new Customer();
        $customer->firstname  = $firstName;
        $customer->lastname   = $lastName;
        $customer->email      = $email;
        $customer->passwd     = Tools::encrypt($this->generateSecurePassword());
        $customer->is_guest   = 0;
        $customer->active     = 1;
        $customer->id_default_group = (int) Configuration::get('PS_CUSTOMER_GROUP');
        $customer->id_shop    = (int) $this->context->shop->id;
        $customer->id_lang    = (int) $this->context->language->id;

        try {
            if (!$customer->validateFields(false) || !$customer->add()) {
                return null;
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'hr_googlelogin: customer creation failed — ' . $e->getMessage(),
                3,
                null,
                'Customer',
                0,
                true
            );
            return null;
        }

        return $customer;
    }

    protected function loginCustomer(Customer $customer): void
    {
        $this->context->updateCustomer($customer);

        // Rebuild cart association
        if ($this->context->cart instanceof Cart) {
            $this->context->cart->id_customer = (int) $customer->id;
            $this->context->cart->secure_key  = $customer->secure_key;
            $this->context->cart->save();
        }

        // Write cookie
        $this->context->cookie->id_customer        = (int) $customer->id;
        $this->context->cookie->customer_lastname  = $customer->lastname;
        $this->context->cookie->customer_firstname = $customer->firstname;
        $this->context->cookie->logged             = 1;
        $this->context->cookie->email              = $customer->email;
        $this->context->cookie->check_cgv          = 0;
        $this->context->cookie->write();

        Hook::exec('actionAuthentication', ['customer' => $customer]);
    }

    /* ─────────────────────────────────────────────────────────────── */
    /*  Helpers                                                         */
    /* ─────────────────────────────────────────────────────────────── */

    protected function isPost(): bool
    {
        return isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'POST';
    }

    protected function getPostedToken(): string
    {
        $token = isset($_POST['token']) ? $_POST['token'] : '';
        return trim((string) $token);
    }

    protected function sanitizeName(string $name): string
    {
        $name = trim(strip_tags($name));
        $name = preg_replace('/[^a-zA-ZÀ-ÿ \'\-]/u', '', $name);
        return mb_substr($name, 0, 32);
    }

    /** Generate a cryptographically-random password (never returned to the client). */
    protected function generateSecurePassword(): string
    {
        return bin2hex(random_bytes(24));
    }

    protected function error(string $message, int $code = 400): array
    {
        return ['success' => false, 'error' => $message, 'code' => $code];
    }

    protected function sendJsonResponse(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $statusCode = $data['code'] ?? ($data['success'] ? 200 : 400);
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }
}
