import { describe, it, beforeEach, afterEach, expect } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia, storeToRefs } from 'pinia'
import type { UserRole } from '@/types/models'
import App from '@/App.vue'
import router from '@/router'
import { useSessionStore } from '@/stores/session'
import { useDataStore } from '@/stores/data'

const roleRoutes: Array<[UserRole, string[]]> = [
  [
    'ADMIN',
    [
      '/app/dashboard',
      '/app/analytics',
      '/app/clients',
      '/app/structure',
      '/app/hr-panel',
      '/app/settlements',
      '/app/commission-thresholds',
      '/app/autenti-panel',
      '/app/leaderboard',
      '/app/settings',
      '/app/calendar',
      '/app/mailbox',
      '/app/knowledge-base',
    ],
  ],
  [
    'DIRECTOR',
    [
      '/app/dashboard',
      '/app/analytics',
      '/app/structure',
      '/app/clients',
      '/app/settlements',
      '/app/calendar',
      '/app/mailbox',
      '/app/knowledge-base',
    ],
  ],
  [
    'MANAGER',
    [
      '/app/dashboard',
      '/app/structure',
      '/app/clients',
      '/app/settlements',
      '/app/calendar',
      '/app/mailbox',
      '/app/knowledge-base',
    ],
  ],
  [
    'SALES',
    [
      '/app/dashboard',
      '/app/clients',
      '/app/quick-calculator',
      '/app/settlements',
      '/app/calendar',
      '/app/mailbox',
      '/app/knowledge-base',
    ],
  ],
]

const errorHandler = (err: unknown) => {
  throw err
}

const navigateRoutes = async (paths: string[]) => {
  for (const path of paths) {
    if (router.currentRoute.value.path !== path) {
      await router.push(path)
    }
    await flushPromises()
    expect(router.currentRoute.value.path).toBe(path)
  }
}

describe('sidebar routes', () => {
  let wrapper: ReturnType<typeof mount> | null = null

  beforeEach(async () => {
    localStorage.clear()
    const pinia = createPinia()
    setActivePinia(pinia)
    await router.push('/login')
    await router.isReady()
    wrapper = mount(App, {
      global: {
        plugins: [pinia, router],
        config: { errorHandler },
      },
    })
    await flushPromises()
  })

  afterEach(() => {
    wrapper?.unmount()
    wrapper = null
  })

  it.each(roleRoutes)('renders sidebar routes for %s', async (role, paths) => {
    const session = useSessionStore()
    const data = useDataStore()
    const { users } = storeToRefs(data)
    const list = Array.isArray(users.value) ? users.value : []
    const match = list.find((user) => user.role === role)
    expect(match).toBeTruthy()
    session.setCurrentUser(match?.id || null)
    await flushPromises()
    await navigateRoutes(paths)
  })
})
