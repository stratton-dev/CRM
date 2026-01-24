<#import "../email-layout.ftl" as layout>

<@layout.emailPage title=msg("emailVerificationTitle") ctaLabel=msg("emailVerificationCta") ctaLink=link>
  <p style="margin:0 0 12px;line-height:1.6;color:#475569;">
    ${msg("emailVerificationIntro")}
  </p>
  <#if linkExpiration??>
    <p style="margin:0;line-height:1.6;color:#475569;">
      ${msg("emailLinkExpiration", linkExpirationFormatter(linkExpiration))}
    </p>
  </#if>
</@layout.emailPage>
