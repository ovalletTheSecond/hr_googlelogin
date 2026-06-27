{**
 * hr_googlelogin — page de configuration back-office
 *}

<div class="panel hr-glogin-config">
  <div class="panel-heading">
    <i class="icon-google-plus"></i>
    {l s='Google Login — Configuration' mod='hr_googlelogin'}
  </div>

  {* ── Instructions ── *}
  <div class="row">
    <div class="col-lg-12">
      <div class="alert alert-info">
        <h4>{l s='Créer vos identifiants chez Google' mod='hr_googlelogin'}</h4>
        <ol style="line-height:1.9">
          <li>
            {l s='Rendez-vous sur la' mod='hr_googlelogin'}
            <a href="https://console.cloud.google.com/" target="_blank" rel="noopener noreferrer">
              {l s='Google Cloud Console' mod='hr_googlelogin'}
            </a>.
          </li>
          <li>{l s='Créez un projet (ex : "Connexion Mon Site E-commerce").' mod='hr_googlelogin'}</li>
          <li>
            {l s='Allez dans' mod='hr_googlelogin'} <strong>{l s='API et services' mod='hr_googlelogin'}</strong>
            › <strong>{l s='Écran de consentement OAuth' mod='hr_googlelogin'}</strong>
            {l s='et configurez-le en mode "Externe" (nom du site + email).' mod='hr_googlelogin'}
          </li>
          <li>
            {l s='Allez dans' mod='hr_googlelogin'} <strong>{l s='Identifiants' mod='hr_googlelogin'}</strong>
            › <strong>{l s='Créer des identifiants' mod='hr_googlelogin'}</strong>
            › <strong>{l s='ID client OAuth' mod='hr_googlelogin'}</strong>.
          </li>
          <li>{l s='Sélectionnez "Application Web".' mod='hr_googlelogin'}</li>
          <li>
            <strong>{l s='Très important :' mod='hr_googlelogin'}</strong>
            {l s='Dans' mod='hr_googlelogin'} <em>{l s='Origines JavaScript autorisées' mod='hr_googlelogin'}</em>,
            {l s='mettez l\'URL de votre boutique :' mod='hr_googlelogin'}
            <code>{$shop_url|escape:'html':'UTF-8'}</code>.
            <br>
            {l s='Dans' mod='hr_googlelogin'} <em>{l s='URI de redirection autorisés' mod='hr_googlelogin'}</em>,
            {l s='mettez :' mod='hr_googlelogin'}
            <code>{$callback_url|escape:'html':'UTF-8'}</code>
          </li>
          <li>
            {l s='Validez. Google vous donne un' mod='hr_googlelogin'}
            <strong>{l s='ID client' mod='hr_googlelogin'}</strong>
            {l s='(chaîne se terminant par' mod='hr_googlelogin'} <code>.apps.googleusercontent.com</code>).
          </li>
        </ol>
      </div>
    </div>
  </div>

  {* ── Form ── *}
  <form action="{$smarty.server.REQUEST_URI|escape:'html':'UTF-8'}" method="POST">
    <div class="form-group">
      <label class="control-label col-lg-3 required" for="HR_GOOGLELOGIN_CLIENT_ID">
        {l s='ID client Google OAuth 2.0' mod='hr_googlelogin'}
      </label>
      <div class="col-lg-6">
        <input
          type        ="text"
          id          ="HR_GOOGLELOGIN_CLIENT_ID"
          name        ="HR_GOOGLELOGIN_CLIENT_ID"
          class       ="form-control"
          required
          pattern     ="^[a-zA-Z0-9\-]+\.apps\.googleusercontent\.com$"
          placeholder ="XXXXXXXXXX-xxxx.apps.googleusercontent.com"
          value       ="{$client_id|escape:'html':'UTF-8'}"
        >
        <p class="help-block">{l s='Votre Client ID se termine toujours par .apps.googleusercontent.com' mod='hr_googlelogin'}</p>
      </div>
    </div>

    <div class="form-group">
      <label class="control-label col-lg-3 required" for="HR_GOOGLELOGIN_HOOK">
        {l s='Hook d\'affichage du bouton' mod='hr_googlelogin'}
      </label>
      <div class="col-lg-6">
        <select id="HR_GOOGLELOGIN_HOOK" name="HR_GOOGLELOGIN_HOOK" class="form-control">
          {foreach from=$hooks key=hookName item=hookLabel}
            <option value="{$hookName|escape:'html':'UTF-8'}" {if $current_hook == $hookName}selected{/if}>
              {$hookLabel|escape:'html':'UTF-8'} ({$hookName|escape:'html':'UTF-8'})
            </option>
          {/foreach}
        </select>
        <p class="help-block">{l s='Choisissez à quel endroit de la page afficher le bouton "Se connecter avec Google".' mod='hr_googlelogin'}</p>
      </div>
    </div>

    <div class="form-group">
      <div class="col-lg-6 col-lg-offset-3">
        <button type="submit" name="submitHrGoogleLogin" value="1" class="btn btn-default pull-right">
          <i class="process-icon-save"></i>
          {l s='Sauvegarder' mod='hr_googlelogin'}
        </button>
      </div>
    </div>
  </form>

  {* ── Preview du bouton (si configuré) ── *}
  {if $client_id}
    <div class="panel-footer">
      <h4>{l s='Aperçu du bouton' mod='hr_googlelogin'}</h4>
      <p class="text-muted">{l s='Voici à quoi ressemble le bouton affiché pour vos clients.' mod='hr_googlelogin'}</p>
      <script src="https://accounts.google.com/gsi/client" async defer></script>
      <div id="g_id_onload"
           data-client_id="{$client_id|escape:'html':'UTF-8'}"
           data-callback="hrAdminPreview"
           data-auto_prompt="false">
      </div>
      <div class="g_id_signin"
           data-type="standard"
           data-shape="rectangular"
           data-theme="outline"
           data-text="signin_with"
           data-size="large"
           data-logo_alignment="left">
      </div>
      {literal}
      <script>window.hrAdminPreview = function(){alert('Aperçu uniquement. La vraie connexion fonctionne en front-office.');};</script>
      {/literal}
    </div>
  {/if}

  {* ── Security notes ── *}
  <div class="row" style="margin-top:2rem">
    <div class="col-lg-12">
      <div class="alert alert-success">
        <h4><i class="icon-lock"></i> {l s='Sécurité' mod='hr_googlelogin'}</h4>
        <ul style="line-height:1.9">
          <li>{l s='Le jeton JWT émis par Google est vérifié côté serveur via l\'API HTTPS de Google (jamais côté client).' mod='hr_googlelogin'}</li>
          <li>{l s='L\'audience (aud) du jeton est vérifiée pour s\'assurer qu\'il correspond bien à votre Client ID.' mod='hr_googlelogin'}</li>
          <li>{l s='L\'expiration (exp) du jeton est vérifiée.' mod='hr_googlelogin'}</li>
          <li>{l s='Seuls les e-mails vérifiés par Google sont acceptés (email_verified = true).' mod='hr_googlelogin'}</li>
          <li>{l s='Le mot de passe généré pour les nouveaux clients est aléatoire et cryptographiquement sûr (jamais communiqué).' mod='hr_googlelogin'}</li>
          <li>{l s='Toutes les requêtes HTTPS vers Google utilisent la vérification SSL stricte (CURLOPT_SSL_VERIFYPEER).' mod='hr_googlelogin'}</li>
        </ul>
      </div>
    </div>
  </div>
</div>
