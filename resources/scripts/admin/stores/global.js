import axios from 'axios'
import { defineStore } from 'pinia'
import { useCompanyStore } from './company'
import { useUserStore } from './user'
import { useModuleStore } from './module'
import { useNotificationStore } from '@/scripts/stores/notification'
import { handleError } from '@/scripts/helpers/error-handling'
import _ from 'lodash'

const BOOTSTRAP_CACHE_PREFIX = 'invoiceshelf.bootstrap.'
const BOOTSTRAP_CACHE_TTL = 30 * 60 * 1000

export const useGlobalStore = (useWindow = false) => {
  const defineStoreFunc = useWindow ? window.pinia.defineStore : defineStore
  const { global } = window.i18n

  return defineStoreFunc({
    id: 'global',
    state: () => ({
      // Global Configuration
      config: null,
      globalSettings: null,

      // Global Lists
      timeZones: [],
      dateFormats: [],
      timeFormats: [],
      currencies: [],
      countries: [],
      languages: [],
      fiscalYears: [],

      // Menus
      mainMenu: [],
      settingMenu: [],

      // Boolean Flags
      isAppLoaded: false,
      isBootstrapping: false,
      isHydratedFromCache: false,
      isSidebarOpen: false,
      areCurrenciesLoading: false,

      downloadReport: null,
    }),

    getters: {
      menuGroups: (state) => {
        return Object.values(_.groupBy(state.mainMenu, 'group'))
      },
    },

    actions: {
      async bootstrap({ useCache = true } = {}) {
        if (useCache) {
          this.hydrateBootstrapCache()
        }

        this.isBootstrapping = true

        try {
          const response = await axios.get('/api/v1/bootstrap')

          this.applyBootstrapPayload(response.data)
          this.writeBootstrapCache(response.data)

          return response
        } catch (err) {
          handleError(err)
          throw err
        } finally {
          this.isBootstrapping = false
        }
      },

      applyBootstrapPayload(data, { fromCache = false } = {}) {
        const companyStore = useCompanyStore()
        const userStore = useUserStore()
        const moduleStore = useModuleStore()

        this.mainMenu = data.main_menu || []
        this.settingMenu = data.setting_menu || []
        this.config = data.config || null
        this.globalSettings = data.global_settings || null

        userStore.currentUser = data.current_user || null
        userStore.currentUserSettings = data.current_user_settings || {}
        userStore.currentAbilities = data.current_user_abilities || []
        userStore.currentUserAccess = data.current_user_access || {
          invoice_access_scope: 'all',
          default_dashboard_invoice_scope: 'all',
          can_toggle_dashboard_invoice_scope: false,
          can_view_non_ofs_invoices: true,
          can_import_legacy_invoices: true,
        }

        moduleStore.apiToken = data.global_settings?.api_token
        moduleStore.enableModules = data.modules || []

        companyStore.companies = data.companies || []
        companyStore.selectedCompany = data.current_company || null
        if (data.current_company) {
          companyStore.setSelectedCompany(data.current_company)
        }
        companyStore.selectedCompanySettings =
          data.current_company_settings || {}
        companyStore.selectedCompanyCurrency =
          data.current_company_currency || null

        this.setBootstrapLanguage(data)
        this.isHydratedFromCache = fromCache
        this.isAppLoaded = true
      },

      setBootstrapLanguage(data) {
        const userLanguage = data.current_user_settings?.language
        const companyLanguage = data.current_company_settings?.language
        const targetLanguage = userLanguage || companyLanguage || 'sr'

        if (typeof global.locale !== 'string') {
          global.locale.value = targetLanguage
        } else {
          global.locale = targetLanguage
        }

        if (targetLanguage !== 'en' && window.loadLanguage) {
          window.loadLanguage(targetLanguage).catch((error) => {
            console.warn('Failed to load language during bootstrap:', error)
          })
        }
      },

      hydrateBootstrapCache() {
        if (this.isAppLoaded) {
          return true
        }

        const cached = this.readBootstrapCache()

        if (!cached) {
          return false
        }

        this.applyBootstrapPayload(cached.payload, { fromCache: true })

        return true
      },

      readBootstrapCache() {
        try {
          const raw = localStorage.getItem(this.getBootstrapCacheKey())

          if (!raw) {
            return null
          }

          const cached = JSON.parse(raw)

          if (!cached?.payload || Date.now() - cached.cachedAt > BOOTSTRAP_CACHE_TTL) {
            return null
          }

          return cached
        } catch (error) {
          return null
        }
      },

      writeBootstrapCache(payload) {
        try {
          localStorage.setItem(
            this.getBootstrapCacheKey(payload.current_company?.id),
            JSON.stringify({
              cachedAt: Date.now(),
              payload,
            })
          )
        } catch (error) {
          // Local storage is best-effort. Fresh bootstrap data is already applied.
        }
      },

      getBootstrapCacheKey(companyId = null) {
        return `${BOOTSTRAP_CACHE_PREFIX}${companyId || window.Ls?.get('selectedCompany') || 'default'}`
      },

      fetchCurrencies() {
        return new Promise((resolve, reject) => {
          if (this.currencies.length || this.areCurrenciesLoading) {
            resolve(this.currencies)
          } else {
            this.areCurrenciesLoading = true
            axios
              .get('/api/v1/currencies')
              .then((response) => {
                this.currencies = response.data.data.filter((currency) => {
                  return (currency.name = `${currency.code} - ${currency.name}`)
                })
                this.areCurrenciesLoading = false
                resolve(response)
              })
              .catch((err) => {
                handleError(err)
                this.areCurrenciesLoading = false
                reject(err)
              })
          }
        })
      },

      fetchConfig(params) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/config`, { params })
            .then((response) => {
              if (response.data.languages) {
                this.languages = response.data.languages
              } else {
                this.fiscalYears = response.data.fiscal_years
              }
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      fetchDateFormats() {
        return new Promise((resolve, reject) => {
          if (this.dateFormats.length) {
            resolve(this.dateFormats)
          } else {
            axios
              .get('/api/v1/date/formats')
              .then((response) => {
                this.dateFormats = response.data.date_formats
                resolve(response)
              })
              .catch((err) => {
                handleError(err)
                reject(err)
              })
          }
        })
      },

      fetchTimeFormats() {
        return new Promise((resolve, reject) => {
          if (this.timeFormats.length) {
            resolve(this.timeFormats)
          } else {
            axios
              .get('/api/v1/time/formats')
              .then((response) => {
                this.timeFormats = response.data.time_formats
                resolve(response)
              })
              .catch((err) => {
                handleError(err)
                reject(err)
              })
          }
        })
      },

      fetchTimeZones() {
        return new Promise((resolve, reject) => {
          if (this.timeZones.length) {
            resolve(this.timeZones)
          } else {
            axios
              .get('/api/v1/timezones')
              .then((response) => {
                this.timeZones = response.data.time_zones
                resolve(response)
              })
              .catch((err) => {
                handleError(err)
                reject(err)
              })
          }
        })
      },

      fetchCountries() {
        return new Promise((resolve, reject) => {
          if (this.countries.length) {
            resolve(this.countries)
          } else {
            axios
              .get('/api/v1/countries')
              .then((response) => {
                this.countries = response.data.data
                resolve(response)
              })
              .catch((err) => {
                handleError(err)
                reject(err)
              })
          }
        })
      },

      fetchPlaceholders(params) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/number-placeholders`, { params })
            .then((response) => {
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      setSidebarVisibility(val) {
        this.isSidebarOpen = val
      },

      setIsAppLoaded(isAppLoaded) {
        this.isAppLoaded = isAppLoaded
      },

      updateGlobalSettings({ data, message }) {
        return new Promise((resolve, reject) => {
          axios
            .post('/api/v1/settings', data)
            .then((response) => {
              Object.assign(this.globalSettings, data.settings)

              if (message) {
                const notificationStore = useNotificationStore()

                notificationStore.showNotification({
                  type: 'success',
                  message: global.t(message),
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
