<#import "template.ftl" as layout>
<#import "stratton-layout.ftl" as stratton>
<#import "user-profile.ftl" as userProfile>

<@layout.registrationLayout bodyClass="stratton-login" displayMessage=false displayInfo=false; section>
  <#if section = "title">
    ${msg("strattonRegisterTitle")}
  <#elseif section = "form">
    <@stratton.authPage title=msg("strattonRegisterTitle") subtitle=msg("strattonRegisterSubtitle")>
      <form id="kc-register-form" class="space-y-4" action="${url.registrationAction}" method="post">
        <@userProfile.userProfileFormFields />

        <#if passwordRequired?? && passwordRequired>
          <div>
            <label for="password" class="sr-only">${msg("strattonPasswordLabel")}</label>
            <input
              id="password"
              name="password"
              type="password"
              placeholder="${msg("strattonPasswordLabel")}"
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
        </#if>

        <#if recaptchaRequired?? && recaptchaRequired>
          <div class="flex justify-center">
            <div class="g-recaptcha" data-sitekey="${recaptchaSiteKey}"></div>
          </div>
        </#if>

        <button id="kc-register" type="submit" class="group relative w-full flex justify-center items-center gap-2 py-3 px-4 border border-stratton-gold/50 text-sm font-bold rounded-lg text-slate-900 bg-stratton-gold hover:bg-[#b6924f] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-stratton-gold transition-all shadow-md hover:shadow-lg uppercase tracking-wide">
          ${msg("strattonRegisterButton")}
          <span class="absolute right-4">&#8594;</span>
        </button>

        <div class="text-center">
          <a href="${url.loginUrl}" class="text-xs text-slate-500 hover:underline">${msg("strattonRegisterLoginLink")}</a>
        </div>
      </form>
    </@stratton.authPage>
  </#if>
</@layout.registrationLayout>
