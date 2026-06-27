{**
 * hr_googlelogin — bouton "Se connecter avec Google"
 * Affiché via le hook choisi dans le back-office.
 *}

{* ── Chargement du script Google Identity Services ── *}
<script src="https://accounts.google.com/gsi/client" async defer></script>

{* ── Bouton Google Identity Services ── *}
<div id="g_id_onload"
     data-client_id="{$hr_google_client_id|escape:'html':'UTF-8'}"
     data-callback="hrGoogleHandleCredentialResponse"
     data-auto_prompt="false"
     data-itp_support="true">
</div>

<div class="hr-google-login-wrapper">
  <div class="hr-google-divider">
    <span>{l s='ou continuer avec' d='Modules.Hrgooglelogin.Shop'}</span>
  </div>

  <div class="g_id_signin"
       data-type="standard"
       data-shape="rectangular"
       data-theme="outline"
       data-text="signin_with"
       data-size="large"
       data-logo_alignment="left"
       data-width="320">
  </div>

  <p class="hr-google-note">
    {l s='En vous connectant, vous acceptez nos ' d='Modules.Hrgooglelogin.Shop'}
    <a href="{$link->getCMSLink(3)|escape:'html':'UTF-8'}" target="_blank">{l s='Conditions Générales' d='Modules.Hrgooglelogin.Shop'}</a>.
  </p>
</div>

<style>
  .hr-google-login-wrapper {
    margin: 1.5rem 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
  }
  .hr-google-divider {
    display: flex;
    align-items: center;
    gap: .75rem;
    width: 100%;
    max-width: 320px;
    color: #6b6b5e;
    font-size: .9rem;
  }
  .hr-google-divider::before,
  .hr-google-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: rgba(0,0,0,.15);
  }
  .hr-google-note {
    font-size: .78rem;
    color: #8a8a7a;
    text-align: center;
    max-width: 320px;
    margin: 0;
  }
  .hr-google-note a {
    color: #003f87;
    text-decoration: underline;
  }
  /* Loading state */
  .hr-google-loading {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .75rem 1.5rem;
    border: 2px solid #1c1c0f;
    border-radius: 999px;
    background: #fff;
    font-weight: 700;
    font-size: .9rem;
    color: #1c1c0f;
    opacity: .7;
    pointer-events: none;
  }
  /* Error/success messages */
  .hr-google-msg {
    max-width: 320px;
    width: 100%;
    padding: .75rem 1rem;
    border-radius: 1rem;
    font-size: .88rem;
    font-weight: 600;
    border: 2px solid;
    display: none;
  }
  .hr-google-msg.error {
    background: #fff1f0;
    border-color: #ba1a1a;
    color: #ba1a1a;
  }
  .hr-google-msg.success {
    background: #f0fff4;
    border-color: #1a7b46;
    color: #1a7b46;
  }
</style>

<div class="hr-google-msg" id="hr-google-feedback"></div>

<script>
(function () {
  'use strict';

  /**
   * Called by Google Identity Services when the user completes sign-in.
   * The credential (JWT) is sent to the server for secure verification.
   */
  window.hrGoogleHandleCredentialResponse = function (response) {
    var token = response && response.credential ? String(response.credential) : '';

    if (!token) {
      hrGoogleShowMessage('error', 'Erreur : jeton Google manquant.');
      return;
    }

    // Basic sanity check before sending (does not replace server validation)
    if (!/^[a-zA-Z0-9\-_\.]+$/.test(token)) {
      hrGoogleShowMessage('error', 'Jeton Google invalide.');
      return;
    }

    hrGoogleShowMessage('loading', 'Connexion en cours…');

    fetch({$hr_google_callback|escape:'quotes'|json_encode nofilter}, {
      method  : 'POST',
      headers : { 'Content-Type': 'application/x-www-form-urlencoded' },
      body    : 'token=' + encodeURIComponent(token),
      credentials: 'same-origin',
    })
    .then(function (res) {
      return res.json().then(function (data) {
        return { status: res.status, data: data };
      });
    })
    .then(function (result) {
      var data = result.data;
      if (data && data.success && data.redirect) {
        hrGoogleShowMessage('success', 'Connexion réussie ! Redirection…');
        window.location.href = data.redirect;
      } else {
        var msg = (data && data.error) ? data.error : 'Erreur de connexion. Veuillez réessayer.';
        hrGoogleShowMessage('error', msg);
      }
    })
    .catch(function () {
      hrGoogleShowMessage('error', 'Erreur réseau. Veuillez réessayer.');
    });
  };

  function hrGoogleShowMessage(type, text) {
    var el = document.getElementById('hr-google-feedback');
    if (!el) { return; }
    el.textContent = text;
    el.className   = 'hr-google-msg ' + (type === 'loading' ? '' : type);
    el.style.display = 'block';
  }
}());
</script>
