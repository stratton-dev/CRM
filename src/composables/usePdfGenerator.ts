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
        openHtmlForPrint(html)
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
        openHtmlForPrint(html)
        return
      }
      error.value = e?.message ?? 'Błąd generowania PDF'
      throw e
    } finally {
      isGenerating.value = false
    }
  }

  function openHtmlForPrint(htmlContent: string) {
    const printWindow = window.open('', '_blank')
    if (printWindow) {
      printWindow.document.write(htmlContent)
      printWindow.document.close()
      printWindow.onload = () => printWindow.print()
    }
  }

  function getPdfFilename(type: PdfType): string {
    const map: Record<PdfType, string> = {
      'short': 'oferta-krotka-stratton.pdf',
      'long': 'oferta-pelna-stratton.pdf',
      'product-card': 'karta-produktu-eliton-prime.pdf',
    }
    return map[type]
  }

  return { generatePdf, isGenerating, error }
}
