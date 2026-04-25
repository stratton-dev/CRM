<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import QuickSimulation from '@/components/calculator/steps/QuickSimulation.vue';
import { useCalculatorStore } from '@/components/calculator/store/useCalculatorStore';
import { useAuthStore } from '@/stores/auth';
import { api } from '@/api/client';

const router = useRouter();
const route = useRoute();
const store = useCalculatorStore();
const auth = useAuthStore();

const meetingId = computed(() => {
  const id = Array.isArray(route.query.meetingId) ? route.query.meetingId[0] : route.query.meetingId;
  return id ? String(id) : null;
});

const clientId = computed(() => {
  const id = Array.isArray(route.query.clientId) ? route.query.clientId[0] : route.query.clientId;
  return id ? String(id) : null;
});

const initialEmployees = computed(() => {
  const value = Array.isArray(route.query.employees) ? route.query.employees[0] : route.query.employees;
  return value ? Number(value) : 50;
});

const initialAvgWage = computed(() => {
  const value = Array.isArray(route.query.avgWage) ? route.query.avgWage[0] : route.query.avgWage;
  return value ? Number(value) : 6000;
});

const initialContractType = computed(() => {
  const value = Array.isArray(route.query.contractType) ? route.query.contractType[0] : route.query.contractType;
  if (value === 'UZ') return 'UZ';
  if (value === 'Mix') return 'MIXED';
  return 'UOP';
});

// Email kontaktu klienta (decision maker lub pierwszy kontakt) — używany do adresowania oferty szacunkowej
const contactEmail = ref<string | null>(null);
const contactName = ref<string | null>(null);

store.setContext({ meetingId: meetingId.value, clientId: clientId.value, source: 'quick' });

// Ensure we start fresh when entering Quick Calculator
store.resetSession();

onMounted(async () => {
  if (auth.enabled && clientId.value) {
    try {
      const { data } = await api.get(`/v1/clients/${clientId.value}/contacts`);
      const contacts = Array.isArray(data) ? data : [];
      const dm = contacts.find((c: any) => c.is_decision_maker) || contacts[0] || null;
      if (dm) {
        contactEmail.value = dm.email ?? null;
        contactName.value = dm.name ?? null;
      }
    } catch {
      // kontakty opcjonalne — ignorujemy błąd
    }
  }
});

const goToDetails = () => {
  void router.push({
    path: '/app/calculator',
    query: {
      ...route.query,
      step: '1',
    },
  });
};
</script>

<template>
  <QuickSimulation
    :initial-employees="initialEmployees"
    :initial-avg-wage="initialAvgWage"
    :initial-contract-type="initialContractType"
    :contact-email="contactEmail"
    :contact-name="contactName"
    :client-id="clientId"
    @transfer="goToDetails"
  />
</template>

<style scoped>
@media print {
  body * {
    visibility: hidden;
  }

  .print-container,
  .print-container * {
    visibility: visible;
  }

  .print-container {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    background-color: white;
  }

  @page {
    size: A4;
    margin: 0;
  }
}
</style>
