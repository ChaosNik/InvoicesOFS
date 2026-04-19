import axios from 'axios'
import moment from 'moment'
import Guid from 'guid'
import _ from 'lodash'
import { defineStore } from 'pinia'
import { useRoute } from 'vue-router'
import { handleError } from '@/scripts/helpers/error-handling'
import invoiceItemStub from '../stub/invoice-item'
import taxStub from '../stub/tax'
import invoiceStub from '../stub/invoice'

import { useNotificationStore } from '@/scripts/stores/notification'
import { useCustomerStore } from './customer'
import { useTaxTypeStore } from './tax-type'
import { useCompanyStore } from './company'
import { useItemStore } from './item'
import { useUserStore } from './user'
import { useNotesStore } from './note'
import { usePaymentStore } from './payment'

function normalizeYesNoSetting(value) {
  return value === true || value === 1 || value === '1' || value === 'YES'
    ? 'YES'
    : 'NO'
}

function usesItemTaxes(value) {
  return normalizeYesNoSetting(value) === 'YES'
}

function hasOfsLabel(taxType) {
  return String(taxType?.ofs_label ?? '').trim() !== ''
}

export const useInvoiceStore = (useWindow = false) => {
  const defineStoreFunc = useWindow ? window.pinia.defineStore : defineStore
  const { global } = window.i18n
  const notificationStore = useNotificationStore()

  return defineStoreFunc({
    id: 'invoice',
    state: () => ({
      templates: [],
      invoices: [],
      selectedInvoices: [],
      selectAllField: false,
      invoiceTotalCount: 0,
      showExchangeRate: false,
      isFetchingInitialSettings: false,
      isFetchingInvoice: false,

      newInvoice: {
        ...invoiceStub(),
      },
    }),

    getters: {
      getInvoice: (state) => (id) => {
        let invId = parseInt(id)
        return state.invoices.find((invoice) => invoice.id === invId)
      },

      getSubTotal() {
        return this.newInvoice.items.reduce(function (a, b) {
          return a + b['total']
        }, 0)
      },

      getNetTotal() {
        return this.getSubtotalWithDiscount - this.getTotalTax
      },

      getTotalSimpleTax() {
        return _.sumBy(this.newInvoice.taxes, function (tax) {
          if (!tax.compound_tax) {
            return tax.amount
          }
          return 0
        })
      },

      getTotalCompoundTax() {
        return _.sumBy(this.newInvoice.taxes, function (tax) {
          if (tax.compound_tax) {
            return tax.amount
          }
          return 0
        })
      },

      getTotalTax() {
        if (!usesItemTaxes(this.newInvoice.tax_per_item)) {
          return this.getTotalSimpleTax + this.getTotalCompoundTax
        }
        return _.sumBy(this.newInvoice.items, function (tax) {
          return tax.tax
        })
      },

      getSubtotalWithDiscount() {
        return this.getSubTotal - this.newInvoice.discount_val
      },

      getTotal() {
        if (this.newInvoice.tax_included) {
          return this.getSubtotalWithDiscount
        }
        return this.getSubtotalWithDiscount + this.getTotalTax
      },

      isEdit: (state) => (state.newInvoice.id ? true : false),
    },

    actions: {
      resetCurrentInvoice() {
        this.newInvoice = {
          ...invoiceStub(),
        }
      },

      previewInvoice(params) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/invoices/${params.id}/send/preview`, { params })
            .then((response) => {
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      fetchInvoices(params) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/invoices`, { params })
            .then((response) => {
              this.invoices = response.data.data
              this.invoiceTotalCount = response.data.meta.invoice_total_count
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      fetchInvoice(id) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/invoices/${id}`)
            .then((response) => {
              this.setInvoiceData(response.data.data)
              this.setCustomerAddresses(this.newInvoice.customer)
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      setInvoiceData(invoice) {
        Object.assign(this.newInvoice, invoice)
        this.newInvoice.tax_per_item = normalizeYesNoSetting(
          this.newInvoice.tax_per_item
        )
        this.newInvoice.discount_per_item = normalizeYesNoSetting(
          this.newInvoice.discount_per_item
        )

        if (usesItemTaxes(this.newInvoice.tax_per_item)) {
          this.newInvoice.items.forEach((_i) => {
            if (_i.taxes && !_i.taxes.length)
              _i.taxes.push({ ...taxStub, id: Guid.raw() })
          })
        }

        if (this.newInvoice.discount_per_item === 'YES') {
          this.newInvoice.items.forEach((_i, index) => {
            if (_i.discount_type === 'fixed')
              this.newInvoice.items[index].discount = _i.discount / 100
          })
        } else {
          if (this.newInvoice.discount_type === 'fixed')
            this.newInvoice.discount = this.newInvoice.discount / 100
        }
      },

      setCreditNoteData(sourceInvoice) {
        const invoiceNumber = this.newInvoice.invoice_number
        const templateName =
          this.newInvoice.template_name || sourceInvoice.template_name
        const invoiceDate = this.newInvoice.invoice_date
        const dueDate = this.newInvoice.due_date
        const fiscalPaymentMethodId =
          sourceInvoice.fiscal_payment_method_id ||
          this.newInvoice.fiscal_payment_method_id

        this.newInvoice = {
          ...invoiceStub(),
          document_type: 'credit_note',
          original_invoice_id: sourceInvoice.id,
          referent_document_number: sourceInvoice.fiscal_invoice_number,
          referent_document_dt: sourceInvoice.fiscalized_at,
          credit_note_reason: '',
          invoice_number: invoiceNumber,
          template_name: templateName,
          invoice_date: invoiceDate,
          due_date: dueDate,
          customer: sourceInvoice.customer,
          customer_id: sourceInvoice.customer_id,
          selectedCurrency: sourceInvoice.customer?.currency,
          fiscal_payment_method_id: fiscalPaymentMethodId,
          tax_per_item: normalizeYesNoSetting(sourceInvoice.tax_per_item),
          tax_included: sourceInvoice.tax_included,
          sales_tax_type: sourceInvoice.sales_tax_type,
          sales_tax_address_type: sourceInvoice.sales_tax_address_type,
          discount_per_item: normalizeYesNoSetting(
            sourceInvoice.discount_per_item
          ),
          discount_type: sourceInvoice.discount_type,
          discount:
            sourceInvoice.discount_type === 'fixed'
              ? sourceInvoice.discount / 100
              : sourceInvoice.discount,
          discount_val: sourceInvoice.discount_val,
          taxes: (sourceInvoice.taxes || []).map((tax) => ({
            ...tax,
            id: Guid.raw(),
          })),
          items: (sourceInvoice.items || []).map((item) => ({
            ...item,
            id: Guid.raw(),
            invoice_id: null,
            discount:
              item.discount_type === 'fixed' ? item.discount / 100 : item.discount,
            taxes: (item.taxes || []).map((tax) => ({
              ...tax,
              id: Guid.raw(),
            })),
          })),
          customFields: [],
          fields: [],
        }

        this.setCustomerAddresses(sourceInvoice.customer)
      },

      setCustomerAddresses(customer) {
        const customer_business = customer.customer_business

        if (customer_business?.billing_address)
          this.newInvoice.customer.billing_address =
            customer_business.billing_address

        if (customer_business?.shipping_address)
          this.newInvoice.customer.shipping_address =
            customer_business.shipping_address
      },

      addSalesTaxUs() {
        const taxTypeStore = useTaxTypeStore()
        let salesTax = { ...taxStub }
        let found = this.newInvoice.taxes.find(
          (_t) => _t.name === 'Sales Tax' && _t.type === 'MODULE',
        )
        if (found) {
          for (const key in found) {
            if (Object.prototype.hasOwnProperty.call(salesTax, key)) {
              salesTax[key] = found[key]
            }
          }
          salesTax.id = found.tax_type_id
          taxTypeStore.taxTypes.push(salesTax)
        }
      },

      sendInvoice(data) {
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/invoices/${data.id}/send`, data)
            .then((response) => {
              notificationStore.showNotification({
                type: 'success',
                message: global.t('invoices.invoice_sent_successfully'),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      addInvoice(data) {
        return new Promise((resolve, reject) => {
          axios
            .post('/api/v1/invoices', data)
            .then((response) => {
              this.invoices = [...this.invoices, response.data.invoice]

              notificationStore.showNotification({
                type: 'success',
                message:
                  data.document_type === 'credit_note'
                    ? global.t('invoices.credit_note_created_message')
                    : global.t('invoices.created_message'),
              })

              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      deleteInvoice(id) {
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/invoices/delete`, id)
            .then((response) => {
              let index = this.invoices.findIndex(
                (invoice) => invoice.id === id,
              )
              this.invoices.splice(index, 1)

              notificationStore.showNotification({
                type: 'success',
                message: global.t('invoices.deleted_message', 1),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      deleteMultipleInvoices(id) {
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/invoices/delete`, { ids: this.selectedInvoices })
            .then((response) => {
              this.selectedInvoices.forEach((invoice) => {
                let index = this.invoices.findIndex(
                  (_inv) => _inv.id === invoice.id,
                )
                this.invoices.splice(index, 1)
              })
              this.selectedInvoices = []

              notificationStore.showNotification({
                type: 'success',
                message: global.t('invoices.deleted_message', 2),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      updateInvoice(data) {
        return new Promise((resolve, reject) => {
          axios
            .put(`/api/v1/invoices/${data.id}`, data)
            .then((response) => {
              let pos = this.invoices.findIndex(
                (invoice) => invoice.id === response.data.data.id,
              )
              this.invoices[pos] = response.data.data

              notificationStore.showNotification({
                type: 'success',
                message: global.t('invoices.updated_message'),
              })

              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      cloneInvoice(data) {
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/invoices/${data.id}/clone`, data)
            .then((response) => {
              notificationStore.showNotification({
                type: 'success',
                message: global.t('invoices.cloned_successfully'),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      markAsSent(data) {
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/invoices/${data.id}/status`, data)
            .then((response) => {
              let pos = this.invoices.findIndex(
                (invoices) => invoices.id === data.id,
              )

              if (this.invoices[pos]) {
                this.invoices[pos].status = 'SENT'
              }

              notificationStore.showNotification({
                type: 'success',
                message: global.t('invoices.mark_as_sent_successfully'),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      getNextNumber(params, setState = false) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/next-number?key=invoice`, { params })
            .then((response) => {
              if (setState) {
                this.newInvoice.invoice_number = response.data.nextNumber
              }
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      searchInvoice(data) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/invoices?${data}`)
            .then((response) => {
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      selectInvoice(data) {
        this.selectedInvoices = data
        if (this.selectedInvoices.length === this.invoices.length) {
          this.selectAllField = true
        } else {
          this.selectAllField = false
        }
      },

      selectAllInvoices() {
        if (this.selectedInvoices.length === this.invoices.length) {
          this.selectedInvoices = []
          this.selectAllField = false
        } else {
          let allInvoiceIds = this.invoices.map((invoice) => invoice.id)
          this.selectedInvoices = allInvoiceIds
          this.selectAllField = true
        }
      },

      selectCustomer(id) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/customers/${id}`)
            .then((response) => {
              this.newInvoice.customer = response.data.data
              this.newInvoice.customer_id = response.data.data.id
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      fetchInvoiceTemplates(params) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/invoices/templates`, { params })
            .then((response) => {
              this.templates = response.data.invoiceTemplates
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      selectNote(data) {
        this.newInvoice.selectedNote = null
        this.newInvoice.selectedNote = data
      },

      setTemplate(data) {
        this.newInvoice.template_name = data
      },

      resetSelectedCustomer() {
        this.newInvoice.customer = null
        this.newInvoice.customer_id = null
      },

      ensureDefaultOfsTax(taxTypes = []) {
        if (usesItemTaxes(this.newInvoice.tax_per_item)) {
          return
        }

        const selectedTaxes = this.newInvoice.taxes.filter(
          (tax) => Number(tax.tax_type_id) > 0
        )

        if (selectedTaxes.length) {
          return
        }

        const defaultTax = taxTypes.find(hasOfsLabel)

        if (!defaultTax) {
          return
        }

        let amount = 0

        if (
          defaultTax.calculation_type === 'percentage' &&
          this.getSubtotalWithDiscount &&
          defaultTax.percent
        ) {
          amount = Math.round(
            (this.getSubtotalWithDiscount * defaultTax.percent) / 100
          )
        } else if (defaultTax.calculation_type === 'fixed') {
          amount = defaultTax.fixed_amount
        }

        this.newInvoice.taxes.push({
          ...taxStub,
          id: Guid.raw(),
          name: defaultTax.name,
          percent: defaultTax.percent,
          tax_type_id: defaultTax.id,
          amount,
          calculation_type: defaultTax.calculation_type,
          fixed_amount: defaultTax.fixed_amount,
          compound_tax: defaultTax.compound_tax,
        })
      },

      addItem() {
        this.newInvoice.items.push({
          ...invoiceItemStub,
          id: Guid.raw(),
          taxes: [{ ...taxStub, id: Guid.raw() }],
        })
      },

      updateItem(data) {
        Object.assign(this.newInvoice.items[data.index], { ...data })
      },

      removeItem(index) {
        this.newInvoice.items.splice(index, 1)
      },

      deselectItem(index) {
        this.newInvoice.items[index] = {
          ...invoiceItemStub,
          id: Guid.raw(),
          taxes: [{ ...taxStub, id: Guid.raw() }],
        }
      },

      resetSelectedNote() {
        this.newInvoice.selectedNote = null
      },

      // On Load actions
      async fetchInvoiceInitialSettings(isEdit) {
        const companyStore = useCompanyStore()
        const customerStore = useCustomerStore()
        const itemStore = useItemStore()
        const paymentStore = usePaymentStore()
        const taxTypeStore = useTaxTypeStore()
        const route = useRoute()
        const userStore = useUserStore()
        const notesStore = useNotesStore()
        const isCreditNote = route.name === 'invoices.credit-note'

        this.isFetchingInitialSettings = true

        try {
          this.newInvoice.selectedCurrency = companyStore.selectedCompanyCurrency

          if (route.query.customer && !isCreditNote) {
            let response = await customerStore.fetchCustomer(route.query.customer)
            this.newInvoice.customer = response.data.data
            this.newInvoice.customer_id = response.data.data.id
          }

          const editInvoiceRequest = isEdit
            ? this.fetchInvoice(route.params.id)
            : Promise.resolve(null)

          if (!isEdit) {
            await notesStore.fetchNotes()
            this.newInvoice.notes =
              notesStore.getDefaultNoteForType('Invoice')?.notes
            this.newInvoice.tax_per_item = normalizeYesNoSetting(
              companyStore.selectedCompanySettings.tax_per_item
            )
            this.newInvoice.sales_tax_type =
              companyStore.selectedCompanySettings.sales_tax_type
            this.newInvoice.sales_tax_address_type =
              companyStore.selectedCompanySettings.sales_tax_address_type
            this.newInvoice.discount_per_item = normalizeYesNoSetting(
              companyStore.selectedCompanySettings.discount_per_item
            )

            let dateFormat = 'YYYY-MM-DD'
            if (companyStore.selectedCompanySettings.invoice_use_time === 'YES') {
              dateFormat += ' HH:mm'
            }

            this.newInvoice.invoice_date = moment().format(dateFormat)
            if (
              companyStore.selectedCompanySettings
                .invoice_set_due_date_automatically === 'YES'
            ) {
              this.newInvoice.due_date = moment()
                .add(
                  companyStore.selectedCompanySettings.invoice_due_date_days,
                  'days',
                )
                .format('YYYY-MM-DD')
            }
          }

          const sourceInvoiceRequest = isCreditNote
            ? axios.get(`/api/v1/invoices/${route.params.id}`)
            : Promise.resolve(null)

          const [res1, res2, res3, res4, res5, res6, res7, sourceInvoiceResponse] = await Promise.all([
            itemStore.fetchItems({
              filter: {},
              orderByField: '',
              orderBy: '',
            }),
            this.resetSelectedNote(),
            this.fetchInvoiceTemplates(),
            this.getNextNumber(),
            taxTypeStore.fetchTaxTypes({ limit: 'all' }),
            paymentStore.fetchPaymentModes({ limit: 'all' }),
            editInvoiceRequest,
            sourceInvoiceRequest,
          ])

          if (!isEdit) {
            if (res4.data) {
              this.newInvoice.invoice_number = res4.data.nextNumber
            }

            if (res3.data && this.templates[0]?.name) {
              const defaultTemplate =
                userStore.currentUserSettings.default_invoice_template ||
                'invoice1'
              const templateExists = this.templates.some(
                (template) => template.name === defaultTemplate
              )

              this.setTemplate(
                templateExists ? defaultTemplate : this.templates[0].name
              )
            }

            const fiscalPaymentMode = paymentStore.paymentModes.find(
              (mode) => mode.ofs_payment_type
            )
            this.newInvoice.fiscal_payment_method_id =
              fiscalPaymentMode?.id ?? null

            if (isCreditNote && sourceInvoiceResponse?.data?.data) {
              this.setCreditNoteData(sourceInvoiceResponse.data.data)
            } else {
              this.ensureDefaultOfsTax(taxTypeStore.taxTypes)
            }
          }
          if (isEdit) {
            this.addSalesTaxUs()
          }
        } catch (err) {
          handleError(err)
        } finally {
          this.isFetchingInitialSettings = false
        }
      },
    },
  })()
}
