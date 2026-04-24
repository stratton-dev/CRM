<#import "template.ftl" as layout>
<#import "stratton-layout.ftl" as stratton>

<@layout.registrationLayout bodyClass="stratton-login" displayMessage=false displayInfo=false; section>
  <#if section = "title">
    ${msg("strattonTermsTitle")}
  <#elseif section = "form">
    <@stratton.authPage title=msg("strattonTermsTitle") subtitle=msg("strattonTermsSubtitle")>
      <#assign termsContent = "">
      <#if terms??>
        <#assign termsContent = terms>
      <#elseif termsText??>
        <#assign termsContent = termsText>
      </#if>

      <div class="text-sm text-slate-600 leading-relaxed max-h-64 overflow-y-auto border border-slate-100 rounded-lg p-4 bg-slate-50">
        ${termsContent?no_esc}
      </div>

      <form id="kc-terms-form" class="space-y-3" action="${url.loginAction}" method="post">
        <button id="kc-accept" name="accept" value="true" type="submit" class="group relative w-full flex justify-center items-center gap-2 py-3 px-4 border border-stratton-gold/50 text-sm font-bold rounded-lg text-slate-900 bg-stratton-gold hover:bg-[#b6924f] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-stratton-gold transition-all shadow-md hover:shadow-lg uppercase tracking-wide">
          ${msg("strattonTermsAccept")}
          <span class="absolute right-4">&#8594;</span>
        </button>
        <button id="kc-decline" name="cancel" value="true" type="submit" class="w-full py-3 px-4 border border-slate-200 text-sm font-semibold rounded-lg text-slate-600 hover:bg-slate-50 transition-all">
          ${msg("strattonTermsDecline")}
        </button>
      </form>
    </@stratton.authPage>
  </#if>
</@layout.registrationLayout>
