<template>
  <tr class="box-border bg-white border border-gray-200 border-solid rounded-b">
    <td colspan="5" class="p-0 text-left align-top">
      <table class="w-full">
        <colgroup>
          <col style="width: 40%; min-width: 280px" />
          <col style="width: 10%; min-width: 120px" />
          <col style="width: 15%; min-width: 120px" />
          <col
            v-if="store[storeProp].discount_per_item === 'YES'"
            style="width: 15%; min-width: 160px"
          />
          <col style="width: 15%; min-width: 120px" />
        </colgroup>
        <tbody>
          <tr>
            <td class="px-5 py-4 text-left align-top">
              <div class="flex justify-start">
                <div
                  class="flex items-center justify-center w-5 h-5 mt-2 mr-2 text-gray-300 cursor-move  handle"
                >
                  <DragIcon />
                </div>
                <BaseItemSelect
                  type="Invoice"
                  :item="itemData"
                  :invalid="v$.name.$error"
                  :invalid-description="v$.description.$error"
                  :taxes="itemData.taxes"
                  :index="index"
                  :store-prop="storeProp"
                  :store="store"
                  @search="searchVal"
                  @select="onSelectItem"
                />
              </div>
              <BaseInputGroup
                class="mt-3 ml-7"
                :label="$t('items.ofs_gtin')"
                :error="v$.ofs_gtin.$error && v$.ofs_gtin.$errors[0].$message"
              >
                <BaseMultiselect
                  v-model="ofsGtinSelect"
                  :invalid="v$.ofs_gtin.$error"
                  :content-loading="loading"
                  :options="searchItemsByOfsGtin"
                  :filter-results="false"
                  :delay="300"
                  :min-chars="0"
                  :can-clear="false"
                  :can-deselect="false"
                  :create-tag="true"
                  :placeholder="$t('items.ofs_gtin')"
                  value-prop="ofs_gtin"
                  label="ofs_gtin"
                  track-by="ofs_gtin"
                  object
                  resolve-on-load
                  searchable
                  @search-change="onOfsGtinSearchChange"
                  @close="selectItemByOfsGtin"
                >
                  <template #option="{ option }">
                    <div class="flex flex-col">
                      <span class="text-sm font-medium text-gray-900">
                        {{ option.ofs_gtin }}
                      </span>
                      <span
                        v-if="option.id"
                        class="mt-0.5 text-xs text-gray-500"
                      >
                        {{ option.name }}
                      </span>
                      <span v-else class="mt-0.5 text-xs text-gray-500">
                        {{ $t('general.use') }} {{ option.ofs_gtin }}
                      </span>
                    </div>
                  </template>
                </BaseMultiselect>
              </BaseInputGroup>
            </td>
            <td class="px-5 py-4 text-right align-top">
              <BaseInput
                v-model="quantity"
                :invalid="v$.quantity.$error"
                :content-loading="loading"
                type="number"
                small
                step="any"
                @change="syncItemToStore()"
                @input="v$.quantity.$touch()"
              />
            </td>
            <td class="px-5 py-4 text-left align-top">
              <div class="flex flex-col">
                <div class="flex-auto flex-fill bd-highlight">
                  <div class="relative w-full">
                    <BaseMoney
                      :key="selectedCurrency"
                      v-model="price"
                      :invalid="v$.price.$error"
                      :content-loading="loading"
                      :currency="selectedCurrency"
                    />
                  </div>
                </div>
              </div>
            </td>
            <td
              v-if="store[storeProp].discount_per_item === 'YES'"
              class="px-5 py-4 text-left align-top"
            >
              <div class="flex flex-col">
                <div class="flex" style="width: 120px" role="group">
                  <BaseInput
                    v-model="discount"
                    :invalid="v$.discount_val.$error"
                    :content-loading="loading"
                    class="
                      border-r-0
                      focus:border-r-2
                      rounded-tr-sm rounded-br-sm
                      h-[38px]
                    "
                  />
                  <BaseDropdown position="bottom-end">
                    <template #activator>
                      <BaseButton
                        :content-loading="loading"
                        class="rounded-tr-md rounded-br-md !p-2 rounded-none"
                        type="button"
                        variant="white"
                      >
                        <span class="flex items-center">
                          {{
                            itemData.discount_type == 'fixed'
                              ? currency.symbol
                              : '%'
                          }}

                          <BaseIcon
                            name="ChevronDownIcon"
                            class="w-4 h-4 ml-1 text-gray-500"
                          />
                        </span>
                      </BaseButton>
                    </template>

                    <BaseDropdownItem @click="selectFixed">
                      {{ $t('general.fixed') }}
                    </BaseDropdownItem>

                    <BaseDropdownItem @click="selectPercentage">
                      {{ $t('general.percentage') }}
                    </BaseDropdownItem>
                  </BaseDropdown>
                </div>
              </div>
            </td>
            <td class="px-5 py-4 text-right align-top">
              <div class="flex items-center justify-end text-sm">
                <span>
                  <BaseContentPlaceholders v-if="loading">
                    <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
                  </BaseContentPlaceholders>

                  <BaseFormatMoney
                    v-else
                    :amount="total"
                    :currency="selectedCurrency"
                  />
                </span>
                <div class="flex items-center justify-center w-6 h-10 mx-2">
                  <BaseIcon
                    v-if="showRemoveButton"
                    class="h-5 text-gray-700 cursor-pointer"
                    name="TrashIcon"
                    @click="store.removeItem(index)"
                  />
                </div>
              </div>
            </td>
          </tr>
          <tr v-if="store[storeProp].tax_per_item === 'YES'">
            <td class="px-5 py-4 text-left align-top" />
            <td colspan="4" class="px-5 py-4 text-left align-top">
              <BaseContentPlaceholders v-if="loading">
                <BaseContentPlaceholdersText
                  :lines="1"
                  class="w-24 h-8 border rounded-md"
                />
              </BaseContentPlaceholders>

              <ItemTax
                v-for="(tax, index1) in itemData.taxes"
                v-else
                :key="tax.id"
                :index="index1"
                :item-index="index"
                :tax-data="tax"
                :taxes="itemData.taxes"
                :discounted-total="total"
                :total-tax="totalSimpleTax"
                :total="subtotal"
                :currency="currency"
                :update-items="syncItemToStore"
                :ability="abilities.CREATE_INVOICE"
                :store="store"
                :store-prop="storeProp"
                :discount="discount"
                @update="updateTax"
              />
            </td>
          </tr>
        </tbody>
      </table>
    </td>
  </tr>
</template>

<script setup>
import { computed, ref, inject } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import Guid from 'guid'
import TaxStub from '@/scripts/admin/stub/tax'
import ItemTax from './CreateItemRowTax.vue'
import { sumBy } from 'lodash'
import abilities from '@/scripts/admin/stub/abilities'
import {
  required,
  between,
  maxLength,
  helpers,
  minLength,
  minValue,
} from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useItemStore } from '@/scripts/admin/stores/item'
import DragIcon from '@/scripts/components/icons/DragIcon.vue'

const props = defineProps({
  store: {
    type: Object,
    default: null,
  },
  storeProp: {
    type: String,
    default: '',
  },
  itemData: {
    type: Object,
    default: null,
  },
  index: {
    type: Number,
    default: null,
  },
  type: {
    type: String,
    default: '',
  },
  loading: {
    type: Boolean,
    default: false,
  },
  currency: {
    type: [Object, String],
    required: true,
  },
  invoiceItems: {
    type: Array,
    required: true,
  },
  itemValidationScope: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['update', 'remove', 'itemValidate'])

const companyStore = useCompanyStore()
const itemStore = useItemStore()

let route = useRoute()
const { t } = useI18n()

const quantity = computed({
  get: () => {
    return props.itemData.quantity
  },
  set: (newValue) => {
    updateItemAttribute('quantity', parseFloat(newValue))
  },
})

const price = computed({
  get: () => {
    const price = props.itemData.price
    return price / 100
  },

  set: (newValue) => {
    let price = Math.round(newValue * 100)
    updateItemAttribute('price', price)
    setDiscount()
  },
})

const ofsGtinSelect = computed({
  get: () => {
    if (!props.itemData.ofs_gtin) {
      return null
    }

    return {
      id: props.itemData.item_id,
      name: props.itemData.name,
      ofs_gtin: props.itemData.ofs_gtin,
    }
  },
  set: (value) => {
    if (!value) {
      ofsGtinSearch.value = ''
      updateItemAttribute('ofs_gtin', '')
      return
    }

    ofsGtinSearch.value = String(value.ofs_gtin ?? value).trim()

    if (value.id) {
      onSelectItem(value)
      return
    }

    updateItemAttribute('ofs_gtin', value.ofs_gtin ?? value)
  },
})

const ofsGtinSearch = ref('')

const subtotal = computed(() => Math.round(props.itemData.price * props.itemData.quantity))

const discount = computed({
  get: () => {
    return props.itemData.discount
  },
  set: (newValue) => {
    updateItemAttribute('discount', newValue)
    setDiscount()
  },
})

const total = computed(() => {
  return subtotal.value - props.itemData.discount_val
})

const selectedCurrency = computed(() => {
  if (props.currency) {
    return props.currency
  } else {
    return companyStore.selectedCompanyCurrency
  }
})

const showRemoveButton = computed(() => {
  if (props.store[props.storeProp].items.length == 1) {
    return false
  }
  return true
})

const totalSimpleTax = computed(() => {
  return Math.round(
    sumBy(props.itemData.taxes, function (tax) {
      if (tax.amount) {
        return tax.amount
      }
      return 0
    })
  )
})

const totalTax = computed(() => totalSimpleTax.value)

const rules = {
  name: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  quantity: {
    required: helpers.withMessage(t('validation.required'), required),
    maxLength: helpers.withMessage(
      t('validation.amount_maxlength'),
      maxLength(20)
    ),
  },
  price: {
    required: helpers.withMessage(t('validation.required'), required),
    maxLength: helpers.withMessage(
      t('validation.price_maxlength'),
      maxLength(20)
    ),
  },
  ofs_gtin: {
    required: helpers.withMessage(t('validation.required'), required),
    minLength: helpers.withMessage(
      t('validation.ofs_gtin_min_length', { count: 8 }),
      minLength(8)
    ),
    maxLength: helpers.withMessage(
      t('validation.ofs_gtin_max_length', { count: 14 }),
      maxLength(14)
    ),
  },
  discount_val: {
    between: helpers.withMessage(
      t('validation.discount_maxlength'),
      between(
        0,
        computed(() => Math.abs(subtotal.value))
      )
    ),
  },
  description: {
    maxLength: helpers.withMessage(
      t('validation.notes_maxlength'),
      maxLength(65000)
    ),
  },
}

const v$ = useVuelidate(
  rules,
  computed(() => props.store[props.storeProp].items[props.index]),
  { $scope: props.itemValidationScope }
)

//
// if (
//   route.params.id &&
//   (props.store[props.storeProp].tax_per_item === 'YES' || 'NO')
// ) {
//   if (props.store[props.storeProp].items[props.index].taxes === undefined) {
//     props.store.$patch((state) => {
//       state[props.storeProp].items[props.index].taxes = [
//         { ...TaxStub, id: Guid.raw() },
//       ]
//     })
//   }
// }

function updateTax(data) {
  props.store.$patch((state) => {
     state[props.storeProp].items[props.index]['taxes'][data.index] = data.item
  })

  let lastTax = props.itemData.taxes[props.itemData.taxes.length - 1]

  if (lastTax?.tax_type_id !== 0) {
    props.store.$patch((state) => {
      state[props.storeProp].items[props.index].taxes.push({
        ...TaxStub,
        id: Guid.raw(),
      })
    })
  }

  syncItemToStore()
}

function setDiscount() {
  const newValue = props.store[props.storeProp].items[props.index].discount
  const absoluteSubtotal = Math.abs(subtotal.value)

  if (props.itemData.discount_type === 'percentage'){
    updateItemAttribute('discount_val', Math.round((absoluteSubtotal * newValue) / 100))
  } else {
    updateItemAttribute('discount_val', Math.min(Math.round(newValue * 100), absoluteSubtotal))
  }
}

function searchVal(val) {
  updateItemAttribute('name', val)
}

function onOfsGtinSearchChange(val) {
  if (val !== null && val !== undefined) {
    ofsGtinSearch.value = String(val).trim()
  }
}

function onSelectItem(itm) {
  props.store.$patch((state) => {
    state[props.storeProp].items[props.index].name = itm.name
    state[props.storeProp].items[props.index].price = itm.price
    state[props.storeProp].items[props.index].item_id = itm.id
    state[props.storeProp].items[props.index].ofs_gtin = itm.ofs_gtin
    state[props.storeProp].items[props.index].description = itm.description

    if (itm.unit) {
      state[props.storeProp].items[props.index].unit_name = itm.unit.name
    }

    if (props.store[props.storeProp].tax_per_item === 'YES' && itm.taxes) {
      let index = 0

      itm.taxes.forEach((tax) => {
        updateTax({ index, item: { ...tax } })
        index++
      })
    }

    if (state[props.storeProp].exchange_rate) {
      state[props.storeProp].items[props.index].price /=
        state[props.storeProp].exchange_rate
    }
  })

  itemStore.fetchItems()
  syncItemToStore()
}

async function searchItemsByOfsGtin(search) {
  const query = search?.trim()

  try {
    const response = await itemStore.fetchItems(
      query
        ? { search: query, limit: 'all' }
        : { limit: 'all' }
    )

    return response.data.data.filter((item) => item.ofs_gtin)
  } catch (err) {
    return []
  }
}

async function selectItemByOfsGtin() {
  const ofsGtin = (
    ofsGtinSearch.value ||
    props.store[props.storeProp].items[props.index].ofs_gtin ||
    ''
  ).trim()

  if (!ofsGtin) {
    return
  }

  updateItemAttribute('ofs_gtin', ofsGtin)

  if (!ofsGtin || ofsGtin.length < 8 || ofsGtin.length > 14) {
    return
  }

  if (
    props.itemData.item_id &&
    props.itemData.ofs_gtin === ofsGtin
  ) {
    return
  }

  try {
    const response = await itemStore.fetchItems({
      ofs_gtin: ofsGtin,
      limit: 'all',
    })

    const item = response.data.data.find(
      (item) => item.ofs_gtin === ofsGtin
    )

    if (item) {
      onSelectItem(item)
    }
  } catch (err) {
    return
  }
}

function selectFixed() {
  if (props.itemData.discount_type === 'fixed') {
    return
  }

  updateItemAttribute('discount_val', Math.round(props.itemData.discount * 100))
  updateItemAttribute('discount_type', 'fixed')
}

function selectPercentage() {
  if (props.itemData.discount_type === 'percentage') {
    return
  }

  updateItemAttribute(
    'discount_val',
    (subtotal.value * props.itemData.discount) / 100
  )

  updateItemAttribute('discount_type', 'percentage')
}

function syncItemToStore() {
  let itemTaxes = props.store[props.storeProp]?.items[props.index]?.taxes

  if (!itemTaxes) {
    itemTaxes = []
  }

  let data = {
    ...props.store[props.storeProp].items[props.index],
    index: props.index,
    total: total.value,
    sub_total: subtotal.value,
    totalSimpleTax: totalSimpleTax.value,
    totalTax: totalTax.value,
    tax: totalTax.value,
    taxes: [...itemTaxes],
    tax_type_ids: itemTaxes.flatMap(_t =>
      _t.tax_type_id ? _t.tax_type_id : [],
    ),
  }

  props.store.updateItem(data)
}

function updateItemAttribute(attribute, value) {
  props.store.$patch((state) => {
    state[props.storeProp].items[props.index][attribute] = value
  })

  syncItemToStore()
}
</script>
