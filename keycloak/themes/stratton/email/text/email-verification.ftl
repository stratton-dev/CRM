${msg("emailVerificationTitle")}

${msg("emailVerificationIntro")}

${msg("emailButtonFallback")}
${link}

<#if linkExpiration??>
${msg("emailLinkExpiration", linkExpirationFormatter(linkExpiration))}
</#if>

${msg("emailFooter")}
