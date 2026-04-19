import Guid from 'guid'
import invoiceItemStub from './invoice-item'
import taxStub from './tax'

export default function () {
  return {
    id: null,
    invoice_number: '',
    document_type: 'invoice',
    original_invoice_id: null,
    referent_document_number: null,
    referent_document_dt: null,
    credit_note_reason: null,
    customer: null,
    customer_id: null,
    template_name: null,
    invoice_date: '',
    due_date: '',
    notes: '',
    discount: 0,
    discount_type: 'fixed',
    discount_val: 0,
    reference_number: null,
    fiscal_payment_method_id: null,
    fiscal_status: null,
    fiscal_invoice_number: null,
    fiscal_verification_url: null,
    tax: 0,
    sub_total: 0,
    total: 0,
    tax_per_item: null,
    tax_included: false,
    sales_tax_type: null,
    sales_tax_address_type: null,
    discount_per_item: null,
    taxes: [],
    items: [
      {
        ...invoiceItemStub,
        id: Guid.raw(),
        taxes: [{ ...taxStub, id: Guid.raw() }],
      },
    ],
    customFields: [],
    fields: [],
    selectedNote: null,
    selectedCurrency: '',
  }
}
