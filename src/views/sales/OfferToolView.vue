<script setup lang="ts">
import { computed, ref, watchEffect } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useClientStore } from '@/stores/client'
import type { Client, SavedOffer } from '@/types/models'

const clientStore = useClientStore()
const { clients } = storeToRefs(clientStore)
const route = useRoute()
const router = useRouter()

const clientId = ref('')
const client = ref<Client | undefined>(undefined)
const step = ref(1)

const formData = ref({
  employeesUop: 0,
  avgWageUop: 0,
  employeesUz: 0,
})

const history = computed(() => client.value?.savedOffers || [])

watchEffect(() => {
  const id = route.params.clientId as string | undefined
  if (!id) return
  clientId.value = id
  const c = clients.value.find((cl) => cl.id === id)
  if (c) {
    client.value = c
    formData.value.employeesUop = c.employeesUop
    formData.value.avgWageUop = c.avgWageUop
    formData.value.employeesUz = c.employeesUz
  }
})

const setStep = (s: number) => {
  if (s < step.value || s === step.value + 1) step.value = s
}

const next = () => {
  if (step.value === 3 && clientId.value) {
    clientStore.updateClient(clientId.value, {
      employeesUop: formData.value.employeesUop,
      avgWageUop: formData.value.avgWageUop,
      employeesUz: formData.value.employeesUz,
      status: 'IN_TALKS',
    })
  }
  step.value += 1
}

const prev = () => {
  step.value -= 1
}

const savingsMonthly = computed(() => formData.value.employeesUop * formData.value.avgWageUop * 0.15 + formData.value.employeesUz * 200)

const saveVersion = () => {
  const name = window.prompt('Nazwa wariantu:', `Wariant ${history.value.length + 1}`)
  if (name && clientId.value) {
    const offer: Omit<SavedOffer, 'id' | 'date'> = {
      employeesUop: formData.value.employeesUop,
      avgWageUop: formData.value.avgWageUop,
      employeesUz: formData.value.employeesUz,
      estimatedSavings: savingsMonthly.value,
      name,
    }
    clientStore.saveOffer(clientId.value, offer)
  }
}

const loadVersion = (offer: SavedOffer) => {
  if (window.confirm('Wczytać dane?')) {
    formData.value.employeesUop = offer.employeesUop
    formData.value.avgWageUop = offer.avgWageUop
    formData.value.employeesUz = offer.employeesUz
    step.value = 4
  }
}

const generateContract = () => {
  if (clientId.value) router.push(`/app/contract-preview/${clientId.value}`)
}

const finish = () => {
  router.push('/app/clients')
}
</script>

<template>
  <div class="flex h-[calc(100vh-80px)] flex-col p-4 bg-gray-100">
    <div class="bg-white border border-gray-300 rounded shadow-sm mb-4 px-2 py-3 flex items-center overflow-x-auto">
      <div v-for="s in [1,2,3,4,5]" :key="s" class="flex items-center group cursor-pointer" @click="setStep(s)">
        <div class="flex items-center">
          <div class="w-6 h-6 rounded-full flex items-center justify-center border-2 text-xs font-bold mr-2" :class="step === s ? 'bg-brand-main border-brand-main text-white' : step > s ? 'bg-white border-brand-main text-brand-main' : 'bg-white border-gray-300 text-gray-400'">
            <span v-if="step > s">✓</span>
            <span v-else>{{ s }}</span>
          </div>
          <div class="flex flex-col mr-4 min-w-[120px]">
            <span class="text-xs uppercase font-bold" :class="step >= s ? 'text-gray-800' : 'text-gray-400'">
              <span v-if="s === 1">Prezentacja</span>
              <span v-else-if="s === 2">Model</span>
              <span v-else-if="s === 3">Dane</span>
              <span v-else-if="s === 4">Kalkulacja</span>
              <span v-else>Finalizacja</span>
            </span>
            <span class="text-[10px] text-gray-400 hidden sm:block">
              <span v-if="s === 1">Wstęp</span>
              <span v-else-if="s === 2">Zasady</span>
              <span v-else-if="s === 3">Input</span>
              <span v-else-if="s === 4">Output</span>
              <span v-else>Zamknięcie</span>
            </span>
          </div>
        </div>
        <div v-if="s < 5" class="h-8 w-px bg-gray-300 mx-2 transform skew-x-[-20deg]"></div>
      </div>
    </div>

    <div class="flex flex-1 overflow-hidden gap-4">
      <div class="flex-1 bg-white shadow-sm border border-gray-200 rounded flex flex-col">
        <div class="p-6 border-b border-gray-100 bg-gray-50 flex justify-between">
          <h2 class="text-lg font-bold text-gray-800">
            <span v-if="step === 1">Kim jesteśmy?</span>
            <span v-else-if="step === 2">Model Stratton Prime</span>
            <span v-else-if="step === 3">Dane do kalkulacji</span>
            <span v-else-if="step === 4">Wynik symulacji</span>
            <span v-else>Podsumowanie</span>
          </h2>
          <div class="text-xs text-gray-400">Klient: {{ client?.name }}</div>
        </div>

        <div class="flex-1 p-8 overflow-y-auto">
          <div v-if="step === 1" class="text-center space-y-8 animate-fade-in max-w-3xl mx-auto">
            <h1 class="text-3xl font-light text-gray-900">Witaj w Stratton Prime</h1>
            <p class="text-gray-600">Optymalizacja kosztów pracy przez innowacyjne modele benefitowe.</p>
          </div>

          <div v-else-if="step === 2" class="space-y-6 max-w-3xl mx-auto">
            <h3 class="text-xl font-semibold text-gray-800">Model Tokenizacji Świadczeń</h3>
            <p class="text-gray-600 leading-relaxed">
              Wykorzystujemy tokenizację świadczeń pracowniczych, co pozwala na zmniejszenie podstawy opodatkowania przy zachowaniu kwoty netto dla pracownika.
            </p>
          </div>

          <div v-else-if="step === 3" class="max-w-2xl mx-auto">
            <h3 class="text-lg font-semibold text-gray-800 mb-6">Wprowadź dane kadrowe</h3>
            <div class="bg-gray-50 p-6 rounded border border-gray-200 grid gap-6">
              <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Liczba pracowników UoP</label>
                <input v-model.number="formData.employeesUop" type="number" class="block w-full border border-gray-300 rounded p-2 focus:ring-brand-main focus:border-brand-main" />
              </div>
              <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Średnie wynagrodzenie brutto UoP</label>
                <input v-model.number="formData.avgWageUop" type="number" class="block w-full border border-gray-300 rounded p-2 focus:ring-brand-main focus:border-brand-main" />
              </div>
              <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Liczba zleceniobiorców (UZ)</label>
                <input v-model.number="formData.employeesUz" type="number" class="block w-full border border-gray-300 rounded p-2 focus:ring-brand-main focus:border-brand-main" />
              </div>
            </div>
          </div>

          <div v-else-if="step === 4" class="text-center space-y-8 max-w-3xl mx-auto">
            <h3 class="text-xl font-light text-gray-600">Potencjał Oszczędności</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-8">
              <div class="bg-white p-6 rounded border border-gray-200 shadow-sm relative overflow-hidden">
                <div class="absolute top-0 left-0 w-1 h-full bg-brand-main"></div>
                <div class="text-xs text-gray-500 uppercase tracking-wide font-bold mb-1">Miesięcznie</div>
                <div class="text-3xl font-bold text-brand-main">{{ Math.round(savingsMonthly).toLocaleString() }} PLN</div>
              </div>
              <div class="bg-white p-6 rounded border border-gray-200 shadow-sm relative overflow-hidden">
                <div class="absolute top-0 left-0 w-1 h-full bg-green-500"></div>
                <div class="text-xs text-gray-500 uppercase tracking-wide font-bold mb-1">Rocznie</div>
                <div class="text-3xl font-bold text-green-600">{{ Math.round(savingsMonthly * 12).toLocaleString() }} PLN</div>
              </div>
            </div>
            <div class="flex justify-center mt-8">
              <button type="button" class="bg-gray-100 text-gray-700 px-4 py-2 rounded text-sm font-semibold hover:bg-gray-200 border border-gray-300" @click="saveVersion">
                Zapisz wariant
              </button>
            </div>
          </div>

          <div v-else class="space-y-8 text-center max-w-lg mx-auto mt-10">
            <div class="w-16 h-16 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto text-3xl">✓</div>
            <div>
              <h2 class="text-xl font-bold text-gray-900">Proces zakończony</h2>
              <p class="text-gray-500 mt-2">Dane zostały zapisane. Wybierz kolejny krok.</p>
            </div>
            <div class="space-y-3">
              <button type="button" class="w-full px-4 py-3 border border-transparent text-sm font-medium rounded text-white bg-brand-main hover:bg-blue-700 shadow-sm" @click="generateContract">
                Generuj Umowę
              </button>
              <button type="button" class="w-full px-4 py-3 border border-gray-300 text-sm font-medium rounded text-gray-700 bg-white hover:bg-gray-50" @click="finish">
                Wróć do listy klientów
              </button>
            </div>
          </div>
        </div>

        <div class="p-4 border-t border-gray-200 bg-gray-50 flex justify-end space-x-3">
          <button type="button" class="px-4 py-2 bg-white border border-gray-300 rounded text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50" :disabled="step === 1" @click="prev">
            Wstecz
          </button>
          <button v-if="step < 5" type="button" class="px-4 py-2 bg-brand-main text-white rounded text-sm hover:bg-blue-700 font-semibold shadow-sm" @click="next">
            Dalej
          </button>
        </div>
      </div>

      <div class="w-72 bg-white border border-gray-200 rounded hidden lg:flex flex-col">
        <div class="p-3 bg-gray-100 border-b border-gray-200 font-bold text-xs text-gray-600 uppercase">Historia Wersji</div>
        <div class="flex-1 overflow-y-auto p-2 space-y-2">
          <div v-for="offer in history" :key="offer.id" class="p-3 border border-gray-100 rounded bg-white hover:border-brand-main cursor-pointer shadow-sm group" @click="loadVersion(offer)">
            <div class="flex justify-between items-center mb-1">
              <span class="text-xs font-bold text-gray-800 group-hover:text-brand-main">{{ offer.name }}</span>
            </div>
            <div class="text-xs text-gray-500 mb-1">{{ new Date(offer.date).toLocaleDateString() }}</div>
            <div class="text-sm font-bold text-green-600">+{{ Math.round(offer.estimatedSavings).toLocaleString() }}</div>
          </div>
          <p v-if="history.length === 0" class="text-xs text-gray-400 text-center mt-4">Brak zapisanych wersji.</p>
        </div>
      </div>
    </div>
  </div>
</template>
