<#import "template.ftl" as layout>
<#import "stratton-layout.ftl" as stratton>

<@layout.registrationLayout bodyClass="stratton-login" displayMessage=false displayInfo=false; section>
  <#if section = "title">
    ${msg("strattonResetTitle")}
  <#elseif section = "form">
    <@stratton.authPage title=msg("strattonResetTitle") subtitle=msg("strattonResetSubtitle")>
      <form id="kc-reset-password-form" class="space-y-4" action="${url.loginAction}" method="post">
        <div>
          <label for="username" class="sr-only">${msg("strattonEmailLabel")}</label>
          <input
            id="username"
            name="username"
            type="text"
            value="${(username!'')}"
            placeholder="${msg("strattonEmailLabel")}"
            autocomplete="username"
            class="w-full border border-slate-200 rounded-lg px-4 py-3 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-stratton-gold focus:border-stratton-gold"
          />
        </div>

        <button id="kc-reset-password" type="submit" class="group relative w-full flex justify-center items-center gap-2 py-3 px-4 border border-stratton-gold/50 text-sm font-bold rounded-lg text-slate-900 bg-stratton-gold hover:bg-[#b6924f] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-stratton-gold transition-all shadow-md hover:shadow-lg uppercase tracking-wide">
          ${msg("strattonResetButton")}
          <span class="absolute right-4">&#8594;</span>
        </button>

        <div class="text-center">
          <a href="${url.loginUrl}" class="text-xs text-slate-500 hover:underline">${msg("strattonBackToLogin")}</a>
        </div>
      </form>
    </@stratton.authPage>
  </#if>
</@layout.registrationLayout>
