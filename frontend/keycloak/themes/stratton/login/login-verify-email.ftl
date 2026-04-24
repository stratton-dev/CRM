<#import "template.ftl" as layout>
<#import "stratton-layout.ftl" as stratton>

<@layout.registrationLayout bodyClass="stratton-login" displayMessage=false displayInfo=false; section>
  <#if section = "title">
    ${msg("strattonVerifyEmailTitle")}
  <#elseif section = "form">
    <@stratton.authPage title=msg("strattonVerifyEmailTitle") subtitle=msg("strattonVerifyEmailSubtitle")>
      <div class="text-sm text-slate-500 text-center">
        ${msg("strattonVerifyEmailHelp")}
      </div>

      <form id="kc-verify-email-form" class="space-y-4" action="${url.loginAction}" method="post">
        <button id="kc-verify-email" type="submit" class="group relative w-full flex justify-center items-center gap-2 py-3 px-4 border border-stratton-gold/50 text-sm font-bold rounded-lg text-slate-900 bg-stratton-gold hover:bg-[#b6924f] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-stratton-gold transition-all shadow-md hover:shadow-lg uppercase tracking-wide">
          ${msg("strattonVerifyEmailButton")}
          <span class="absolute right-4">&#8594;</span>
        </button>
      </form>

      <div class="text-center">
        <a href="${url.loginUrl}" class="text-xs text-slate-500 hover:underline">${msg("strattonBackToLogin")}</a>
      </div>
    </@stratton.authPage>
  </#if>
</@layout.registrationLayout>
