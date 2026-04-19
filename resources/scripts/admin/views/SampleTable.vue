<template>
  <BasePage>
    <h1 class="mb-2">Lokalna test tabela</h1>

    <BaseTable :data="data" :columns="columns">
      <template #cell-status="{ row }">
        <span
          v-if="row.data.status === 'Aktivno'"
          class="
            inline-flex
            px-2
            text-xs
            font-semibold
            leading-5
            text-green-800
            bg-green-100
            rounded-full
          "
        >
          {{ row.data.status }}
        </span>

        <span
          v-else
          class="
            inline-flex
            px-2
            text-xs
            font-semibold
            leading-5
            text-red-800
            bg-red-100
            rounded-full
          "
        >
          {{ row.data.status }}
        </span>
      </template>

      <template #cell-actions="{ row }">
        <base-dropdown width-class="w-48" margin-class="mt-1">
          <template #activator>
            <div class="flex items-center justify-center">
              <EllipsisHorizontalIcon class="w-6 h-6 text-gray-600" />
            </div>
          </template>

          <base-dropdown-item>
            <document-text-icon
              class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
              aria-hidden="true"
            />
            Nova faktura
          </base-dropdown-item>

          <base-dropdown-item>
            <document-icon
              class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
              aria-hidden="true"
            />
            Nova profaktura
          </base-dropdown-item>

          <base-dropdown-item>
            <user-icon
              class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
              aria-hidden="true"
            />
            Novi klijent
          </base-dropdown-item>
        </base-dropdown>
      </template>
    </BaseTable>

    <h1 class="mt-8 mb-2">Udaljena test tabela</h1>

    <BaseTable :data="fetchData" :columns="columns2"> </BaseTable>
  </BasePage>
</template>

<script>
import { computed, reactive } from 'vue'
import { useItemStore } from '@/scripts/admin/stores/item'
import {
  UserIcon,
  DocumentIcon,
  DocumentTextIcon,
  EllipsisHorizontalIcon,
} from '@heroicons/vue/24/solid'

export default {
  components: {
    BaseTable,
    EllipsisHorizontalIcon,
    UserIcon,
    DocumentIcon,
    DocumentTextIcon,
  },

  setup() {
    const itemStore = useItemStore()
    const data = reactive([
      { name: 'Tom', age: 3, image: 'tom.jpg', status: 'Aktivno' },
      { name: 'Felix', age: 5, image: 'felix.jpg', status: 'Onemogućeno' },
      { name: 'Sylvester', age: 7, image: 'sylvester.jpg', status: 'Aktivno' },
    ])

    const columns = computed(() => {
      return [
        {
          key: 'name',
          label: 'Ime',
          thClass: 'extra',
          tdClass: 'font-medium text-gray-900',
        },
        { key: 'age', label: 'Starost' },
        { key: 'image', label: 'Slika' },
        { key: 'status', label: 'Status' },
        {
          key: 'actions',
          label: '',
          tdClass: 'text-right text-sm font-medium',
          sortable: false,
        },
      ]
    })

    const columns2 = computed(() => {
      return [
        {
          key: 'name',
          label: 'Naziv',
          thClass: 'extra',
          tdClass: 'font-medium text-gray-900',
        },
        { key: 'price', label: 'Cijena' },
        { key: 'created_at', label: 'Kreirano' },
        {
          key: 'actions',
          label: '',
          tdClass: 'text-right text-sm font-medium',
          sortable: false,
        },
      ]
    })

    async function fetchData({ page, sort }) {
      let data = {
        orderByField: sort.fieldName || 'created_at',
        orderBy: sort.order || 'desc',
        page,
      }

      let response = await itemStore.fetchItems(data)

      return {
        data: response.data.items.data,
        pagination: {
          totalPages: response.data.items.last_page,
          currentPage: page,
          totalCount: response.data.itemTotalCount,
          limit: 10,
        },
      }
    }

    return {
      data,
      columns,
      fetchData,
      columns2,
    }
  },
}
</script>
