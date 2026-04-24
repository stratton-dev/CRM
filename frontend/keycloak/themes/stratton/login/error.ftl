<#import "template.ftl" as layout>
<#import "stratton-layout.ftl" as stratton>

<@layout.registrationLayout bodyClass="stratton-login" displayMessage=false displayInfo=false; section>
  <#if section = "title">
    ${msg("strattonErrorTitle")}
  <#elseif section = "form">
    <@stratton.authPage title=msg("strattonErrorTitle") subtitle=msg("strattonErrorSubtitle")>
      <div class="space-y-3 text-center">
        <#if url.loginRestartFlowUrl??>
          <a href="${url.loginRestartFlowUrl}" class="group relative w-full inline-flex justify-center items-center gap-2 py-3 px-4 border border-stratton-gold/50 text-sm font-bold rounded-lg text-slate-900 bg-stratton-gold hover:bg-[#b6924f] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-stratton-gold transition-all shadow-md hover:shadow-lg uppercase tracking-wide">
            ${msg("strattonTryAgain")}
            <span class="absolute right-4">&#8594;</span>
          </a>
        <#elseif url.loginUrl??>
          <a href="${url.loginUrl}" class="group relative w-full inline-flex justify-center items-center gap-2 py-3 px-4 border border-stratton-gold/50 text-sm font-bold rounded-lg text-slate-900 bg-stratton-gold hover:bg-[#b6924f] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-stratton-gold transition-all shadow-md hover:shadow-lg uppercase tracking-wide">
            ${msg("strattonBackToLogin")}
            <span class="absolute right-4">&#8594;</span>
          </a>
        </#if>
      </div>
    </@stratton.authPage>
  </#if>
</@layout.registrationLayout>
