<#macro emailPage title ctaLabel="" ctaLink="">
<!DOCTYPE html>
<html lang="${locale}">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>${title}</title>
  </head>
  <body style="margin:0;padding:0;background-color:#f8fafc;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f8fafc;padding:24px 0;">
      <tr>
        <td align="center">
          <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="width:100%;max-width:600px;background-color:#ffffff;border-radius:20px;border:1px solid #e2e8f0;">
            <tr>
              <td align="center" style="padding:32px 32px 16px;">
                <img src="https://stratton-prime.pl/img/logo-stratton-prime.svg" alt="Stratton" width="64" height="64" style="display:block;border:0;" />
                <div style="margin-top:12px;font-family:Georgia, 'Times New Roman', serif;font-size:20px;letter-spacing:4px;color:#0f172a;font-weight:700;">STRATTON</div>
              </td>
            </tr>
            <tr>
              <td style="padding:0 32px 24px;font-family:Arial, sans-serif;color:#1f2937;">
                <h1 style="margin:0 0 12px;font-size:20px;line-height:1.4;color:#0f172a;">${title}</h1>
                <#nested>

                <#if ctaLink?has_content>
                  <div style="margin:24px 0;text-align:center;">
                    <a href="${ctaLink}" style="background-color:#c5a059;color:#0f172a;text-decoration:none;padding:12px 20px;border-radius:10px;display:inline-block;font-weight:700;letter-spacing:1px;text-transform:uppercase;font-size:12px;">${ctaLabel}</a>
                  </div>
                  <div style="font-size:12px;color:#64748b;line-height:1.6;word-break:break-all;">
                    ${msg("emailButtonFallback")}<br />
                    <a href="${ctaLink}" style="color:#1e3a8a;">${ctaLink}</a>
                  </div>
                </#if>
              </td>
            </tr>
            <tr>
              <td style="padding:24px 32px 32px;font-family:Arial, sans-serif;font-size:12px;color:#64748b;line-height:1.6;border-top:1px solid #e2e8f0;">
                ${msg("emailFooter")}
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>
</#macro>
