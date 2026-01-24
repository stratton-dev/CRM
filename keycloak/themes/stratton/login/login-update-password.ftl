<#import "template.ftl" as layout>
<#import "stratton-layout.ftl" as stratton>

<@layout.registrationLayout bodyClass="stratton-login" displayMessage=false displayInfo=false; section>
  <#if section = "title">
    ${msg("strattonUpdatePasswordTitle")}
  <#elseif section = "form">
    <@stratton.authPage title=msg("strattonUpdatePasswordTitle") subtitle=msg("strattonUpdatePasswordSubtitle")>
      <form id="kc-passwd-update-form" class="space-y-4" action="${url.loginAction}" method="post">
        <div>
          <label for="password-new" class="sr-only">${msg("strattonPasswordNewLabel")}</label>
          <input
            id="password-new"
            name="password-new"
            type="password"
            placeholder="${msg("strattonPasswordNewLabel")}"
            autocomplete="new-password"
            class="w-full border border-slate-200 rounded-lg px-4 py-3 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-stratton-gold focus:border-stratton-gold"
          />
        </div>

        <div>
          <label for="password-confirm" class="sr-only">${msg("strattonPasswordConfirmLabel")}</label>
          <input
            id="password-confirm"
            name="password-confirm"
            type="password"
            placeholder="${msg("strattonPasswordConfirmLabel")}"
            autocomplete="new-password"
            class="w-full border border-slate-200 rounded-lg px-4 py-3 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-stratton-gold focus:border-stratton-gold"
          />
        </div>

        <button id="kc-update-password" type="submit" class="group relative w-full flex justify-center items-center gap-2 py-3 px-4 border border-stratton-gold/50 text-sm font-bold rounded-lg text-slate-900 bg-stratton-gold hover:bg-[#b6924f] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-stratton-gold transition-all shadow-md hover:shadow-lg uppercase tracking-wide">
          ${msg("strattonUpdatePasswordButton")}
          <span class="absolute right-4">&#8594;</span>
        </button>
      </form>
    </@stratton.authPage>
  </#if>
</@layout.registrationLayout>
