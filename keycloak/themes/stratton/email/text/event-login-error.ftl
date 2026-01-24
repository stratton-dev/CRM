${msg("eventLoginErrorTitle")}

${msg("eventLoginErrorIntro")}

<#if event??>
<#if event.date??>${msg("eventDetailTime")}: ${event.date}
</#if>
<#if event.ipAddress??>${msg("eventDetailIp")}: ${event.ipAddress}
</#if>
<#if event.clientId??>${msg("eventDetailClient")}: ${event.clientId}
</#if>
</#if>

<#if link??>
${msg("emailButtonFallback")}
${link}
</#if>

${msg("emailFooter")}
