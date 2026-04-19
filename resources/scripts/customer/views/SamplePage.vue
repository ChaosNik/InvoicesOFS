<template>
  <div class="bg-blue-100 h-screen container mx-auto px-6">
    <h1 class="text-xl font-bold">Primjer stranice</h1>
    <BaseButton>Zdravo</BaseButton>
    <BaseCheckon>{{ customerStore.customers }} </BaseCheckon>
  </div>
</template>


<script setup>
import BaseCheckon from '@/scripts/customer/BaseCheckon.vue'
import { useCustomerStore } from '@/scripts/customer/stores/customer'

const customerStore = useCustomerStore()
</script>
