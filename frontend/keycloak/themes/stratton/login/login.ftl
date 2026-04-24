<#import "template.ftl" as layout>
<#import "stratton-layout.ftl" as stratton>

<@layout.registrationLayout bodyClass="stratton-login" displayMessage=false displayInfo=false; section>
  <#if section = "title">
    ${msg("strattonLoginTitle")}
  <#elseif section = "header">

  <#elseif section = "form">
    <@stratton.authPage title=msg("strattonLoginTitle") subtitle=msg("strattonLoginSubtitle")>
      <form id="kc-form-login" class="space-y-4" onsubmit="login.disabled = true; return true;" action="${url.loginAction}" method="post">
        <div>
          <label for="username" class="sr-only">${msg("strattonEmailLabel")}</label>
          <input
            id="username"
            name="username"
            type="text"
            value="${(login.username!'')}"
            placeholder="${msg("strattonEmailLabel")}"
            autofocus
            autocomplete="username"
            class="w-full border border-slate-200 rounded-lg px-4 py-3 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-stratton-gold focus:border-stratton-gold"
          />
        </div>

        <div>
          <label for="password" class="sr-only">${msg("strattonPasswordLabel")}</label>
          <input
            id="password"
            name="password"
            type="password"
            placeholder="${msg("strattonPasswordLabel")}"
            autocomplete="current-password"
            class="w-full border border-slate-200 rounded-lg px-4 py-3 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-stratton-gold focus:border-stratton-gold"
          />
        </div>

        <#if realm.rememberMe?? && realm.rememberMe>
          <label class="flex items-center gap-2 text-xs text-slate-500">
            <input id="rememberMe" name="rememberMe" type="checkbox" class="rounded border-slate-300 text-stratton-gold focus:ring-stratton-gold" />
            ${msg("strattonRememberMe")}
          </label>
        </#if>

        <button id="kc-login" name="login" type="submit" class="group relative w-full flex justify-center items-center gap-2 py-3 px-4 border border-stratton-gold/50 text-sm font-bold rounded-lg text-slate-900 bg-stratton-gold hover:bg-[#b6924f] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-stratton-gold transition-all shadow-md hover:shadow-lg uppercase tracking-wide">
          ${msg("strattonLoginButton")}
          <span class="absolute right-4">&#8594;</span>
        </button>

        <#if realm.resetPasswordAllowed>
          <div class="text-center">
            <a href="${url.loginResetCredentialsUrl}" class="text-xs text-slate-500 hover:underline">${msg("strattonForgotPassword")}</a>
          </div>
        </#if>
      </form>
    </@stratton.authPage>
  </#if>
</@layout.registrationLayout>
