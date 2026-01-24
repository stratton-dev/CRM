<#import "template.ftl" as layout>
<#import "stratton-layout.ftl" as stratton>
<#import "user-profile.ftl" as userProfile>

<@layout.registrationLayout bodyClass="stratton-login" displayMessage=false displayInfo=false; section>
  <#if section = "title">
    ${msg("strattonUpdateProfileTitle")}
  <#elseif section = "form">
    <@stratton.authPage title=msg("strattonUpdateProfileTitle") subtitle=msg("strattonUpdateProfileSubtitle")>
      <form id="kc-update-profile-form" class="space-y-4" action="${url.loginAction}" method="post">
        <@userProfile.userProfileFormFields />

        <button id="kc-update-profile" type="submit" class="group relative w-full flex justify-center items-center gap-2 py-3 px-4 border border-stratton-gold/50 text-sm font-bold rounded-lg text-slate-900 bg-stratton-gold hover:bg-[#b6924f] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-stratton-gold transition-all shadow-md hover:shadow-lg uppercase tracking-wide">
          ${msg("strattonUpdateProfileButton")}
          <span class="absolute right-4">&#8594;</span>
        </button>
      </form>
    </@stratton.authPage>
  </#if>
</@layout.registrationLayout>
