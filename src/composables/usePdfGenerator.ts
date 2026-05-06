import { ref } from 'vue'
import apiClient from '@/api/client'

export type PdfType = 'short' | 'long' | 'product-card'

export function usePdfGenerator() {
  const isGenerating = ref(false)
  const error = ref<string | null>(null)

  async function generatePdf(type: PdfType, data: Record<string, unknown>) {
    isGenerating.value = true
    error.value = null
    try {
      const response = await apiClient.post(
        '/v1/pdf/generate-offer',
        { type, data },
        { responseType: 'blob', timeout: 60000 }
      )

      // Check if server returned HTML fallback (Puppeteer unavailable on Railway)
      const contentType = response.headers['content-type'] || ''
      if (contentType.includes('text/html') || response.headers['x-pdf-fallback']) {
        const html = await (response.data as Blob).text()
        await generatePdfFromHtml(html, getPdfFilename(type))
        return
      }

      const url = URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }))
      const filename = getPdfFilename(type)
      const anchor = document.createElement('a')
      anchor.href = url
      anchor.download = filename
      anchor.click()
      setTimeout(() => URL.revokeObjectURL(url), 5000)
    } catch (e: any) {
      // 501 = Puppeteer unavailable fallback
      if (e?.response?.status === 501) {
        const blob = e.response.data as Blob
        const html = await blob.text()
        await generatePdfFromHtml(html, getPdfFilename(type))
        return
      }
      error.value = e?.message ?? 'Błąd generowania PDF'
      throw e
    } finally {
      isGenerating.value = false
    }
  }

  async function generatePdfFromHtml(htmlContent: string, filename: string) {
    const [{ default: html2canvas }, { jsPDF }] = await Promise.all([
      import('html2canvas'),
      import('jspdf'),
    ])

    // Parse full HTML document and extract styles + body
    const parser = new DOMParser()
    const doc = parser.parseFromString(htmlContent, 'text/html')

    // Build hidden container in current document (A4 width at 96dpi = 794px)
    const container = document.createElement('div')
    container.style.cssText =
      'position:fixed;left:-9999px;top:0;width:794px;background:white;z-index:-1;'

    const styleEl = document.createElement('style')
    styleEl.textContent = Array.from(doc.querySelectorAll('style'))
      .map((s) => s.textContent)
      .join('\n')
    container.appendChild(styleEl)

    const contentDiv = document.createElement('div')
    contentDiv.innerHTML = doc.body.innerHTML
    container.appendChild(contentDiv)

    document.body.appendChild(container)

    try {
      // Wait for images and fonts to load
      const images = Array.from(container.querySelectorAll('img'))
      await Promise.all(
        images.map((img) =>
          img.complete
            ? Promise.resolve()
            : new Promise<void>((r) => {
                img.onload = () => r()
                img.onerror = () => r()
              }),
        ),
      )
      await document.fonts.ready

      const canvas = await html2canvas(container, {
        scale: 2,
        useCORS: true,
        allowTaint: true,
        width: 794,
        backgroundColor: '#ffffff',
      })

      const pdf = new jsPDF({ orientation: 'p', unit: 'mm', format: 'a4' })
      const A4_W = 210
      const A4_H = 297

      const imgW = A4_W
      const imgH = (canvas.height * A4_W) / canvas.width
      const pageData = canvas.toDataURL('image/jpeg', 0.95)

      let yOffset = 0
      pdf.addImage(pageData, 'JPEG', 0, yOffset, imgW, imgH)

      let remaining = imgH - A4_H
      while (remaining > 0) {
        yOffset -= A4_H
        remaining -= A4_H
        pdf.addPage()
        pdf.addImage(pageData, 'JPEG', 0, yOffset, imgW, imgH)
      }

      pdf.save(filename)
    } finally {
      document.body.removeChild(container)
    }
  }

  function getPdfFilename(type: PdfType): string {
    const map: Record<PdfType, string> = {
      short: 'oferta-krotka-stratton.pdf',
      long: 'oferta-pelna-stratton.pdf',
      'product-card': 'karta-produktu-eliton-prime.pdf',
    }
    return map[type]
  }

  return { generatePdf, isGenerating, error }
}
