<#import "template.ftl" as layout>
<#import "stratton-layout.ftl" as stratton>

<@layout.registrationLayout bodyClass="stratton-login" displayMessage=false displayInfo=false; section>
  <#if section = "title">
    ${msg("strattonOtpTitle")}
  <#elseif section = "form">
    <@stratton.authPage title=msg("strattonOtpTitle") subtitle=msg("strattonOtpSubtitle")>
      <form id="kc-otp-login-form" class="space-y-4" action="${url.loginAction}" method="post">
        <div>
          <label for="otp" class="sr-only">${msg("strattonOtpLabel")}</label>
          <input
            id="otp"
            name="otp"
            type="text"
            autocomplete="one-time-code"
            placeholder="${msg("strattonOtpLabel")}"
            class="w-full border border-slate-200 rounded-lg px-4 py-3 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-stratton-gold focus:border-stratton-gold"
          />
        </div>

        <button id="kc-login" type="submit" class="group relative w-full flex justify-center items-center gap-2 py-3 px-4 border border-stratton-gold/50 text-sm font-bold rounded-lg text-slate-900 bg-stratton-gold hover:bg-[#b6924f] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-stratton-gold transition-all shadow-md hover:shadow-lg uppercase tracking-wide">
          ${msg("strattonOtpButton")}
          <span class="absolute right-4">&#8594;</span>
        </button>
      </form>
    </@stratton.authPage>
  </#if>
</@layout.registrationLayout>
