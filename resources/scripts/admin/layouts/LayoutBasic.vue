<template>
  <div v-if="isAppLoaded" class="h-full">
    <NotificationRoot />

    <SiteHeader />

    <SiteSidebar />

    <ExchangeRateBulkUpdateModal />

    <main
      class="h-screen h-screen-ios overflow-y-auto md:pl-56 xl:pl-64 min-h-0"
    >
      <div class="pt-16 pb-16">
        <router-view />
      </div>
    </main>
  </div>

  <BaseGlobalLoader v-else />
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { useGlobalStore } from '@/scripts/admin/stores/global'
import { onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUserStore } from '@/scripts/admin/stores/user'
import { useModalStore } from '@/scripts/stores/modal'
import { useExchangeRateStore } from '@/scripts/admin/stores/exchange-rate'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useCustomerStore } from '@/scripts/admin/stores/customer'
import { useItemStore } from '@/scripts/admin/stores/item'
import { usePaymentStore } from '@/scripts/admin/stores/payment'
import { useTaxTypeStore } from '@/scripts/admin/stores/tax-type'

import SiteHeader from '@/scripts/admin/layouts/partials/TheSiteHeader.vue'
import SiteSidebar from '@/scripts/admin/layouts/partials/TheSiteSidebar.vue'
import NotificationRoot from '@/scripts/components/notifications/NotificationRoot.vue'
import ExchangeRateBulkUpdateModal from '@/scripts/admin/components/modal-components/ExchangeRateBulkUpdateModal.vue'

const globalStore = useGlobalStore()
const route = useRoute()
const userStore = useUserStore()
const router = useRouter()
const modalStore = useModalStore()
const { t } = useI18n()
const exchangeRateStore = useExchangeRateStore()
const companyStore = useCompanyStore()
const customerStore = useCustomerStore()
const itemStore = useItemStore()
const paymentStore = usePaymentStore()
const taxTypeStore = useTaxTypeStore()

globalStore.hydrateBootstrapCache()

const isAppLoaded = computed(() => {
  return globalStore.isAppLoaded
})

onMounted(() => {
  if (globalStore.isAppLoaded) {
    warmMenuData()
  }

  globalStore.bootstrap().then((res) => {
    if (route.meta.ability && !userStore.hasAbilities(route.meta.ability)) {
      router.push({ name: 'account.settings' })
    } else if (route.meta.isOwner && !userStore.currentUser.is_owner) {
      router.push({ name: 'account.settings' })
    }

    if (
      res.data.current_company_settings.bulk_exchange_rate_configured === 'NO'
    ) {
      exchangeRateStore.fetchBulkCurrencies().then((res) => {
        if (res.data.currencies.length) {
          modalStore.openModal({
            componentName: 'ExchangeRateBulkUpdateModal',
            size: 'sm',
          })
        } else {
          let data = {
            settings: {
              bulk_exchange_rate_configured: 'YES',
            },
          }
          companyStore.updateCompanySettings({
            data,
          })
        }
      })
    }

    warmMenuData()
  })
})

function warmMenuData() {
  window.setTimeout(() => {
    itemStore.fetchItems({
      limit: 'all',
      filter: {},
      orderByField: '',
      orderBy: '',
      background: true,
    }).catch(() => {})
    itemStore.fetchItemUnits({ limit: 'all', background: true }).catch(() => {})
    taxTypeStore.fetchTaxTypes({ limit: 'all', background: true }).catch(() => {})
    paymentStore.fetchPaymentModes({ limit: 'all', background: true }).catch(() => {})
    customerStore.fetchCustomers({
      limit: 'all',
      filter: {},
      orderByField: '',
      orderBy: '',
      background: true,
    }).catch(() => {})
  }, 0)
}
</script>
