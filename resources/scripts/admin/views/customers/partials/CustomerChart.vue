<template>
  <BaseCard class="flex flex-col mt-6">
    <ChartPlaceholder v-if="customerStore.isFetchingViewData" />

    <div v-else class="grid grid-cols-12">
      <div class="col-span-12 xl:col-span-9 xxl:col-span-10">
        <div class="flex justify-between mt-1 mb-6 flex-col gap-3 md:flex-row md:items-end">
          <h6 class="flex items-center h-10">
            <BaseIcon name="ChartBarSquareIcon" class="h-5 text-primary-400 mr-1" />
            {{ $t('dashboard.monthly_chart.title') }}
          </h6>

          <div class="w-full flex flex-col gap-3 md:w-auto md:flex-row md:items-end">
            <div class="w-full md:w-44 h-10">
              <BaseMultiselect
                v-model="selectedPeriod"
                :options="periodOptions"
                :allow-empty="false"
                :show-labels="false"
                :placeholder="$t('dashboard.select_period')"
                :can-deselect="false"
                label="label"
                track-by="label"
                value-prop="value"
              />
            </div>

            <div
              v-if="selectedPeriod === 'custom'"
              class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:flex md:gap-3"
            >
              <BaseInputGroup :label="$t('general.from_date')" class="w-full md:w-40">
                <BaseInput
                  v-model="customRange.from"
                  type="date"
                  :max="customRange.to || undefined"
                />
              </BaseInputGroup>

              <BaseInputGroup :label="$t('general.to_date')" class="w-full md:w-40">
                <BaseInput
                  v-model="customRange.to"
                  type="date"
                  :min="customRange.from || undefined"
                />
              </BaseInputGroup>
            </div>
          </div>
        </div>

        <LineChart
          v-if="isLoading"
          :invoices="getChartInvoices"
          :expenses="getChartExpenses"
          :receipts="getReceiptTotals"
          :income="getNetProfits"
          :labels="getChartMonths"
          class="sm:w-full"
        />
      </div>

      <div
        class="
          grid
          col-span-12
          mt-6
          text-center
          xl:mt-0
          sm:grid-cols-5
          xl:text-right xl:col-span-3 xl:grid-cols-1
          xxl:col-span-2
        "
      >
        <div class="px-6 py-2">
          <span class="text-xs leading-5 lg:text-sm">
            {{ $t('dashboard.chart_info.total_sales') }}
          </span>
          <br />
          <span
            v-if="isLoading"
            class="block mt-1 text-xl font-semibold leading-8"
          >
            <BaseFormatMoney
              :amount="chartData.salesTotal"
              :currency="companyStore.selectedCompanyCurrency"
            />
          </span>
        </div>

        <div class="px-6 py-2">
          <span class="text-xs leading-5 lg:text-sm">
            {{ $t('dashboard.chart_info.total_receipts') }}
          </span>
          <br />

          <span
            v-if="isLoading"
            class="block mt-1 text-xl font-semibold leading-8"
            style="color: #00c99c"
          >
            <BaseFormatMoney
              :amount="chartData.totalReceipts"
              :currency="companyStore.selectedCompanyCurrency"
            />
          </span>
        </div>

        <div class="px-6 py-2">
          <span class="text-xs leading-5 lg:text-sm">
            {{ $t('dashboard.chart_info.total_expense') }}
          </span>
          <br />
          <span
            v-if="isLoading"
            class="block mt-1 text-xl font-semibold leading-8"
            style="color: #fb7178"
          >
            <BaseFormatMoney
              :amount="chartData.totalExpenses"
              :currency="companyStore.selectedCompanyCurrency"
            />
          </span>
        </div>

        <div class="px-6 py-2">
          <span class="text-xs leading-5 lg:text-sm">
            {{ $t('dashboard.chart_info.net_income') }}
          </span>
          <br />
          <span
            v-if="isLoading"
            class="block mt-1 text-xl font-semibold leading-8"
            style="color: #5851d8"
          >
            <BaseFormatMoney
              :amount="chartData.netProfit"
              :currency="companyStore.selectedCompanyCurrency"
            />
          </span>
        </div>

        <div class="px-6 py-2">
          <span class="text-xs leading-5 lg:text-sm">
            {{ $t('dashboard.cards.due_amount') }}
          </span>
          <br />
          <span
            v-if="isLoading"
            class="block mt-1 text-xl font-semibold leading-8"
          >
            <BaseFormatMoney
              :amount="customerDueAmount"
              :currency="companyStore.selectedCompanyCurrency"
            />
          </span>
        </div>
      </div>
    </div>

    <CustomerInfo />
  </BaseCard>
</template>

<script setup>
import CustomerInfo from './CustomerInfo.vue'
import LineChart from '@/scripts/admin/components/charts/LineChart.vue'
import { ref, computed, watch, reactive } from 'vue'
import { useCustomerStore } from '@/scripts/admin/stores/customer'
import { useRoute } from 'vue-router'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import ChartPlaceholder from './CustomerChartPlaceholder.vue'
import { useI18n } from 'vue-i18n'

const companyStore = useCompanyStore()
const customerStore = useCustomerStore()
const { t } = useI18n()

const route = useRoute()

let isLoading = ref(false)
let chartData = reactive({})
let data = reactive({})
const periodOptions = computed(() => [
  { label: t('dateRange.this_year'), value: 'this_year' },
  { label: t('dateRange.previous_year'), value: 'previous_year' },
  { label: t('dateRange.custom'), value: 'custom' },
])
const selectedPeriod = ref('this_year')
const customRange = reactive(getDefaultCustomRange())

const getChartExpenses = computed(() => {
  if (chartData.expenseTotals) {
    return chartData.expenseTotals
  }
  return []
})

const getNetProfits = computed(() => {
  if (chartData.netProfits) {
    return chartData.netProfits
  }
  return []
})

const getChartMonths = computed(() => {
  if (chartData && chartData.months) {
    return chartData.months
  }
  return []
})

const getReceiptTotals = computed(() => {
  if (chartData.receiptTotals) {
    return chartData.receiptTotals
  }
  return []
})

const getChartInvoices = computed(() => {
  if (chartData.invoiceTotals) {
    return chartData.invoiceTotals
  }

  return []
})

const customerDueAmount = computed(() => {
  return Number(data.base_due_amount ?? data.due_amount ?? 0)
})

watch(
  route,
  () => {
    selectedPeriod.value = 'this_year'
    Object.assign(customRange, getDefaultCustomRange())

    if (route.params.id) {
      loadCustomer(buildCustomerParams())
    }
  },
  { immediate: true }
)

watch(
  [selectedPeriod, () => customRange.from, () => customRange.to],
  () => {
    if (!route.params.id) {
      return
    }

    const params = buildCustomerParams()

    if (!params) {
      return
    }

    loadCustomer(params)
  }
)

async function loadCustomer(params = { id: route.params.id }) {
  isLoading.value = false
  let response = await customerStore.fetchViewCustomer(params)

  if (response.data) {
    Object.assign(chartData, response.data.meta.chartData)
    Object.assign(data, response.data.data)
  }

  isLoading.value = true
}

function buildCustomerParams() {
  let params = {
    id: route.params.id,
    range_type: selectedPeriod.value,
  }

  if (selectedPeriod.value === 'previous_year') {
    params.previous_year = true
  }

  if (selectedPeriod.value === 'custom') {
    if (!customRange.from || !customRange.to) {
      return null
    }

    const [fromDate, toDate] =
      customRange.from <= customRange.to
        ? [customRange.from, customRange.to]
        : [customRange.to, customRange.from]

    params.from_date = fromDate
    params.to_date = toDate
  }

  return params
}

function getDefaultCustomRange() {
  const today = new Date()

  return {
    from: formatDateInput(new Date(today.getFullYear(), today.getMonth(), 1)),
    to: formatDateInput(today),
  }
}

function formatDateInput(date) {
  const year = date.getFullYear()
  const month = `${date.getMonth() + 1}`.padStart(2, '0')
  const day = `${date.getDate()}`.padStart(2, '0')

  return `${year}-${month}-${day}`
}
</script>
