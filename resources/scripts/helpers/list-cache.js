import { get } from 'lodash'

export function buildScopedListCacheKey(companyId, params = {}) {
  const normalized = {}

  Object.keys(params)
    .sort()
    .forEach((key) => {
      normalized[key] = params[key] ?? null
    })

  return `${companyId || 'default'}:${JSON.stringify(normalized)}`
}

export function sortCachedRecords(records = [], fieldName = '', order = 'asc') {
  if (!fieldName) {
    return [...records]
  }

  const direction = order === 'asc' ? 1 : -1

  return [...records].sort((left, right) => {
    const leftValue = normalizeSortValue(get(left, fieldName))
    const rightValue = normalizeSortValue(get(right, fieldName))

    if (leftValue === rightValue) {
      return 0
    }

    return leftValue > rightValue ? direction : -direction
  })
}

export function paginateCachedRecords(records = [], page = 1, limit = 10) {
  const normalizedLimit =
    limit === 'all' ? Math.max(records.length, 1) : Math.max(Number(limit) || 10, 1)
  const normalizedPage = Math.max(Number(page) || 1, 1)
  const total = records.length
  const lastPage = Math.max(Math.ceil(total / normalizedLimit), 1)
  const start = (normalizedPage - 1) * normalizedLimit
  const data =
    limit === 'all' ? [...records] : records.slice(start, start + normalizedLimit)

  return {
    data,
    meta: {
      current_page: normalizedPage,
      last_page: lastPage,
      total,
    },
  }
}

function normalizeSortValue(value) {
  if (value === null || value === undefined) {
    return ''
  }

  if (typeof value === 'number') {
    return value
  }

  if (typeof value === 'string') {
    const trimmed = value.trim()
    const numeric = Number(trimmed)

    if (trimmed !== '' && !Number.isNaN(numeric)) {
      return numeric
    }

    return trimmed.toLowerCase()
  }

  if (value instanceof Date) {
    return value.getTime()
  }

  return String(value).toLowerCase()
}
