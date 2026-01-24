<#import "../email-layout.ftl" as layout>

<@layout.emailPage title=msg("passwordResetTitle") ctaLabel=msg("passwordResetCta") ctaLink=link>
  <p style="margin:0 0 12px;line-height:1.6;color:#475569;">
    ${msg("passwordResetIntro")}
  </p>
  <#if linkExpiration??>
    <p style="margin:0;line-height:1.6;color:#475569;">
      ${msg("emailLinkExpiration", linkExpirationFormatter(linkExpiration))}
    </p>
  </#if>
</@layout.emailPage>
