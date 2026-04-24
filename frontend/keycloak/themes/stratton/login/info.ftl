<#import "template.ftl" as layout>
<#import "stratton-layout.ftl" as stratton>

<@layout.registrationLayout bodyClass="stratton-login" displayMessage=false displayInfo=false; section>
  <#if section = "title">
    ${msg("strattonInfoTitle")}
  <#elseif section = "form">
    <#assign actionLink = "">
    <#if requiredActionsUrl??>
      <#assign actionLink = requiredActionsUrl>
    <#elseif pageRedirectUri??>
      <#assign actionLink = pageRedirectUri>
    <#elseif actionUri??>
      <#assign actionLink = actionUri>
    </#if>

    <@stratton.authPage title=msg("strattonInfoTitle") subtitle=msg("strattonInfoSubtitle")>
      <#if requiredActions?? && requiredActions?size gt 0>
        <p class="text-sm text-slate-500 text-center">${msg("strattonInfoRequiredActions")}</p>
        <ul class="text-sm text-slate-600 space-y-1">
          <#list requiredActions as action>
            <li class="flex items-center gap-2">
              <span class="text-stratton-gold">&#8226;</span>
              <span>${msg("requiredAction." + action)}</span>
            </li>
          </#list>
        </ul>
      <#elseif message?has_content>
        <p class="text-sm text-slate-500 text-center">${message.summary?no_esc}</p>
      <#else>
        <p class="text-sm text-slate-500 text-center">${msg("strattonInfoBody")}</p>
      </#if>

      <#if actionLink?has_content>
        <a href="${actionLink}" class="group relative w-full inline-flex justify-center items-center gap-2 py-3 px-4 border border-stratton-gold/50 text-sm font-bold rounded-lg text-slate-900 bg-stratton-gold hover:bg-[#b6924f] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-stratton-gold transition-all shadow-md hover:shadow-lg uppercase tracking-wide">
          <#if requiredActions?? && requiredActions?size gt 0>
            ${msg("strattonExecuteActionsButton")}
          <#else>
            ${msg("strattonInfoButton")}
          </#if>
          <span class="absolute right-4">&#8594;</span>
        </a>
      </#if>

      <#if url.loginUrl??>
        <div class="text-center">
          <a href="${url.loginUrl}" class="text-xs text-slate-500 hover:underline">${msg("strattonBackToLogin")}</a>
        </div>
      </#if>
    </@stratton.authPage>
  </#if>
</@layout.registrationLayout>
