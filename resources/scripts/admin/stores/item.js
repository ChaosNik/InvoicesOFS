import axios from 'axios'
import { defineStore } from 'pinia'
import { useNotificationStore } from '@/scripts/stores/notification'
import { handleError } from '@/scripts/helpers/error-handling'
import { useCompanyStore } from './company'
import {
  buildScopedListCacheKey,
  paginateCachedRecords,
  sortCachedRecords,
} from '@/scripts/helpers/list-cache'

export const useItemStore = (useWindow = false) => {
  const defineStoreFunc = useWindow ? window.pinia.defineStore : defineStore
  const { global } = window.i18n

  return defineStoreFunc({
    id: 'item',
    state: () => ({
      items: [],
      allItemsCache: [],
      allItemsCacheLoaded: false,
      cachedCompanyId: null,
      listResponseCache: {},
      totalItems: 0,
      selectAllField: false,
      selectedItems: [],
      itemUnits: [],
      itemUnitsLoaded: false,
      currentItemUnit: {
        id: null,
        name: '',
      },
      currentItem: {
        name: '',
        item_code: '',
        description: '',
        price: 0,
        ofs_gtin: '',
        unit_id: '',
        unit: null,
        taxes: [],
        tax_per_item: false,
      },
    }),
    getters: {
      isItemUnitEdit: (state) => (state.currentItemUnit.id ? true : false),
    },
    actions: {
      resetCurrentItem() {
        this.currentItem = {
          name: '',
          item_code: '',
          description: '',
          price: 0,
          ofs_gtin: '',
          unit_id: '',
          unit: null,
          taxes: [],
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
        this.items = []
        this.totalItems = 0
        this.allItemsCache = []
        this.allItemsCacheLoaded = false
        this.listResponseCache = {}
        this.itemUnits = []
        this.itemUnitsLoaded = false

        return companyId
      },

      invalidateItemCaches() {
        this.allItemsCache = []
        this.allItemsCacheLoaded = false
        this.listResponseCache = {}
      },

      fetchItems(params) {
        return new Promise((resolve, reject) => {
          const companyId = this.ensureCacheScope()
          const requestParams = { ...(params || {}) }
          delete requestParams.background

          const isSearchRequest =
            Object.prototype.hasOwnProperty.call(requestParams, 'search') &&
            !requestParams.page
          const isAllLimit =
            !requestParams.limit || requestParams.limit === 'all'
          const isMenuRequest =
            !requestParams.page &&
            isAllLimit &&
            (!requestParams.filter ||
              Object.keys(requestParams.filter).length === 0) &&
            !requestParams.orderByField &&
            !requestParams.orderBy
          const canServeFromAllCache =
            this.allItemsCacheLoaded &&
            (!requestParams.filter ||
              Object.keys(requestParams.filter).length === 0)

          if (isMenuRequest && !requestParams.search) {
            requestParams.limit = 'all'
          }

          if (
            canServeFromAllCache &&
            (isSearchRequest || isMenuRequest || requestParams.page)
          ) {
            const search = (requestParams.search || '').toString().toLowerCase()
            const unitId =
              requestParams.unit_id === '' || requestParams.unit_id === null
                ? null
                : String(requestParams.unit_id)
            const hasPriceFilter =
              requestParams.price !== '' &&
              requestParams.price !== null &&
              requestParams.price !== undefined &&
              !Number.isNaN(Number(requestParams.price))
            const price = hasPriceFilter ? Number(requestParams.price) : null
            const filteredItems = this.allItemsCache.filter((item) => {
              const matchesSearch =
                !search ||
                item.name?.toLowerCase().includes(search) ||
                item.item_code?.toLowerCase().includes(search) ||
                item.ofs_gtin?.toLowerCase().includes(search)
              const matchesUnit =
                !unitId || String(item.unit_id ?? '') === unitId
              const matchesPrice =
                price === null || Number(item.price ?? 0) === price

              return matchesSearch && matchesUnit && matchesPrice
            })
            const sortedItems = sortCachedRecords(
              filteredItems,
              requestParams.orderByField || 'created_at',
              requestParams.orderBy || 'desc'
            )
            const paginatedItems = requestParams.page
              ? paginateCachedRecords(
                  sortedItems,
                  requestParams.page,
                  requestParams.limit || 10
                )
              : {
                  data: sortedItems,
                  meta: {
                    current_page: 1,
                    last_page: 1,
                    total: sortedItems.length,
                  },
                }

            this.items = paginatedItems.data
            this.totalItems = sortedItems.length
            resolve({
              data: {
                data: paginatedItems.data,
                meta: {
                  item_total_count: sortedItems.length,
                  total: paginatedItems.meta.total,
                  current_page: paginatedItems.meta.current_page,
                  last_page: paginatedItems.meta.last_page,
                },
              },
            })
            return
          }

          const cacheKey = buildScopedListCacheKey(companyId, requestParams)
          const cachedResponse = this.listResponseCache[cacheKey]

          if (cachedResponse) {
            this.items = cachedResponse.data
            this.totalItems =
              cachedResponse.meta.item_total_count ?? cachedResponse.meta.total ?? 0
            resolve({ data: cachedResponse })
            return
          }

          axios
            .get(`/api/v1/items`, { params: requestParams })
            .then((response) => {
              this.items = response.data.data
              this.totalItems = response.data.meta.item_total_count
              this.listResponseCache[cacheKey] = response.data

              if (isMenuRequest && !requestParams.search) {
                this.allItemsCache = response.data.data
                this.allItemsCacheLoaded = true
              }

              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      fetchItem(id) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/items/${id}`)
            .then((response) => {
              if (response.data) {
                Object.assign(this.currentItem, response.data.data)
              }
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      addItem(data) {
        return new Promise((resolve, reject) => {
          axios
            .post('/api/v1/items', data)
            .then((response) => {
              const notificationStore = useNotificationStore()

              this.items.push(response.data.data)
              this.invalidateItemCaches()

              notificationStore.showNotification({
                type: 'success',
                message: global.t('items.created_message'),
              })

              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      importItems(data) {
        return new Promise((resolve, reject) => {
          axios
            .post('/api/v1/items/import', data, {
              headers: {
                'Content-Type': 'multipart/form-data',
              },
            })
            .then((response) => {
              this.invalidateItemCaches()
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      updateItem(data) {
        return new Promise((resolve, reject) => {
          axios
            .put(`/api/v1/items/${data.id}`, data)
            .then((response) => {
              if (response.data) {
                const notificationStore = useNotificationStore()

                let pos = this.items.findIndex(
                  (item) => item.id === response.data.data.id
                )

                this.items[pos] = data.item
                this.invalidateItemCaches()

                notificationStore.showNotification({
                  type: 'success',
                  message: global.t('items.updated_message'),
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

      deleteItem(id) {
        const notificationStore = useNotificationStore()

        return new Promise((resolve, reject) => {
          const payload =
            typeof id === 'object' && id !== null ? id : { ids: [id] }
          const ids = Array.isArray(payload.ids) ? payload.ids : []

          axios
            .post(`/api/v1/items/delete`, payload)
            .then((response) => {
              if (ids.length) {
                this.items = this.items.filter((item) => !ids.includes(item.id))
              }
              this.invalidateItemCaches()

              notificationStore.showNotification({
                type: 'success',
                message: global.t('items.deleted_message', 1),
              })

              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      deleteMultipleItems() {
        const notificationStore = useNotificationStore()

        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/items/delete`, { ids: this.selectedItems })
            .then((response) => {
              this.selectedItems.forEach((item) => {
                let index = this.items.findIndex(
                  (_item) => _item.id === item.id
                )
                this.items.splice(index, 1)
              })
              this.invalidateItemCaches()

              notificationStore.showNotification({
                type: 'success',
                message: global.t('items.deleted_message', 2),
              })

              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      selectItem(data) {
        this.selectedItems = data
        if (this.selectedItems.length === this.items.length) {
          this.selectAllField = true
        } else {
          this.selectAllField = false
        }
      },

      selectAllItems(data) {
        if (this.selectedItems.length === this.items.length) {
          this.selectedItems = []
          this.selectAllField = false
        } else {
          let allItemIds = this.items.map((item) => item.id)
          this.selectedItems = allItemIds
          this.selectAllField = true
        }
      },

      addItemUnit(data) {
        const notificationStore = useNotificationStore()

        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/units`, data)
            .then((response) => {
              this.ensureCacheScope()
              this.itemUnits.push(response.data.data)
              this.itemUnitsLoaded = false

              if (response.data.data) {
                notificationStore.showNotification({
                  type: 'success',
                  message: global.t(
                    'settings.customization.items.item_unit_added'
                  ),
                })
              }

              if (response.data.errors) {
                notificationStore.showNotification({
                  type: 'error',
                  message: err.response.data.errors[0],
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

      updateItemUnit(data) {
        const notificationStore = useNotificationStore()

        return new Promise((resolve, reject) => {
          axios
            .put(`/api/v1/units/${data.id}`, data)
            .then((response) => {
              this.ensureCacheScope()
              let pos = this.itemUnits.findIndex(
                (unit) => unit.id === response.data.data.id
              )

              this.itemUnits[pos] = data
              this.itemUnitsLoaded = false

              if (response.data.data) {
                notificationStore.showNotification({
                  type: 'success',
                  message: global.t(
                    'settings.customization.items.item_unit_updated'
                  ),
                })
              }

              if (response.data.errors) {
                notificationStore.showNotification({
                  type: 'error',
                  message: err.response.data.errors[0],
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

      fetchItemUnits(params) {
        return new Promise((resolve, reject) => {
          this.ensureCacheScope()
          const requestParams = { ...(params || {}) }
          delete requestParams.background

          if (this.itemUnitsLoaded && !requestParams.page) {
            const search = (requestParams.search || '').toString().toLowerCase()
            const data = search
              ? this.itemUnits.filter((unit) =>
                  unit.name?.toLowerCase().includes(search)
                )
              : this.itemUnits

            resolve({ data: { data } })
            return
          }

          axios
            .get(`/api/v1/units`, { params: requestParams })
            .then((response) => {
              this.itemUnits = response.data.data
              if (!requestParams.search) {
                this.itemUnitsLoaded = true
              }
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      fetchItemUnit(id) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/units/${id}`)
            .then((response) => {
              this.currentItemUnit = response.data.data
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      deleteItemUnit(id) {
        const notificationStore = useNotificationStore()

        return new Promise((resolve, reject) => {
          axios
            .delete(`/api/v1/units/${id}`)
            .then((response) => {
              if (!response.data.error) {
                let index = this.itemUnits.findIndex((unit) => unit.id === id)
                this.itemUnits.splice(index, 1)
                this.itemUnitsLoaded = false
              }

              if (response.data.success) {
                notificationStore.showNotification({
                  type: 'success',
                  message: global.t(
                    'settings.customization.items.deleted_message'
                  ),
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
    },
  })()
}
