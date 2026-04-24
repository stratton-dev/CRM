<#import "../email-layout.ftl" as layout>

<@layout.emailPage title=msg("eventLoginErrorTitle") ctaLabel=msg("eventLoginErrorCta") ctaLink=(link!'')>
  <p style="margin:0 0 12px;line-height:1.6;color:#475569;">
    ${msg("eventLoginErrorIntro")}
  </p>

  <#if event??>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-size:12px;color:#475569;">
      <#if event.date??>
        <tr>
          <td style="padding:4px 0;font-weight:700;width:120px;">${msg("eventDetailTime")}</td>
          <td style="padding:4px 0;">${event.date}</td>
        </tr>
      </#if>
      <#if event.ipAddress??>
        <tr>
          <td style="padding:4px 0;font-weight:700;width:120px;">${msg("eventDetailIp")}</td>
          <td style="padding:4px 0;">${event.ipAddress}</td>
        </tr>
      </#if>
      <#if event.clientId??>
        <tr>
          <td style="padding:4px 0;font-weight:700;width:120px;">${msg("eventDetailClient")}</td>
          <td style="padding:4px 0;">${event.clientId}</td>
        </tr>
      </#if>
    </table>
  </#if>
</@layout.emailPage>
