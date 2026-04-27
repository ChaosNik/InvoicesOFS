<template>
  <div class="flex-1 text-sm">
    <!-- Selected Item Field  -->
    <div
      v-if="item.item_id"
      class="
        relative
        flex
        items-center
        h-10
        pl-2
        bg-gray-200
        border border-gray-200 border-solid
        rounded
      "
    >
      {{ item.name }}

      <span
        class="absolute text-gray-400 cursor-pointer top-[8px] right-[10px]"
        @click="deselectItem(index)"
      >
        <BaseIcon name="XCircleIcon" />
      </span>
    </div>

    <!-- Select Item Field -->
    <BaseMultiselect
      v-else
      v-model="itemSelect"
      :content-loading="contentLoading"
      :loading="isLoadingOptions"
      value-prop="id"
      track-by="search_text"
      :invalid="invalid"
      preserve-search
      :initial-search="itemData.name"
      label="display_name"
      :filter-results="true"
      searchable
      :options="selectableItems"
      object
      class="w-full"
      @open="loadAvailableItems(true)"
      @update:modelValue="(val) => $emit('select', val)"
      @search-change="(val) => $emit('search', val)"
    >
      <!-- Add Item Action  -->
      <template #action>
        <BaseSelectAction
          v-if="userStore.hasAbilities(abilities.CREATE_ITEM)"
          @click="openItemModal"
        >
          <BaseIcon
            name="PlusCircleIcon"
            class="h-4 mr-2 -ml-2 text-center text-primary-400"
          />
          {{ $t('general.add_new_item') }}
        </BaseSelectAction>
      </template>
    </BaseMultiselect>

    <!-- Item Description  -->
    <div class="w-full pt-1 text-xs text-light">
      <BaseTextarea
        v-model="description"
        :content-loading="contentLoading"
        :autosize="true"
        class="text-xs"
        :borderless="true"
        :placeholder="$t('estimates.item.type_item_description')"
        :invalid="invalidDescription"
      />
      <div v-if="invalidDescription">
        <span class="text-red-600">
          {{ $t('validation.description_maxlength') }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import axios from 'axios'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useModalStore } from '@/scripts/stores/modal'
import { useUserStore } from '@/scripts/admin/stores/user'
import abilities from '@/scripts/admin/stub/abilities'

const props = defineProps({
  contentLoading: {
    type: Boolean,
    default: false,
  },
  type: {
    type: String,
    default: null,
  },
  item: {
    type: Object,
    required: true,
  },
  index: {
    type: Number,
    default: 0,
  },
  invalid: {
    type: Boolean,
    required: false,
    default: false,
  },
  invalidDescription: {
    type: Boolean,
    required: false,
    default: false,
  },
  taxPerItem: {
    type: String,
    default: '',
  },
  taxes: {
    type: Array,
    default: null,
  },
  store: {
    type: Object,
    default: null,
  },
  storeProp: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['search', 'select'])

const companyStore = useCompanyStore()
const modalStore = useModalStore()
const userStore = useUserStore()

const { t } = useI18n()

const itemSelect = ref(null)
const availableItems = ref([])
const areItemsLoaded = ref(false)
const isLoadingOptions = ref(false)
let itemData = reactive({ ...props.item })
Object.assign(itemData, props.item)

const description = computed({
  get: () => props.item.description,
  set: (value) => {
    props.store[props.storeProp].items[props.index].description = value
  },
})

const selectableItems = computed(() => {
  return availableItems.value.map((item) => ({
    ...item,
    display_name: [item.item_code, item.name].filter(Boolean).join(' - '),
    search_text: [item.item_code, item.name, item.ofs_gtin].filter(Boolean).join(' '),
  }))
})

async function loadAvailableItems(force = false) {
  if (areItemsLoaded.value && !force) {
    return availableItems.value
  }

  isLoadingOptions.value = true

  try {
    const response = await axios.get('/api/v1/items', {
      params: {
        limit: 'all',
      },
      headers: companyStore.selectedCompany?.id
        ? {
            company: companyStore.selectedCompany.id,
          }
        : {},
    })

    availableItems.value = response.data.data || []
    areItemsLoaded.value = true
  } catch (error) {
    availableItems.value = []
    areItemsLoaded.value = false
  } finally {
    isLoadingOptions.value = false
  }

  return availableItems.value
}

function openItemModal() {
  modalStore.openModal({
    title: t('items.add_item'),
    componentName: 'ItemModal',
    refreshData: (val) => emit('select', val),
    data: {
      taxPerItem: props.taxPerItem,
      taxes: props.taxes,
      itemIndex: props.index,
      store: props.store,
      storeProps: props.storeProp,
    },
  })
}

function deselectItem(index) {
  props.store.deselectItem(index)
}

watch(
  () => companyStore.selectedCompany?.id,
  () => {
    availableItems.value = []
    areItemsLoaded.value = false
    loadAvailableItems(true)
  }
)

onMounted(() => {
  loadAvailableItems()
})
</script>
