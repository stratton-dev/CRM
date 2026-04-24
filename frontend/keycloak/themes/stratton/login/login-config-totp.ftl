<#import "template.ftl" as layout>
<#import "stratton-layout.ftl" as stratton>

<@layout.registrationLayout bodyClass="stratton-login" displayMessage=false displayInfo=false; section>
  <#if section = "title">
    ${msg("strattonTotpTitle")}
  <#elseif section = "form">
    <@stratton.authPage title=msg("strattonTotpTitle") subtitle=msg("strattonTotpSubtitle")>
      <div class="space-y-4">
        <p class="text-sm text-slate-500 text-center">${msg("strattonTotpInstruction")}</p>

        <#if totp?? && totp.qrCode??>
          <div class="flex items-center justify-center">
            <img alt="QR" src="data:image/png;base64,${totp.qrCode}" class="w-40 h-40 border border-slate-200 rounded-xl bg-white" />
          </div>
        </#if>

        <#if totp?? && totp.totpSecret??>
          <div class="text-xs text-slate-500 text-center">
            ${msg("strattonTotpManualKey")}: <span class="font-mono text-slate-700">${totp.totpSecret}</span>
          </div>
        </#if>
      </div>

      <form id="kc-totp-settings-form" class="space-y-4" action="${url.loginAction}" method="post">
        <div>
          <label for="totp" class="sr-only">${msg("strattonOtpLabel")}</label>
          <input
            id="totp"
            name="totp"
            type="text"
            autocomplete="one-time-code"
            placeholder="${msg("strattonOtpLabel")}"
            class="w-full border border-slate-200 rounded-lg px-4 py-3 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-stratton-gold focus:border-stratton-gold"
          />
        </div>

        <#if totp??>
          <#if totp.totpSecret??>
            <input type="hidden" name="totpSecret" value="${totp.totpSecret}" />
          </#if>
          <#if totp.totpSecretEncoded??>
            <input type="hidden" name="totpSecretEncoded" value="${totp.totpSecretEncoded}" />
          </#if>
          <#if totp.algorithm??>
            <input type="hidden" name="algorithm" value="${totp.algorithm}" />
          </#if>
          <#if totp.digits??>
            <input type="hidden" name="digits" value="${totp.digits}" />
          </#if>
          <#if totp.period??>
            <input type="hidden" name="period" value="${totp.period}" />
          </#if>
          <#if totp.type??>
            <input type="hidden" name="type" value="${totp.type}" />
          </#if>
        </#if>

        <button id="kc-totp" type="submit" class="group relative w-full flex justify-center items-center gap-2 py-3 px-4 border border-stratton-gold/50 text-sm font-bold rounded-lg text-slate-900 bg-stratton-gold hover:bg-[#b6924f] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-stratton-gold transition-all shadow-md hover:shadow-lg uppercase tracking-wide">
          ${msg("strattonTotpButton")}
          <span class="absolute right-4">&#8594;</span>
        </button>
      </form>
    </@stratton.authPage>
  </#if>
</@layout.registrationLayout>
