import { describe, it, expect } from 'vitest'
import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const projectRoot = path.resolve(__dirname, '../..')
const read = (rel: string) => fs.readFileSync(path.join(projectRoot, rel), 'utf8')

describe('UI layout consistency', () => {
  it('imports custom.css into tailwind.css', () => {
    const css = read('src/assets/tailwind.css')
    expect(css).toContain('@import "./custom.css"')
  })

  it('defines core crm utility classes', () => {
    const css = read('src/assets/custom.css')
    const required = [
      '.crm-card',
      '.crm-table',
      '.crm-table-head',
      '.crm-table-th',
      '.crm-table-td',
      '.crm-input',
      '.crm-select',
      '.crm-badge-success',
      '.crm-badge-warn',
    ]
    required.forEach((cls) => expect(css).toContain(cls))
  })

  const tableViews = [
    'src/views/admin/UsersManagementView.vue',
    'src/views/admin/SystemLogsView.vue',
    'src/views/admin/AutentiPanelView.vue',
    'src/views/NotificationsView.vue',
    'src/views/SettlementsView.vue',
    'src/views/hr/HrPanelView.vue',
    'src/views/DealsView.vue',
    'src/views/CustomersView.vue',
  ]

  tableViews.forEach((file) => {
    it(`${file} uses crm table styles`, () => {
      const content = read(file)
      expect(content).toContain('crm-table')
      expect(content).toContain('crm-table-head')
    })
  })

  const formViews = [
    'src/views/admin/AutentiPanelView.vue',
    'src/views/hr/HrPanelView.vue',
  ]

  formViews.forEach((file) => {
    it(`${file} uses crm form controls`, () => {
      const content = read(file)
      expect(content).toMatch(/crm-input|crm-select/)
    })
  })
})
