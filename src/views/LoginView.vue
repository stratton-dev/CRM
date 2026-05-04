<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import logoUrl from '@/assets/logo.svg'
import AppIcon from '@/components/AppIcon.vue'
import { useDataStore } from '@/stores/data'
import { storeToRefs } from 'pinia'
import { useSessionStore } from '@/stores/session'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const data = useDataStore()
const { users } = storeToRefs(data)
const session = useSessionStore()
const loading = ref(false)
const email = ref('')
const password = ref('')
const devRole = ref<'ADMIN' | 'DIRECTOR' | 'MANAGER' | 'SALES' | 'CLIENT_HR'>(
  (localStorage.getItem('stratton_dev_role') as 'ADMIN' | 'DIRECTOR' | 'MANAGER' | 'SALES' | 'CLIENT_HR') || 'ADMIN'
)

const redirectTarget = computed(() => {
  const redirect = route.query.redirect
  return typeof redirect === 'string' && redirect.startsWith('/') ? redirect : '/app/dashboard'
})

const goAfterLogin = async () => {
  await router.replace(redirectTarget.value)
}

const doLogin = async () => {
  loading.value = true
  try {
    if (!auth.enabled) {
      localStorage.setItem('stratton_dev_role', devRole.value)
      await auth.login('', '')
      const list = Array.isArray(users.value) ? users.value : []
      const match = list.find((user) => user.role === devRole.value) || list[0]
      if (match) session.setCurrentUser(match.id)
      await goAfterLogin()
      return
    }

    await auth.login(email.value, password.value)
    await goAfterLogin()
  } catch (err: any) {
    console.error('Login error:', err)
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  if (auth.enabled) {
    await auth.ensureInitialized()
  }
  if (auth.isAuthenticated) {
    await goAfterLogin()
  }
})
</script>

<template>
  <div class="login-root">

    <!-- ═══════════════════════════════════════════════ -->
    <!-- HERO PANEL (left)                              -->
    <!-- ═══════════════════════════════════════════════ -->
    <div class="login-hero">
      <!-- Animated background orbs -->
      <div class="orb orb-1"></div>
      <div class="orb orb-2"></div>
      <div class="orb orb-3"></div>

      <!-- Grid overlay -->
      <div class="hero-grid"></div>

      <!-- Gold diagonal sweep -->
      <div class="hero-sweep"></div>

      <!-- Content -->
      <div class="hero-content">
        <!-- Brand mark -->
        <div class="hero-brand">
          <div class="hero-shield">
            <img :src="logoUrl" alt="Stratton Prime" class="w-12 h-12 drop-shadow-lg" />
          </div>
          <div class="hero-brand-words">
            <span class="hero-brand-main font-cinzel">STRATTON PRIME</span>
            <span class="hero-brand-sub">SYSTEM CRM 2.0</span>
          </div>
        </div>

        <!-- Gold divider -->
        <div class="hero-divider">
          <span class="hero-divider-line"></span>
          <span class="hero-divider-diamond"></span>
          <span class="hero-divider-line"></span>
        </div>

        <!-- Tagline -->
        <p class="hero-tagline">
          Inteligentne zarządzanie<br />
          relacjami z klientem
        </p>

        <!-- Feature pills -->
        <div class="hero-pills">
          <span class="hero-pill">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            Bezpieczne logowanie
          </span>
          <span class="hero-pill">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            Analityka w czasie rzeczywistym
          </span>
          <span class="hero-pill">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            Zarządzanie zespołem
          </span>
        </div>
      </div>

      <!-- Bottom stamp -->
      <div class="hero-footer">
        <span class="text-white/20 text-[10px] font-mono tracking-widest uppercase">© {{ new Date().getFullYear() }} Stratton Prime · All rights reserved</span>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════ -->
    <!-- FORM PANEL (right)                             -->
    <!-- ═══════════════════════════════════════════════ -->
    <div class="login-form-panel">
      <div class="form-container">

        <!-- Small logo for mobile / top of form -->
        <div class="form-logo-row">
          <div class="form-logo-badge">
            <img :src="logoUrl" alt="Stratton" class="w-6 h-6" />
          </div>
          <span class="font-cinzel text-[11px] font-bold text-slate-400 tracking-[0.3em] uppercase">Stratton Prime</span>
        </div>

        <!-- Heading -->
        <div class="form-heading-block">
          <h1 class="form-heading">Zaloguj się</h1>
          <div class="form-heading-bar"></div>
          <p class="form-subheading">Dostęp do systemu CRM — tylko dla autoryzowanych użytkowników.</p>
        </div>

        <!-- Error -->
        <div v-if="auth.error" class="form-error">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
          {{ auth.error }}
        </div>

        <form class="form-fields" @submit.prevent="doLogin">

          <!-- Dev mode selector -->
          <div v-if="!auth.enabled" class="dev-banner">
            <div class="dev-banner-label">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
              Tryb deweloperski — wybierz rolę
            </div>
            <select v-model="devRole" class="dev-select">
              <option value="ADMIN">ADMIN</option>
              <option value="DIRECTOR">DIRECTOR</option>
              <option value="MANAGER">MANAGER</option>
              <option value="SALES">SALES</option>
              <option value="CLIENT_HR">CLIENT_HR</option>
            </select>
          </div>

          <!-- Email + Password -->
          <template v-if="auth.enabled">
            <div class="field-group">
              <label class="field-label" for="login-email">Adres email</label>
              <div class="field-input-wrap">
                <svg class="field-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                <input
                  id="login-email"
                  v-model="email"
                  type="email"
                  autocomplete="email"
                  required
                  placeholder="twoj@email.pl"
                  class="field-input"
                />
              </div>
            </div>

            <div class="field-group">
              <label class="field-label" for="login-password">Hasło</label>
              <div class="field-input-wrap">
                <svg class="field-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                <input
                  id="login-password"
                  v-model="password"
                  type="password"
                  autocomplete="current-password"
                  required
                  placeholder="••••••••"
                  class="field-input"
                />
              </div>
            </div>
          </template>

          <!-- Submit button -->
          <button
            type="submit"
            class="submit-btn"
            :disabled="auth.initializing || loading"
          >
            <span v-if="auth.initializing || loading" class="submit-spinner">
              <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
              Logowanie…
            </span>
            <span v-else class="submit-label">
              Zaloguj się
              <svg class="submit-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
            </span>
            <div class="submit-shimmer"></div>
          </button>
        </form>

        <!-- Footer -->
        <div class="form-footer">
          <span>Stratton Prime CRM</span>
          <span class="form-footer-dot"></span>
          <span>v2.0</span>
          <span class="form-footer-dot"></span>
          <span>Wsparcie: it@stratton-prime.pl</span>
        </div>
      </div>
    </div>

  </div>
</template>

<style scoped>
/* ─── Root ─────────────────────────────────────────────────── */
.login-root {
  display: flex;
  min-height: 100dvh;
  overflow: hidden;
  background: #f8fafc;
}

/* ─── Hero Panel ────────────────────────────────────────────── */
.login-hero {
  position: relative;
  display: none;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  width: 55%;
  background: linear-gradient(145deg, #00142b 0%, #001f3d 35%, #002a52 65%, #003366 100%);
  overflow: hidden;
  animation: heroSlideIn 0.7s cubic-bezier(0.22, 1, 0.36, 1) both;
}

@media (min-width: 1024px) {
  .login-hero { display: flex; }
}

@keyframes heroSlideIn {
  from { transform: translateX(-40px); opacity: 0; }
  to   { transform: translateX(0);     opacity: 1; }
}

/* Orbs */
.orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  pointer-events: none;
}
.orb-1 {
  width: 500px; height: 500px;
  background: radial-gradient(circle, rgba(197,160,89,0.18) 0%, transparent 70%);
  top: -15%; left: -15%;
  animation: orbFloat1 18s ease-in-out infinite;
}
.orb-2 {
  width: 350px; height: 350px;
  background: radial-gradient(circle, rgba(0,51,102,0.7) 0%, transparent 70%);
  bottom: 10%; right: -10%;
  animation: orbFloat2 22s ease-in-out infinite;
}
.orb-3 {
  width: 250px; height: 250px;
  background: radial-gradient(circle, rgba(197,160,89,0.10) 0%, transparent 70%);
  bottom: 30%; left: 30%;
  animation: orbFloat3 15s ease-in-out infinite;
}

@keyframes orbFloat1 {
  0%, 100% { transform: translate(0, 0); }
  50%       { transform: translate(30px, 40px); }
}
@keyframes orbFloat2 {
  0%, 100% { transform: translate(0, 0); }
  50%       { transform: translate(-25px, -30px); }
}
@keyframes orbFloat3 {
  0%, 100% { transform: translate(0, 0); }
  50%       { transform: translate(20px, -20px); }
}

@media (prefers-reduced-motion: reduce) {
  .orb { animation: none; }
}

/* Grid overlay */
.hero-grid {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(197,160,89,0.04) 1px, transparent 1px),
    linear-gradient(90deg, rgba(197,160,89,0.04) 1px, transparent 1px);
  background-size: 48px 48px;
  pointer-events: none;
}

/* Gold diagonal sweep */
.hero-sweep {
  position: absolute;
  top: -30%;
  left: -10%;
  width: 60%;
  height: 160%;
  background: linear-gradient(
    105deg,
    transparent 40%,
    rgba(197,160,89,0.06) 50%,
    transparent 60%
  );
  transform: skewX(-10deg);
  pointer-events: none;
  animation: sweepPan 8s ease-in-out infinite alternate;
}

@keyframes sweepPan {
  from { left: -20%; }
  to   { left: 10%; }
}

@media (prefers-reduced-motion: reduce) {
  .hero-sweep { animation: none; }
}

/* Hero Content */
.hero-content {
  position: relative;
  z-index: 10;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2rem;
  padding: 3rem 2.5rem;
  text-align: center;
  animation: fadeUp 0.9s 0.2s cubic-bezier(0.22, 1, 0.36, 1) both;
}

@keyframes fadeUp {
  from { opacity: 0; transform: translateY(20px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* Brand */
.hero-brand {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1.25rem;
}

.hero-shield {
  width: 72px;
  height: 72px;
  border-radius: 18px;
  background: rgba(197,160,89,0.12);
  border: 1px solid rgba(197,160,89,0.3);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 0 40px rgba(197,160,89,0.15), inset 0 1px 0 rgba(197,160,89,0.2);
}

.hero-brand-words {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
}

.hero-brand-main {
  font-size: 1.625rem;
  font-weight: 900;
  color: #C5A059;
  letter-spacing: 0.25em;
  line-height: 1;
  text-shadow: 0 0 30px rgba(197,160,89,0.4);
}

.hero-brand-sub {
  font-size: 0.625rem;
  font-weight: 700;
  color: rgba(255,255,255,0.35);
  letter-spacing: 0.4em;
  text-transform: uppercase;
}

/* Divider */
.hero-divider {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  width: 100%;
  max-width: 280px;
}

.hero-divider-line {
  flex: 1;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(197,160,89,0.5), transparent);
}

.hero-divider-diamond {
  width: 6px;
  height: 6px;
  background: #C5A059;
  transform: rotate(45deg);
  flex-shrink: 0;
  box-shadow: 0 0 8px rgba(197,160,89,0.6);
}

/* Tagline */
.hero-tagline {
  font-size: 1.375rem;
  font-weight: 300;
  color: rgba(255,255,255,0.85);
  line-height: 1.6;
  letter-spacing: 0.01em;
  max-width: 300px;
}

/* Pills */
.hero-pills {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  width: 100%;
  max-width: 300px;
}

.hero-pill {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.875rem;
  border-radius: 999px;
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(197,160,89,0.15);
  color: rgba(255,255,255,0.55);
  font-size: 0.6875rem;
  font-weight: 500;
  letter-spacing: 0.02em;
  backdrop-filter: blur(8px);
}

.hero-pill svg {
  color: #C5A059;
  flex-shrink: 0;
}

/* Hero footer */
.hero-footer {
  position: absolute;
  bottom: 1.25rem;
  left: 0;
  right: 0;
  display: flex;
  justify-content: center;
  z-index: 10;
}

/* ─── Form Panel ────────────────────────────────────────────── */
.login-form-panel {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem 1.5rem;
  background: #f8fafc;
  animation: formSlideIn 0.7s 0.15s cubic-bezier(0.22, 1, 0.36, 1) both;
}

@keyframes formSlideIn {
  from { opacity: 0; transform: translateX(30px); }
  to   { opacity: 1; transform: translateX(0); }
}

.form-container {
  width: 100%;
  max-width: 400px;
  display: flex;
  flex-direction: column;
  gap: 1.75rem;
}

/* Logo row */
.form-logo-row {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding-bottom: 0.25rem;
}

.form-logo-badge {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: linear-gradient(135deg, #001f3d, #003366);
  border: 1px solid rgba(197,160,89,0.3);
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Heading block */
.form-heading-block {
  display: flex;
  flex-direction: column;
  gap: 0.625rem;
}

.form-heading {
  font-size: 1.875rem;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: -0.02em;
  line-height: 1.1;
}

.form-heading-bar {
  width: 48px;
  height: 3px;
  border-radius: 999px;
  background: linear-gradient(90deg, #C5A059, #d4b06a);
}

.form-subheading {
  font-size: 0.8125rem;
  color: #64748b;
  line-height: 1.6;
  margin-top: 0.125rem;
}

/* Error */
.form-error {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.625rem 0.875rem;
  border-radius: 0.625rem;
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #dc2626;
  font-size: 0.8125rem;
  font-weight: 500;
}

/* Form fields */
.form-fields {
  display: flex;
  flex-direction: column;
  gap: 1.125rem;
}

/* Dev banner */
.dev-banner {
  padding: 0.875rem 1rem;
  border-radius: 0.75rem;
  background: #fffbeb;
  border: 1px solid #fde68a;
  display: flex;
  flex-direction: column;
  gap: 0.625rem;
}

.dev-banner-label {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.6875rem;
  font-weight: 700;
  color: #92400e;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.dev-select {
  width: 100%;
  border: 1px solid #fcd34d;
  border-radius: 0.5rem;
  padding: 0.5rem 0.75rem;
  font-size: 0.875rem;
  background: #fff;
  color: #92400e;
  font-weight: 600;
  cursor: pointer;
  outline: none;
}

.dev-select:focus {
  border-color: #C5A059;
  box-shadow: 0 0 0 3px rgba(197,160,89,0.15);
}

/* Field group */
.field-group {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.field-label {
  font-size: 0.75rem;
  font-weight: 700;
  color: #374151;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.field-input-wrap {
  position: relative;
}

.field-icon {
  position: absolute;
  left: 0.875rem;
  top: 50%;
  transform: translateY(-50%);
  width: 1rem;
  height: 1rem;
  color: #94a3b8;
  pointer-events: none;
  transition: color 200ms ease;
}

.field-input {
  width: 100%;
  padding: 0.75rem 0.875rem 0.75rem 2.5rem;
  border: 1.5px solid #e2e8f0;
  border-radius: 0.75rem;
  background: #fff;
  font-size: 0.875rem;
  color: #0f172a;
  transition: border-color 200ms ease, box-shadow 200ms ease;
  outline: none;
  box-shadow: 0 1px 2px rgba(0,0,0,0.04);
}

.field-input::placeholder {
  color: #cbd5e1;
}

.field-input:focus {
  border-color: #C5A059;
  box-shadow: 0 0 0 3px rgba(197,160,89,0.12), 0 1px 2px rgba(0,0,0,0.04);
}

.field-input:focus + .field-icon,
.field-input-wrap:focus-within .field-icon {
  color: #C5A059;
}

/* Submit button */
.submit-btn {
  position: relative;
  overflow: hidden;
  width: 100%;
  padding: 0.875rem 1.25rem;
  border-radius: 0.875rem;
  background: linear-gradient(135deg, #C5A059 0%, #d4b06a 50%, #C5A059 100%);
  background-size: 200% 100%;
  border: none;
  cursor: pointer;
  font-size: 0.875rem;
  font-weight: 700;
  color: #fff;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  transition: background-position 400ms ease, box-shadow 200ms ease, transform 150ms ease;
  box-shadow: 0 4px 14px rgba(197,160,89,0.4), 0 2px 4px rgba(0,0,0,0.1);
  margin-top: 0.25rem;
}

.submit-btn:hover:not(:disabled) {
  background-position: right center;
  box-shadow: 0 6px 20px rgba(197,160,89,0.5), 0 2px 4px rgba(0,0,0,0.1);
  transform: translateY(-1px);
}

.submit-btn:active:not(:disabled) {
  transform: translateY(0);
}

.submit-btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.submit-label {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.625rem;
}

.submit-arrow {
  width: 1rem;
  height: 1rem;
  transition: transform 200ms ease;
}

.submit-btn:hover:not(:disabled) .submit-arrow {
  transform: translateX(4px);
}

.submit-spinner {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

/* Shimmer effect */
.submit-shimmer {
  position: absolute;
  top: 0;
  left: -100%;
  width: 60%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
  transform: skewX(-20deg);
  animation: shimmer 3s 1s ease-in-out infinite;
  pointer-events: none;
}

@keyframes shimmer {
  0%   { left: -100%; }
  50%  { left: 150%; }
  100% { left: 150%; }
}

@media (prefers-reduced-motion: reduce) {
  .submit-shimmer { animation: none; }
}

/* Form footer */
.form-footer {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  font-size: 0.6875rem;
  color: #94a3b8;
  flex-wrap: wrap;
}

.form-footer-dot {
  width: 3px;
  height: 3px;
  border-radius: 50%;
  background: #cbd5e1;
  flex-shrink: 0;
}
</style>
