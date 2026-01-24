${msg("passwordResetTitle")}

${msg("passwordResetIntro")}

${msg("emailButtonFallback")}
${link}

<#if linkExpiration??>
${msg("emailLinkExpiration", linkExpirationFormatter(linkExpiration))}
</#if>

${msg("emailFooter")}
