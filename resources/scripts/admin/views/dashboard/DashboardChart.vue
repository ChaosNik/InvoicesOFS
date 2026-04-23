<template>
  <div>
    <div
      v-if="dashboardStore.isDashboardDataLoaded"
      class="grid grid-cols-10 mt-8 bg-white rounded shadow"
    >
      <!-- Chart -->
      <div
        class="
          grid grid-cols-1
          col-span-10
          px-4
          py-5
          lg:col-span-7
          xl:col-span-8
          sm:p-6
        "
      >
        <div class="flex justify-between mt-1 mb-4 flex-col md:flex-row">
          <div class="flex flex-col gap-3 md:flex-row md:items-center">
            <h6 class="flex items-center sw-section-title h-10">
              <BaseIcon name="ChartBarSquareIcon" class="text-primary-400 mr-1" />
              {{ $t('dashboard.monthly_chart.title') }}
            </h6>

            <div
              v-if="userStore.currentUserAccess.can_toggle_dashboard_invoice_scope"
              class="flex flex-wrap gap-2"
            >
              <BaseButton
                size="sm"
                :variant="selectedInvoiceScope === 'ofs_only' ? 'primary' : 'primary-outline'"
                @click="selectedInvoiceScope = 'ofs_only'"
              >
                {{ $t('dashboard.invoice_scope.ofs_only') }}
              </BaseButton>
              <BaseButton
                size="sm"
                :variant="selectedInvoiceScope === 'all' ? 'primary' : 'primary-outline'"
                @click="selectedInvoiceScope = 'all'"
              >
                {{ $t('dashboard.invoice_scope.all_invoices') }}
              </BaseButton>
            </div>
          </div>

          <div class="w-full my-2 md:m-0 md:w-40 h-10">
            <BaseMultiselect
              v-model="selectedYear"
              :options="years"
              :allow-empty="false"
              :show-labels="false"
              :placeholder="$t('dashboard.select_year')"
              :can-deselect="false"
            />
          </div>
        </div>

        <LineChart
          :invoices="dashboardStore.chartData.invoiceTotals"
          :expenses="dashboardStore.chartData.expenseTotals"
          :receipts="dashboardStore.chartData.receiptTotals"
          :income="dashboardStore.chartData.netIncomeTotals"
          :labels="dashboardStore.chartData.months"
          class="sm:w-full"
        />
      </div>

      <!-- Chart Labels -->
      <div
        class="
          grid grid-cols-3
          col-span-10
          text-center
          border-t border-l border-gray-200 border-solid
          lg:border-t-0 lg:text-right lg:col-span-3
          xl:col-span-2
          lg:grid-cols-1
        "
      >
        <div class="p-6">
          <span class="text-xs leading-5 lg:text-sm">
            {{ $t('dashboard.chart_info.total_sales') }}
          </span>
          <br />
          <span class="block mt-1 text-xl font-semibold leading-8 lg:text-2xl">
            <BaseFormatMoney
              :amount="dashboardStore.totalSales"
              :currency="companyStore.selectedCompanyCurrency"
            />
          </span>
        </div>
        <div class="p-6">
          <span class="text-xs leading-5 lg:text-sm">
            {{ $t('dashboard.chart_info.total_receipts') }}
          </span>
          <br />
          <span
            class="
              block
              mt-1
              text-xl
              font-semibold
              leading-8
              lg:text-2xl
              text-green-400
            "
          >
            <BaseFormatMoney
              :amount="dashboardStore.totalReceipts"
              :currency="companyStore.selectedCompanyCurrency"
            />
          </span>
        </div>
        <div class="p-6">
          <span class="text-xs leading-5 lg:text-sm">
            {{ $t('dashboard.chart_info.total_expense') }}
          </span>
          <br />
          <span
            class="
              block
              mt-1
              text-xl
              font-semibold
              leading-8
              lg:text-2xl
              text-red-400
            "
          >
            <BaseFormatMoney
              :amount="dashboardStore.totalExpenses"
              :currency="companyStore.selectedCompanyCurrency"
            />
          </span>
        </div>
        <div
          class="
            col-span-3
            p-6
            border-t border-gray-200 border-solid
            lg:col-span-1
          "
        >
          <span class="text-xs leading-5 lg:text-sm">
            {{ $t('dashboard.chart_info.net_income') }}
          </span>
          <br />
          <span
            class="
              block
              mt-1
              text-xl
              font-semibold
              leading-8
              lg:text-2xl
              text-primary-500
            "
          >
            <BaseFormatMoney
              :amount="dashboardStore.totalNetIncome"
              :currency="companyStore.selectedCompanyCurrency"
            />
          </span>
        </div>
      </div>
    </div>

    <ChartPlaceholder v-else />
  </div>
</template>

<script setup>
import { ref, watch, inject } from 'vue'
import { useDashboardStore } from '@/scripts/admin/stores/dashboard'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import LineChart from '@/scripts/admin/components/charts/LineChart.vue'
import ChartPlaceholder from './DashboardChartPlaceholder.vue'
import abilities from '@/scripts/admin/stub/abilities'
import { useUserStore } from '@/scripts/admin/stores/user'
import { useI18n } from 'vue-i18n'

const dashboardStore = useDashboardStore()
const companyStore = useCompanyStore()

const { t } = useI18n()
const utils = inject('utils')
const userStore = useUserStore()
const years = ref( [{label: t('dateRange.this_year'), value: 'This year'}, {label: t( 'dateRange.previous_year'), value:
  'Previous year'}])
const selectedYear = ref('This year')
const selectedInvoiceScope = ref(
  userStore.currentUserAccess.default_dashboard_invoice_scope || 'all'
)

watch(
  [selectedYear, selectedInvoiceScope],
  ([year]) => {
    const params = {
      invoice_scope: selectedInvoiceScope.value,
    }

    if (year === 'Previous year') {
      params.previous_year = true
    }

    loadData(params)
  },
  { immediate: true }
)

async function loadData(params) {
  if (userStore.hasAbilities(abilities.DASHBOARD)) {
    const response = await dashboardStore.loadData(params)
    selectedInvoiceScope.value = response?.data?.active_invoice_scope || selectedInvoiceScope.value
  }
}
</script>
