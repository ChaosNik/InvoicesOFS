import axios from 'axios'
import { defineStore } from 'pinia'
import { useRoute } from 'vue-router'
import { handleError } from '@/scripts/helpers/error-handling'
import { useNotificationStore } from '@/scripts/stores/notification'
import { useGlobalStore } from '@/scripts/admin/stores/global'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import addressStub from '@/scripts/admin/stub/address.js'
import customerStub from '@/scripts/admin/stub/customer'
import {
  buildScopedListCacheKey,
  paginateCachedRecords,
  sortCachedRecords,
} from '@/scripts/helpers/list-cache'

export const useCustomerStore = (useWindow = false) => {
  const defineStoreFunc = useWindow ? window.pinia.defineStore : defineStore
  const { global } = window.i18n

  return defineStoreFunc({
    id: 'customer',
    state: () => ({
      customers: [],
      allCustomersCache: [],
      allCustomersCacheLoaded: false,
      cachedCompanyId: null,
      listResponseCache: {},
      totalCustomers: 0,
      selectAllField: false,
      selectedCustomers: [],
      selectedViewCustomer: {},
      isFetchingInitialSettings: false,
      isFetchingViewData: false,
      currentCustomer: {
        ...customerStub(),
      },
      editCustomer: null
    }),

    getters: {
      isEdit: (state) => (state.currentCustomer.id ? true : false),
    },

    actions: {
      resetCurrentCustomer() {
        this.currentCustomer = {
          ...customerStub(),
        }
      },

      ensureCacheScope() {
        const companyStore = useCompanyStore()
        const companyId =
          companyStore.selectedCompany?.id || window.Ls?.get('selectedCompany') || null

        if (this.cachedCompanyId === companyId) {
          return companyId
        }

        this.cachedCompanyId = companyId
        this.allCustomersCache = []
        this.allCustomersCacheLoaded = false
        this.listResponseCache = {}
        this.customers = []
        this.totalCustomers = 0

        return companyId
      },

      invalidateCustomerCaches() {
        this.allCustomersCache = []
        this.allCustomersCacheLoaded = false
        this.listResponseCache = {}
      },

      copyAddress() {
        this.currentCustomer.shipping = {
          ...this.currentCustomer.billing,
          type: 'shipping',
        }
      },

      fetchCustomerInitialSettings(isEdit) {
        const route = useRoute()
        const globalStore = useGlobalStore()
        const companyStore = useCompanyStore()

        this.isFetchingInitialSettings = true
        let editActions = []
        if (isEdit) {
          editActions = [this.fetchCustomer(route.params.id)]
        } else {
          this.currentCustomer.currency_id =
            companyStore.selectedCompanyCurrency.id
        }

        Promise.all([
          globalStore.fetchCurrencies(),
          globalStore.fetchCountries(),
          ...editActions,
        ])
          .then(async ([res1, res2, res3]) => {
            this.isFetchingInitialSettings = false
          })
          .catch((error) => {
            handleError(error)
          })
      },

      fetchCustomers(params) {
        return new Promise((resolve, reject) => {
          const companyId = this.ensureCacheScope()
          const requestParams = { ...(params || {}) }
          delete requestParams.background

          const hasPage = !!requestParams.page
          const search = (requestParams.search || '').toString().toLowerCase()
          const displayName = (requestParams.display_name || '')
            .toString()
            .toLowerCase()
          const contactName = (requestParams.contact_name || '')
            .toString()
            .toLowerCase()
          const phone = (requestParams.phone || '').toString().toLowerCase()
          const isMenuRequest =
            !hasPage &&
            (!requestParams.filter ||
              Object.keys(requestParams.filter).length === 0) &&
            !requestParams.orderByField &&
            !requestParams.orderBy &&
            !requestParams.customer_id

          const canServeFromAllCache =
            this.allCustomersCacheLoaded &&
            (!requestParams.filter ||
              Object.keys(requestParams.filter).length === 0) &&
            !requestParams.customer_id

          if (isMenuRequest && !search && !displayName && !contactName && !phone) {
            requestParams.limit = 'all'
          }

          if (canServeFromAllCache) {
            const filteredCustomers = this.allCustomersCache.filter((customer) => {
              const matchesSearch =
                !search ||
                [
                  customer.name,
                  customer.display_name,
                  customer.contact_name,
                  customer.email,
                  customer.phone,
                ].some((value) =>
                  value?.toString().toLowerCase().includes(search)
                )
              const matchesDisplayName =
                !displayName ||
                [customer.display_name, customer.name].some((value) =>
                  value?.toString().toLowerCase().includes(displayName)
                )
              const matchesContactName =
                !contactName ||
                customer.contact_name?.toString().toLowerCase().includes(contactName)
              const matchesPhone =
                !phone || customer.phone?.toString().toLowerCase().includes(phone)

              return (
                matchesSearch &&
                matchesDisplayName &&
                matchesContactName &&
                matchesPhone
              )
            })

            const sortedCustomers = sortCachedRecords(
              filteredCustomers,
              requestParams.orderByField || 'created_at',
              requestParams.orderBy || 'desc'
            )
            const paginatedCustomers = hasPage
              ? paginateCachedRecords(
                  sortedCustomers,
                  requestParams.page,
                  requestParams.limit || 10
                )
              : {
                  data: sortedCustomers,
                  meta: {
                    current_page: 1,
                    last_page: 1,
                    total: sortedCustomers.length,
                  },
                }

            this.customers = paginatedCustomers.data
            this.totalCustomers = sortedCustomers.length
            resolve({
              data: {
                data: paginatedCustomers.data,
                meta: {
                  customer_total_count: sortedCustomers.length,
                  total: paginatedCustomers.meta.total,
                  current_page: paginatedCustomers.meta.current_page,
                  last_page: paginatedCustomers.meta.last_page,
                },
              },
            })
            return
          }

          const cacheKey = buildScopedListCacheKey(companyId, requestParams)
          const cachedResponse = this.listResponseCache[cacheKey]

          if (cachedResponse) {
            this.customers = cachedResponse.data
            this.totalCustomers =
              cachedResponse.meta.customer_total_count ?? cachedResponse.meta.total ?? 0
            resolve({ data: cachedResponse })
            return
          }

          axios
            .get(`/api/v1/customers`, { params: requestParams })
            .then((response) => {
              this.customers = response.data.data
              this.totalCustomers = response.data.meta.customer_total_count
              this.listResponseCache[cacheKey] = response.data

              if (isMenuRequest && !search && !displayName && !contactName && !phone) {
                this.allCustomersCache = response.data.data
                this.allCustomersCacheLoaded = true
              }

              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      fetchViewCustomer(params) {
        return new Promise((resolve, reject) => {
          this.isFetchingViewData = true
          axios
            .get(`/api/v1/customers/${params.id}/stats`, { params })

            .then((response) => {
              this.selectedViewCustomer = {}
              Object.assign(this.selectedViewCustomer, response.data.data)
              this.setAddressStub(response.data.data)
              this.isFetchingViewData = false
              resolve(response)
            })
            .catch((err) => {
              this.isFetchingViewData = false
              handleError(err)
              reject(err)
            })
        })
      },

      fetchCustomer(id) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/customers/${id}`)
            .then((response) => {
              Object.assign(this.currentCustomer, response.data.data)

              this.setAddressStub(response.data.data)
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      addCustomer(data) {
        return new Promise((resolve, reject) => {
          axios
            .post('/api/v1/customers', data)
            .then((response) => {
              this.customers.push(response.data.data)
              this.invalidateCustomerCaches()

              const notificationStore = useNotificationStore()
              notificationStore.showNotification({
                type: 'success',
                message: global.t('customers.created_message'),
              })
              resolve(response)
            })

            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      updateCustomer(data) {
        return new Promise((resolve, reject) => {
          axios
            .put(`/api/v1/customers/${data.id}`, data)
            .then((response) => {
              if (response.data) {
                let pos = this.customers.findIndex(
                  (customer) => customer.id === response.data.data.id
                )
                this.customers[pos] = data
                this.invalidateCustomerCaches()
                const notificationStore = useNotificationStore()
                notificationStore.showNotification({
                  type: 'success',
                  message: global.t('customers.updated_message'),
                })
              }
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      deleteCustomer(id) {
        const notificationStore = useNotificationStore()
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/customers/delete`, id)
            .then((response) => {
              let index = this.customers.findIndex(
                (customer) => customer.id === id
              )
              this.customers.splice(index, 1)
              this.invalidateCustomerCaches()
              notificationStore.showNotification({
                type: 'success',
                message: global.t('customers.deleted_message', 1),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      deleteMultipleCustomers() {
        const notificationStore = useNotificationStore()

        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/customers/delete`, { ids: this.selectedCustomers })
            .then((response) => {
              this.selectedCustomers.forEach((customer) => {
                let index = this.customers.findIndex(
                  (_customer) => _customer.id === customer.id
                )
                this.customers.splice(index, 1)
              })
              this.invalidateCustomerCaches()

              notificationStore.showNotification({
                type: 'success',
                message: global.t('customers.deleted_message', 2),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      setSelectAllState(data) {
        this.selectAllField = data
      },

      selectCustomer(data) {
        this.selectedCustomers = data
        if (this.selectedCustomers.length === this.customers.length) {
          this.selectAllField = true
        } else {
          this.selectAllField = false
        }
      },

      selectAllCustomers() {
        if (this.selectedCustomers.length === this.customers.length) {
          this.selectedCustomers = []
          this.selectAllField = false
        } else {
          let allCustomerIds = this.customers.map((customer) => customer.id)
          this.selectedCustomers = allCustomerIds
          this.selectAllField = true
        }
      },

      setAddressStub(data) {
        if (!data.billing) this.currentCustomer.billing = { ...addressStub }
        if (!data.shipping) this.currentCustomer.shipping = { ...addressStub }
      },
    },
  })()
}
