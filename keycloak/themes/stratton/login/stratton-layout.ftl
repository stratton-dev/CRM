<#macro authPage title subtitle="" showMessage=true>
  <div class="flex items-center justify-center min-h-screen bg-slate-100 relative overflow-hidden">
    <div class="absolute -top-[20%] -right-[20%] w-[800px] h-[800px] rounded-full bg-gradient-to-br from-stratton-gold/10 to-transparent blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-[20%] -left-[20%] w-[800px] h-[800px] rounded-full bg-gradient-to-tr from-stratton-blue/10 to-transparent blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-md p-8 space-y-8 bg-white rounded-2xl shadow-lg relative z-10 transition-all duration-500 hover:shadow-xl border border-slate-100">
      <div class="text-center">
        <div class="flex flex-col items-center justify-center gap-4 mb-6">
          <img src="${url.resourcesPath}/img/logo.svg" alt="Stratton logo" class="w-16 h-16" />
          <span class="text-2xl font-serif font-bold text-stratton-dark tracking-widest">STRATTON</span>
        </div>
        <h2 class="text-xl font-bold text-slate-700 font-serif">${title}</h2>
        <#if subtitle?has_content>
          <p class="text-sm text-slate-400 mt-2">${subtitle}</p>
        </#if>
      </div>

      <#if showMessage && message?has_content>
        <#assign msgType = (message.type!"info")?lower_case>
        <#assign alertClass = "text-slate-600 bg-slate-50 border-slate-100">
        <#if msgType == "success">
          <#assign alertClass = "text-emerald-700 bg-emerald-50 border-emerald-100">
        <#elseif msgType == "warning">
          <#assign alertClass = "text-amber-800 bg-amber-50 border-amber-100">
        <#elseif msgType == "error">
          <#assign alertClass = "text-red-600 bg-red-50 border-red-100">
        </#if>
        <div class="${alertClass} text-sm text-center border py-2 rounded-lg flex items-center justify-center">
          ${message.summary?no_esc}
        </div>
      </#if>

      <#nested>
    </div>
  </div>
</#macro>
