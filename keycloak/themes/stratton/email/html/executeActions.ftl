<#import "../email-layout.ftl" as layout>

<@layout.emailPage title=msg("executeActionsTitle") ctaLabel=msg("executeActionsCta") ctaLink=link>
  <p style="margin:0 0 12px;line-height:1.6;color:#475569;">
    ${msg("executeActionsIntro")}
  </p>

  <#if requiredActions??>
    <ul style="margin:0 0 12px 18px;padding:0;color:#475569;">
      <#list requiredActions as action>
        <li style="margin:4px 0;">${msg("requiredAction." + action)}</li>
      </#list>
    </ul>
  </#if>

  <#if linkExpiration??>
    <p style="margin:0;line-height:1.6;color:#475569;">
      ${msg("emailLinkExpiration", linkExpirationFormatter(linkExpiration))}
    </p>
  </#if>
</@layout.emailPage>
