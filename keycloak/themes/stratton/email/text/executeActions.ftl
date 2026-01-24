${msg("executeActionsTitle")}

${msg("executeActionsIntro")}

<#if requiredActions??>
<#list requiredActions as action>
- ${msg("requiredAction." + action)}
</#list>
</#if>

${msg("emailButtonFallback")}
${link}

<#if linkExpiration??>
${msg("emailLinkExpiration", linkExpirationFormatter(linkExpiration))}
</#if>

${msg("emailFooter")}
