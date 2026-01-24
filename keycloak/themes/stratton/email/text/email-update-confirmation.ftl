${msg("emailUpdateConfirmationTitle")}

${msg("emailUpdateConfirmationIntro")}

${msg("emailButtonFallback")}
${link}

<#if linkExpiration??>
${msg("emailLinkExpiration", linkExpirationFormatter(linkExpiration))}
</#if>

${msg("emailFooter")}
