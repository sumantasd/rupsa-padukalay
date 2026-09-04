<template>
  <div>
    <!-- SCREEN PREVIEW CONTAINER (Visible inside UI modals, hidden during printing) -->
    <div
      :class="[
        'thermal-receipt-screen-container font-mono text-slate-900 leading-tight select-none mx-auto p-4 bg-white shadow-xs rounded-xl print:hidden',
        mergedSettings.printer_width === '58mm' ? 'w-[58mm] text-[10px]' : 'w-[80mm] text-[11px]'
      ]"
    >
      <div class="receipt-content-body">
        <!-- 1. MASTER STORE HEADER -->
        <div class="text-center space-y-0.5">
          <div v-if="mergedSettings.show_logo" class="flex justify-center mb-1">
            <img
              v-if="mergedSettings.printer_logo_url"
              :src="mergedSettings.printer_logo_url"
              alt="Store Logo"
              class="max-w-[100px] max-h-[50px] object-contain filter grayscale contrast-125 mx-auto"
            />
            <div v-else class="w-10 h-10 bg-slate-900 text-white rounded-full flex items-center justify-center font-black text-sm border-2 border-slate-900">
              RP
            </div>
          </div>
          <div v-if="mergedSettings.show_store_name" class="font-black text-sm uppercase tracking-wider">
            RUPSA PADUKALAYA
          </div>
          <div v-if="mergedSettings.show_outlet_name" class="text-[10px] font-bold text-slate-700 uppercase">
            Main Outlet (STR-001)
          </div>
          <div v-if="mergedSettings.show_address" class="text-[9px] text-slate-600 font-semibold leading-snug">
            DHANTALA BAZAR, DHANTALA, NADIA - 741202<br />
            WEST BENGAL, INDIA
          </div>
          <div v-if="mergedSettings.show_phone" class="text-[9px] text-slate-600 font-bold">
            PH: +91 9735125112
          </div>
          <div v-if="mergedSettings.show_gstin && storeGstin" class="text-[9px] text-slate-600 font-bold">
            GSTIN: {{ storeGstin }}
          </div>
          <div v-if="mergedSettings.show_email && storeEmail" class="text-[9px] text-slate-500">
            Email: {{ storeEmail }}
          </div>
          <div class="border-b border-dashed border-slate-400 my-2"></div>
        </div>

        <!-- 2. DOCUMENT TITLE -->
        <div class="text-center font-black uppercase text-xs tracking-wider mb-2">
          <span class="px-2 py-0.5 border border-slate-900 inline-block bg-slate-50 rounded">
            {{ getDocumentTitle() }}
          </span>
        </div>

        <!-- 3. TRANSACTION METADATA -->
        <div class="space-y-0.5 text-[10px] mb-2">
          <div class="flex justify-between font-bold">
            <span>{{ getDocNumberLabel() }}:</span>
            <span>{{ getDocNumber() }}</span>
          </div>
          <div v-if="(documentType === 'return' || documentType === 'exchange') && getOriginalInvoiceNumber()" class="flex justify-between text-slate-600">
            <span>ORIGINAL BILL:</span>
            <span class="font-bold">{{ getOriginalInvoiceNumber() }}</span>
          </div>
          <div class="flex justify-between text-slate-600">
            <span>DATE & TIME:</span>
            <span>{{ formatDateTime(getDataDate()) }}</span>
          </div>
          <div v-if="getCashierName()" class="flex justify-between text-slate-600">
            <span>STAFF:</span>
            <span>{{ getCashierName() }}</span>
          </div>
          <div v-if="mergedSettings.show_customer_name && getCustomerName()" class="flex justify-between font-bold border-t border-slate-200 pt-1 mt-1">
            <span>CUSTOMER:</span>
            <span>{{ getCustomerName() }}</span>
          </div>
          <div v-if="mergedSettings.show_customer_mobile && getCustomerMobile()" class="flex justify-between text-slate-600">
            <span>MOBILE:</span>
            <span>{{ getCustomerMobile() }}</span>
          </div>
          <div v-if="mergedSettings.show_customer_address && getCustomerAddress()" class="flex justify-between text-slate-500">
            <span>ADDRESS:</span>
            <span>{{ getCustomerAddress() }}</span>
          </div>
          <div class="border-b border-dashed border-slate-400 my-2"></div>
        </div>

        <!-- 4. SALES INVOICE ITEMS TABLE -->
        <div v-if="documentType === 'invoice'" class="space-y-2 mb-2">
          <div class="space-y-1">
            <div v-for="(item, idx) in getItemList()" :key="idx" class="space-y-0.5 border-b border-slate-100 pb-1">
              <div class="font-bold flex justify-between">
                <span v-if="mergedSettings.show_product_name">{{ getItemName(item) }}</span>
                <span v-if="mergedSettings.show_selling_price">₹{{ formatCurrency(item.subtotal || (item.quantity * item.unit_price)) }}</span>
              </div>
              <div class="text-[9px] text-slate-600 flex justify-between flex-wrap gap-x-2">
                <div>
                  <span v-if="mergedSettings.show_article_number">ART: {{ getItemArticle(item) }}</span>
                  <span v-if="mergedSettings.show_brand && getItemBrand(item)"> | {{ getItemBrand(item) }}</span>
                </div>
                <div>
                  <span v-if="mergedSettings.show_color">{{ getItemColor(item) }}</span>
                  <span v-if="mergedSettings.show_size" class="font-bold text-slate-900"> | IND {{ getItemSize(item) }}</span>
                </div>
              </div>
              <div class="text-[9px] text-slate-500 flex justify-between font-mono">
                <span>
                  <span v-if="mergedSettings.show_quantity">{{ item.quantity }} pcs</span>
                  <span v-if="mergedSettings.show_selling_price"> × ₹{{ formatCurrency(item.unit_price) }}</span>
                  <span v-if="mergedSettings.show_mrp && item.mrp > item.unit_price" class="line-through text-slate-400 ml-1">MRP ₹{{ formatCurrency(item.mrp) }}</span>
                </span>
                <span v-if="mergedSettings.show_line_discount && item.discount_amount > 0" class="text-emerald-700 font-bold">
                  -₹{{ formatCurrency(item.discount_amount) }}
                </span>
              </div>
            </div>
          </div>
          <div class="border-b border-dashed border-slate-400 my-2"></div>
        </div>

        <!-- 5. SALES RETURN ITEMS TABLE -->
        <div v-else-if="documentType === 'return'" class="space-y-2 mb-2">
          <div class="font-bold text-[10px] text-amber-900 uppercase tracking-wider">RETURNED FOOTWEAR ITEMS:</div>
          <div class="space-y-1">
            <div v-for="(item, idx) in getItemList()" :key="idx" class="space-y-0.5 border-b border-slate-100 pb-1">
              <div class="font-bold flex justify-between text-amber-900">
                <span v-if="mergedSettings.show_product_name">{{ getItemName(item) }}</span>
                <span v-if="mergedSettings.show_selling_price">₹{{ formatCurrency(item.subtotal || (item.quantity * item.unit_price)) }}</span>
              </div>
              <div class="text-[9px] text-slate-600 flex justify-between flex-wrap gap-x-2">
                <div>
                  <span v-if="mergedSettings.show_article_number">ART: {{ getItemArticle(item) }}</span>
                  <span v-if="mergedSettings.show_brand && getItemBrand(item)"> | {{ getItemBrand(item) }}</span>
                </div>
                <div>
                  <span v-if="mergedSettings.show_color">{{ getItemColor(item) }}</span>
                  <span v-if="mergedSettings.show_size" class="font-bold text-slate-900"> | IND {{ getItemSize(item) }}</span>
                </div>
              </div>
              <div class="text-[9px] text-slate-500 flex justify-between font-mono">
                <span>
                  <span v-if="mergedSettings.show_quantity">{{ item.quantity }} pcs</span>
                  <span v-if="mergedSettings.show_selling_price"> × ₹{{ formatCurrency(item.unit_price || item.refund_unit_price) }}</span>
                </span>
              </div>
            </div>
          </div>
          <div class="border-b border-dashed border-slate-400 my-2"></div>
        </div>

        <!-- 6. EXCHANGE ITEMS TABLE (RETURNED OLD & REPLACEMENT NEW) -->
        <div v-else-if="documentType === 'exchange'" class="space-y-2 mb-2">
          <!-- Returned Old Item -->
          <div class="space-y-1">
            <div class="font-bold text-[10px] text-amber-900 uppercase">RETURNED OLD ITEM:</div>
            <div v-for="(item, idx) in getReturnedItemsList()" :key="'ret-'+idx" class="space-y-0.5 border-b border-slate-100 pb-1">
              <div class="font-bold flex justify-between">
                <span v-if="mergedSettings.show_product_name">{{ getItemName(item) }}</span>
                <span v-if="mergedSettings.show_selling_price">₹{{ formatCurrency(item.subtotal || (item.quantity * item.unit_price)) }}</span>
              </div>
              <div class="text-[9px] text-slate-600 flex justify-between flex-wrap gap-x-2">
                <div>
                  <span v-if="mergedSettings.show_article_number">ART: {{ getItemArticle(item) }}</span>
                  <span v-if="mergedSettings.show_brand && getItemBrand(item)"> | {{ getItemBrand(item) }}</span>
                </div>
                <div>
                  <span v-if="mergedSettings.show_color">{{ getItemColor(item) }}</span>
                  <span v-if="mergedSettings.show_size" class="font-bold text-slate-900"> | IND {{ getItemSize(item) }}</span>
                </div>
              </div>
              <div class="text-[9px] text-slate-500 flex justify-between font-mono">
                <span>
                  <span v-if="mergedSettings.show_quantity">{{ item.quantity }} pcs</span>
                  <span v-if="mergedSettings.show_selling_price"> × ₹{{ formatCurrency(item.unit_price) }}</span>
                </span>
              </div>
            </div>
          </div>

          <!-- Replacement New Item -->
          <div class="space-y-1 pt-1">
            <div class="font-bold text-[10px] text-emerald-900 uppercase">REPLACEMENT NEW ITEM:</div>
            <div v-for="(item, idx) in getReplacementItemsList()" :key="'rep-'+idx" class="space-y-0.5 border-b border-slate-100 pb-1">
              <div class="font-bold flex justify-between text-emerald-900">
                <span v-if="mergedSettings.show_product_name">{{ getItemName(item) }}</span>
                <span v-if="mergedSettings.show_selling_price">₹{{ formatCurrency(item.subtotal || (item.quantity * item.unit_price)) }}</span>
              </div>
              <div class="text-[9px] text-slate-600 flex justify-between flex-wrap gap-x-2">
                <div>
                  <span v-if="mergedSettings.show_article_number">ART: {{ getItemArticle(item) }}</span>
                  <span v-if="mergedSettings.show_brand && getItemBrand(item)"> | {{ getItemBrand(item) }}</span>
                </div>
                <div>
                  <span v-if="mergedSettings.show_color">{{ getItemColor(item) }}</span>
                  <span v-if="mergedSettings.show_size" class="font-bold text-slate-900"> | IND {{ getItemSize(item) }}</span>
                </div>
              </div>
              <div class="text-[9px] text-slate-500 flex justify-between font-mono">
                <span>
                  <span v-if="mergedSettings.show_quantity">{{ item.quantity }} pcs</span>
                  <span v-if="mergedSettings.show_selling_price"> × ₹{{ formatCurrency(item.unit_price) }}</span>
                </span>
              </div>
            </div>
          </div>
          <div class="border-b border-dashed border-slate-400 my-2"></div>
        </div>

        <!-- 7. PAYMENT RECEIPT METADATA -->
        <div v-else-if="documentType === 'payment'" class="space-y-1 mb-2 text-[10px]">
          <div v-if="data.reference_number" class="flex justify-between">
            <span>AGAINST REFERENCE:</span>
            <span class="font-bold">{{ data.reference_number }}</span>
          </div>
          <div v-if="data.previous_due !== undefined" class="flex justify-between text-slate-600">
            <span>PREVIOUS DUE:</span>
            <span>₹{{ formatCurrency(data.previous_due) }}</span>
          </div>
          <div class="flex justify-between font-bold text-emerald-700">
            <span>AMOUNT RECEIVED:</span>
            <span>₹{{ formatCurrency(data.amount_received || data.amount) }}</span>
          </div>
          <div v-if="data.remaining_due !== undefined" class="flex justify-between text-slate-600">
            <span>REMAINING DUE:</span>
            <span>₹{{ formatCurrency(data.remaining_due) }}</span>
          </div>
          <div class="border-b border-dashed border-slate-400 my-2"></div>
        </div>

        <!-- 8. FINANCIAL SUMMARY -->
        <div class="space-y-0.5 text-[10px] font-bold text-right mb-2">
          <template v-if="documentType === 'invoice'">
            <div v-if="mergedSettings.show_subtotal" class="flex justify-between text-slate-600">
              <span>SUBTOTAL:</span>
              <span>₹{{ formatCurrency(data.subtotal) }}</span>
            </div>
            <div v-if="mergedSettings.show_discount && data.discount_amount > 0" class="flex justify-between text-emerald-700">
              <span>TOTAL DISCOUNT:</span>
              <span>-₹{{ formatCurrency(data.discount_amount) }}</span>
            </div>
            <div v-if="mergedSettings.show_tax && data.total_tax > 0" class="flex justify-between text-slate-600">
              <span>GST TAX:</span>
              <span>+₹{{ formatCurrency(data.total_tax) }}</span>
            </div>
            <div v-if="mergedSettings.show_grand_total" class="flex justify-between text-xs font-black pt-1 border-t border-slate-400">
              <span>GRAND TOTAL:</span>
              <span class="font-mono text-red-600">₹{{ formatCurrency(data.grand_total) }}</span>
            </div>

            <div v-if="mergedSettings.show_payment_method" class="pt-1.5 space-y-0.5 border-t border-slate-200 mt-1">
              <div v-for="(pay, pidx) in getPaymentList()" :key="pidx" class="flex justify-between text-[9px] uppercase text-slate-700">
                <span>PAID via {{ pay.payment_method }}:</span>
                <span>₹{{ formatCurrency(pay.amount) }}</span>
              </div>
              <div v-if="getPaymentList().length === 0" class="flex justify-between text-[9px] uppercase text-slate-700">
                <span>PAID via Cash:</span>
                <span>₹{{ formatCurrency(data.paid_amount || data.grand_total) }}</span>
              </div>
            </div>

            <div v-if="mergedSettings.show_due_amount && getDueAmount() > 0" class="flex justify-between text-red-600 font-bold">
              <span>DUE AMOUNT:</span>
              <span>₹{{ formatCurrency(getDueAmount()) }}</span>
            </div>
          </template>

          <template v-else-if="documentType === 'return'">
            <div class="flex justify-between text-xs font-black text-amber-900 border-t border-slate-400 pt-1">
              <span>TOTAL REFUND VALUE:</span>
              <span>₹{{ formatCurrency(data.total_refund_amount) }}</span>
            </div>
            <div v-if="mergedSettings.show_payment_method" class="flex justify-between text-[9px] text-slate-700 uppercase">
              <span>REFUND METHOD:</span>
              <span class="font-bold">{{ data.refund_mode }}</span>
            </div>
            <div v-if="mergedSettings.show_store_credit && data.refund_mode === 'store_credit'" class="p-1 bg-purple-50 border border-purple-200 rounded text-center text-[9px] text-purple-900 font-bold mt-1">
              Store Credit Added: ₹{{ formatCurrency(data.total_refund_amount) }}
            </div>
          </template>

          <template v-else-if="documentType === 'exchange'">
            <div v-if="mergedSettings.show_subtotal" class="flex justify-between text-slate-600">
              <span>OLD ITEM VALUE:</span>
              <span>₹{{ formatCurrency(getReturnedTotal()) }}</span>
            </div>
            <div v-if="mergedSettings.show_subtotal" class="flex justify-between text-slate-600">
              <span>NEW ITEM VALUE:</span>
              <span>₹{{ formatCurrency(getReplacementTotal()) }}</span>
            </div>
            <div v-if="mergedSettings.show_price_difference" class="flex justify-between text-xs font-black pt-1 border-t border-slate-400">
              <span>PRICE DIFFERENCE:</span>
              <span :class="getExchangeDiff() > 0 ? 'text-red-600' : getExchangeDiff() < 0 ? 'text-purple-700' : 'text-slate-900'">
                ₹{{ formatCurrency(Math.abs(getExchangeDiff())) }}
              </span>
            </div>
            <div class="text-[9px] text-slate-600 uppercase font-bold">
              STATUS: {{ getExchangeDiff() > 0 ? 'Customer Paid Extra' : getExchangeDiff() < 0 ? 'Customer Refund Due' : 'EVEN EXCHANGE' }}
            </div>
            <div v-if="mergedSettings.show_payment_method && (data.payment_method || data.refund_mode)" class="flex justify-between text-[9px] uppercase text-slate-700">
              <span>METHOD:</span>
              <span>{{ data.payment_method || data.refund_mode }}</span>
            </div>
            <div v-if="mergedSettings.show_store_credit && data.refund_mode === 'store_credit' && getExchangeDiff() < 0" class="p-1 bg-purple-50 border border-purple-200 rounded text-center text-[9px] text-purple-900 font-bold mt-1">
              Store Credit Added: ₹{{ formatCurrency(Math.abs(getExchangeDiff())) }}
            </div>
          </template>

          <template v-else-if="documentType === 'payment'">
            <div v-if="mergedSettings.show_payment_method" class="flex justify-between text-[9px] uppercase text-slate-700">
              <span>PAYMENT METHOD:</span>
              <span>{{ data.payment_method }}</span>
            </div>
            <div v-if="data.transaction_reference" class="flex justify-between text-[9px] text-slate-500">
              <span>REF #:</span>
              <span>{{ data.transaction_reference }}</span>
            </div>
          </template>

          <div class="border-b border-dashed border-slate-400 my-2"></div>
        </div>

        <!-- 9. QR CODE SECTION -->
        <div v-if="mergedSettings.show_qr_code" class="text-center space-y-1 mb-2">
          <div class="w-16 h-16 bg-white border border-slate-300 rounded p-1 mx-auto flex items-center justify-center">
            <svg class="w-full h-full text-slate-900" fill="currentColor" viewBox="0 0 24 24">
              <path d="M2 2h8v8H2V2zm2 2v4h4V4H4zm9-2h8v8h-8V2zm2 2v4h4V4h-4zM2 14h8v8H2v-8zm2 2v4h4v-4H4zm13-2h2v2h-2v-2zm-4 0h2v2h-2v-2zm2 2h2v2h-2v-2zm-2 2h2v2h-2v-2zm4 0h2v2h-2v-2zm2-2h2v2h-2v-2zm0 4h2v2h-2v-2zm-4 0h2v2h-2v-2z" />
            </svg>
          </div>
          <div class="text-[8px] font-mono text-slate-500 uppercase">
            {{ getQrLabel() }}
          </div>
        </div>

        <!-- 10. MASTER FOOTER -->
        <div class="text-center space-y-1 border-t border-dashed border-slate-400 pt-2 text-[9px] text-slate-600">
          <div v-if="mergedSettings.show_thank_you_message" class="font-semibold leading-tight">
            {{ mergedSettings.thank_you_message || 'Thank you for shopping with us! We hope to serve you again.' }}
          </div>
          <div v-if="mergedSettings.show_return_policy" class="text-[8px] font-bold text-slate-700 uppercase">
            {{ mergedSettings.return_policy_text || '*** GOODS ONCE SOLD WILL NOT BE TAKEN BACK ***' }}
          </div>
          <div v-if="mergedSettings.show_developed_by_credit" class="font-bold text-slate-900 pt-1 text-[9px]">
            Developed By Tech Googly
          </div>
        </div>
      </div>
    </div>

    <!-- PRINT PORTAL CONTAINER (Teleported directly under <body> for 100% reliable print rendering) -->
    <Teleport to="body">
      <div
        id="thermal-receipt-print-root"
        :class="[
          'font-mono text-black leading-tight bg-white p-2 mx-auto',
          mergedSettings.printer_width === '58mm' ? 'w-[58mm] text-[10px]' : 'w-[80mm] text-[11px]'
        ]"
      >
        <!-- 1. MASTER STORE HEADER -->
        <div class="text-center space-y-0.5">
          <div v-if="mergedSettings.show_logo" class="flex justify-center mb-1">
            <img
              v-if="mergedSettings.printer_logo_url"
              :src="mergedSettings.printer_logo_url"
              alt="Store Logo"
              class="max-w-[100px] max-h-[50px] object-contain filter grayscale contrast-150 mx-auto"
            />
            <div v-else class="w-10 h-10 bg-black text-white rounded-full flex items-center justify-center font-black text-sm border-2 border-black">
              RP
            </div>
          </div>
          <div v-if="mergedSettings.show_store_name" class="font-black text-sm uppercase tracking-wider">
            RUPSA PADUKALAYA
          </div>
          <div v-if="mergedSettings.show_outlet_name" class="text-[10px] font-bold text-black uppercase">
            Main Outlet (STR-001)
          </div>
          <div v-if="mergedSettings.show_address" class="text-[9px] text-black font-semibold leading-snug">
            DHANTALA BAZAR, DHANTALA, NADIA - 741202<br />
            WEST BENGAL, INDIA
          </div>
          <div v-if="mergedSettings.show_phone" class="text-[9px] text-black font-bold">
            PH: +91 9735125112
          </div>
          <div v-if="mergedSettings.show_gstin && storeGstin" class="text-[9px] text-black font-bold">
            GSTIN: {{ storeGstin }}
          </div>
          <div v-if="mergedSettings.show_email && storeEmail" class="text-[9px] text-black">
            Email: {{ storeEmail }}
          </div>
          <div class="border-b border-dashed border-black my-2"></div>
        </div>

        <div class="text-center font-black uppercase text-xs tracking-wider mb-2">
          <span class="px-2 py-0.5 border border-black inline-block rounded">
            {{ getDocumentTitle() }}
          </span>
        </div>

        <div class="space-y-0.5 text-[10px] mb-2">
          <div class="flex justify-between font-bold">
            <span>{{ getDocNumberLabel() }}:</span>
            <span>{{ getDocNumber() }}</span>
          </div>
          <div v-if="(documentType === 'return' || documentType === 'exchange') && getOriginalInvoiceNumber()" class="flex justify-between">
            <span>ORIGINAL BILL:</span>
            <span class="font-bold">{{ getOriginalInvoiceNumber() }}</span>
          </div>
          <div class="flex justify-between">
            <span>DATE & TIME:</span>
            <span>{{ formatDateTime(getDataDate()) }}</span>
          </div>
          <div v-if="getCashierName()" class="flex justify-between">
            <span>STAFF:</span>
            <span>{{ getCashierName() }}</span>
          </div>
          <div v-if="mergedSettings.show_customer_name && getCustomerName()" class="flex justify-between font-bold border-t border-black pt-1 mt-1">
            <span>CUSTOMER:</span>
            <span>{{ getCustomerName() }}</span>
          </div>
          <div v-if="mergedSettings.show_customer_mobile && getCustomerMobile()" class="flex justify-between">
            <span>MOBILE:</span>
            <span>{{ getCustomerMobile() }}</span>
          </div>
          <div v-if="mergedSettings.show_customer_address && getCustomerAddress()" class="flex justify-between">
            <span>ADDRESS:</span>
            <span>{{ getCustomerAddress() }}</span>
          </div>
          <div class="border-b border-dashed border-black my-2"></div>
        </div>

        <!-- Sales Invoice Print Table -->
        <div v-if="documentType === 'invoice'" class="space-y-2 mb-2">
          <div class="space-y-1">
            <div v-for="(item, idx) in getItemList()" :key="'prn-'+idx" class="space-y-0.5 border-b border-gray-300 pb-1">
              <div class="font-bold flex justify-between">
                <span v-if="mergedSettings.show_product_name">{{ getItemName(item) }}</span>
                <span v-if="mergedSettings.show_selling_price">₹{{ formatCurrency(item.subtotal || (item.quantity * item.unit_price)) }}</span>
              </div>
              <div class="text-[9px] flex justify-between flex-wrap gap-x-2">
                <div>
                  <span v-if="mergedSettings.show_article_number">ART: {{ getItemArticle(item) }}</span>
                  <span v-if="mergedSettings.show_brand && getItemBrand(item)"> | {{ getItemBrand(item) }}</span>
                </div>
                <div>
                  <span v-if="mergedSettings.show_color">{{ getItemColor(item) }}</span>
                  <span v-if="mergedSettings.show_size" class="font-bold"> | IND {{ getItemSize(item) }}</span>
                </div>
              </div>
              <div class="text-[9px] flex justify-between font-mono">
                <span>
                  <span v-if="mergedSettings.show_quantity">{{ item.quantity }} pcs</span>
                  <span v-if="mergedSettings.show_selling_price"> × ₹{{ formatCurrency(item.unit_price) }}</span>
                  <span v-if="mergedSettings.show_mrp && item.mrp > item.unit_price" class="line-through text-gray-500 ml-1">MRP ₹{{ formatCurrency(item.mrp) }}</span>
                </span>
                <span v-if="mergedSettings.show_line_discount && item.discount_amount > 0" class="font-bold">
                  -₹{{ formatCurrency(item.discount_amount) }}
                </span>
              </div>
            </div>
          </div>
          <div class="border-b border-dashed border-black my-2"></div>
        </div>

        <!-- Sales Return Print Table -->
        <div v-else-if="documentType === 'return'" class="space-y-2 mb-2">
          <div class="font-bold text-[10px] uppercase">RETURNED FOOTWEAR ITEMS:</div>
          <div class="space-y-1">
            <div v-for="(item, idx) in getItemList()" :key="'prn-ret-'+idx" class="space-y-0.5 border-b border-gray-300 pb-1">
              <div class="font-bold flex justify-between">
                <span v-if="mergedSettings.show_product_name">{{ getItemName(item) }}</span>
                <span v-if="mergedSettings.show_selling_price">₹{{ formatCurrency(item.subtotal || (item.quantity * item.unit_price)) }}</span>
              </div>
              <div class="text-[9px] flex justify-between flex-wrap gap-x-2">
                <div>
                  <span v-if="mergedSettings.show_article_number">ART: {{ getItemArticle(item) }}</span>
                  <span v-if="mergedSettings.show_brand && getItemBrand(item)"> | {{ getItemBrand(item) }}</span>
                </div>
                <div>
                  <span v-if="mergedSettings.show_color">{{ getItemColor(item) }}</span>
                  <span v-if="mergedSettings.show_size" class="font-bold"> | IND {{ getItemSize(item) }}</span>
                </div>
              </div>
              <div class="text-[9px] flex justify-between">
                <span>
                  <span v-if="mergedSettings.show_quantity">{{ item.quantity }} pcs</span>
                  <span v-if="mergedSettings.show_selling_price"> × ₹{{ formatCurrency(item.unit_price || item.refund_unit_price) }}</span>
                </span>
              </div>
            </div>
          </div>
          <div class="border-b border-dashed border-black my-2"></div>
        </div>

        <!-- Exchange Print Table -->
        <div v-else-if="documentType === 'exchange'" class="space-y-2 mb-2">
          <div class="space-y-1">
            <div class="font-bold text-[10px] uppercase">RETURNED OLD ITEM:</div>
            <div v-for="(item, idx) in getReturnedItemsList()" :key="'prn-ret-old-'+idx" class="space-y-0.5 border-b border-gray-300 pb-1">
              <div class="font-bold flex justify-between">
                <span v-if="mergedSettings.show_product_name">{{ getItemName(item) }}</span>
                <span v-if="mergedSettings.show_selling_price">₹{{ formatCurrency(item.subtotal || (item.quantity * item.unit_price)) }}</span>
              </div>
              <div class="text-[9px] flex justify-between flex-wrap gap-x-2">
                <div>
                  <span v-if="mergedSettings.show_article_number">ART: {{ getItemArticle(item) }}</span>
                  <span v-if="mergedSettings.show_brand && getItemBrand(item)"> | {{ getItemBrand(item) }}</span>
                </div>
                <div>
                  <span v-if="mergedSettings.show_color">{{ getItemColor(item) }}</span>
                  <span v-if="mergedSettings.show_size" class="font-bold"> | IND {{ getItemSize(item) }}</span>
                </div>
              </div>
              <div class="text-[9px] flex justify-between">
                <span>
                  <span v-if="mergedSettings.show_quantity">{{ item.quantity }} pcs</span>
                  <span v-if="mergedSettings.show_selling_price"> × ₹{{ formatCurrency(item.unit_price) }}</span>
                </span>
              </div>
            </div>
          </div>

          <div class="space-y-1 pt-1">
            <div class="font-bold text-[10px] uppercase">REPLACEMENT NEW ITEM:</div>
            <div v-for="(item, idx) in getReplacementItemsList()" :key="'prn-rep-new-'+idx" class="space-y-0.5 border-b border-gray-300 pb-1">
              <div class="font-bold flex justify-between">
                <span v-if="mergedSettings.show_product_name">{{ getItemName(item) }}</span>
                <span v-if="mergedSettings.show_selling_price">₹{{ formatCurrency(item.subtotal || (item.quantity * item.unit_price)) }}</span>
              </div>
              <div class="text-[9px] flex justify-between flex-wrap gap-x-2">
                <div>
                  <span v-if="mergedSettings.show_article_number">ART: {{ getItemArticle(item) }}</span>
                  <span v-if="mergedSettings.show_brand && getItemBrand(item)"> | {{ getItemBrand(item) }}</span>
                </div>
                <div>
                  <span v-if="mergedSettings.show_color">{{ getItemColor(item) }}</span>
                  <span v-if="mergedSettings.show_size" class="font-bold"> | IND {{ getItemSize(item) }}</span>
                </div>
              </div>
              <div class="text-[9px] flex justify-between">
                <span>
                  <span v-if="mergedSettings.show_quantity">{{ item.quantity }} pcs</span>
                  <span v-if="mergedSettings.show_selling_price"> × ₹{{ formatCurrency(item.unit_price) }}</span>
                </span>
              </div>
            </div>
          </div>
          <div class="border-b border-dashed border-black my-2"></div>
        </div>

        <div v-else-if="documentType === 'payment'" class="space-y-1 mb-2 text-[10px]">
          <div v-if="data.reference_number" class="flex justify-between">
            <span>AGAINST REFERENCE:</span>
            <span class="font-bold">{{ data.reference_number }}</span>
          </div>
          <div v-if="data.previous_due !== undefined" class="flex justify-between">
            <span>PREVIOUS DUE:</span>
            <span>₹{{ formatCurrency(data.previous_due) }}</span>
          </div>
          <div class="flex justify-between font-bold">
            <span>AMOUNT RECEIVED:</span>
            <span>₹{{ formatCurrency(data.amount_received || data.amount) }}</span>
          </div>
          <div v-if="data.remaining_due !== undefined" class="flex justify-between">
            <span>REMAINING DUE:</span>
            <span>₹{{ formatCurrency(data.remaining_due) }}</span>
          </div>
          <div class="border-b border-dashed border-black my-2"></div>
        </div>

        <!-- Financial Summary Print -->
        <div class="space-y-0.5 text-[10px] font-bold text-right mb-2">
          <template v-if="documentType === 'invoice'">
            <div v-if="mergedSettings.show_subtotal" class="flex justify-between">
              <span>SUBTOTAL:</span>
              <span>₹{{ formatCurrency(data.subtotal) }}</span>
            </div>
            <div v-if="mergedSettings.show_discount && data.discount_amount > 0" class="flex justify-between">
              <span>TOTAL DISCOUNT:</span>
              <span>-₹{{ formatCurrency(data.discount_amount) }}</span>
            </div>
            <div v-if="mergedSettings.show_tax && data.total_tax > 0" class="flex justify-between">
              <span>GST TAX:</span>
              <span>+₹{{ formatCurrency(data.total_tax) }}</span>
            </div>
            <div v-if="mergedSettings.show_grand_total" class="flex justify-between text-xs font-black pt-1 border-t border-black">
              <span>GRAND TOTAL:</span>
              <span class="font-mono">₹{{ formatCurrency(data.grand_total) }}</span>
            </div>

            <div v-if="mergedSettings.show_payment_method" class="pt-1.5 space-y-0.5 border-t border-black my-1">
              <div v-for="(pay, pidx) in getPaymentList()" :key="'prn-pay-'+pidx" class="flex justify-between text-[9px] uppercase">
                <span>PAID via {{ pay.payment_method }}:</span>
                <span>₹{{ formatCurrency(pay.amount) }}</span>
              </div>
              <div v-if="getPaymentList().length === 0" class="flex justify-between text-[9px] uppercase">
                <span>PAID via Cash:</span>
                <span>₹{{ formatCurrency(data.paid_amount || data.grand_total) }}</span>
              </div>
            </div>

            <div v-if="mergedSettings.show_due_amount && getDueAmount() > 0" class="flex justify-between font-bold">
              <span>DUE AMOUNT:</span>
              <span>₹{{ formatCurrency(getDueAmount()) }}</span>
            </div>
          </template>

          <template v-else-if="documentType === 'return'">
            <div class="flex justify-between text-xs font-black border-t border-black pt-1">
              <span>TOTAL REFUND VALUE:</span>
              <span>₹{{ formatCurrency(data.total_refund_amount) }}</span>
            </div>
            <div v-if="mergedSettings.show_payment_method" class="flex justify-between text-[9px] uppercase">
              <span>REFUND METHOD:</span>
              <span class="font-bold">{{ data.refund_mode }}</span>
            </div>
            <div v-if="mergedSettings.show_store_credit && data.refund_mode === 'store_credit'" class="p-1 border border-black rounded text-center text-[9px] font-bold mt-1">
              Store Credit Added: ₹{{ formatCurrency(data.total_refund_amount) }}
            </div>
          </template>

          <template v-else-if="documentType === 'exchange'">
            <div v-if="mergedSettings.show_subtotal" class="flex justify-between">
              <span>OLD ITEM VALUE:</span>
              <span>₹{{ formatCurrency(getReturnedTotal()) }}</span>
            </div>
            <div v-if="mergedSettings.show_subtotal" class="flex justify-between">
              <span>NEW ITEM VALUE:</span>
              <span>₹{{ formatCurrency(getReplacementTotal()) }}</span>
            </div>
            <div v-if="mergedSettings.show_price_difference" class="flex justify-between text-xs font-black pt-1 border-t border-black">
              <span>PRICE DIFFERENCE:</span>
              <span>₹{{ formatCurrency(Math.abs(getExchangeDiff())) }}</span>
            </div>
            <div class="text-[9px] uppercase font-bold">
              STATUS: {{ getExchangeDiff() > 0 ? 'Customer Paid Extra' : getExchangeDiff() < 0 ? 'Customer Refund Due' : 'EVEN EXCHANGE' }}
            </div>
            <div v-if="mergedSettings.show_payment_method && (data.payment_method || data.refund_mode)" class="flex justify-between text-[9px] uppercase">
              <span>METHOD:</span>
              <span>{{ data.payment_method || data.refund_mode }}</span>
            </div>
            <div v-if="mergedSettings.show_store_credit && data.refund_mode === 'store_credit' && getExchangeDiff() < 0" class="p-1 border border-black rounded text-center text-[9px] font-bold mt-1">
              Store Credit Added: ₹{{ formatCurrency(Math.abs(getExchangeDiff())) }}
            </div>
          </template>

          <template v-else-if="documentType === 'payment'">
            <div v-if="mergedSettings.show_payment_method" class="flex justify-between text-[9px] uppercase">
              <span>PAYMENT METHOD:</span>
              <span>{{ data.payment_method }}</span>
            </div>
            <div v-if="data.transaction_reference" class="flex justify-between text-[9px]">
              <span>REF #:</span>
              <span>{{ data.transaction_reference }}</span>
            </div>
          </template>

          <div class="border-b border-dashed border-black my-2"></div>
        </div>

        <div v-if="mergedSettings.show_qr_code" class="text-center space-y-1 mb-2">
          <div class="w-16 h-16 bg-white border border-black rounded p-1 mx-auto flex items-center justify-center">
            <svg class="w-full h-full text-black" fill="currentColor" viewBox="0 0 24 24">
              <path d="M2 2h8v8H2V2zm2 2v4h4V4H4zm9-2h8v8h-8V2zm2 2v4h4V4h-4zM2 14h8v8H2v-8zm2 2v4h4v-4H4zm13-2h2v2h-2v-2zm-4 0h2v2h-2v-2zm2 2h2v2h-2v-2zm-2 2h2v2h-2v-2zm4 0h2v2h-2v-2zm2-2h2v2h-2v-2zm0 4h2v2h-2v-2zm-4 0h2v2h-2v-2z" />
            </svg>
          </div>
          <div class="text-[8px] font-mono uppercase">
            {{ getQrLabel() }}
          </div>
        </div>

        <div class="text-center space-y-1 border-t border-dashed border-black pt-2 text-[9px]">
          <div v-if="mergedSettings.show_thank_you_message" class="font-semibold leading-tight">
            {{ mergedSettings.thank_you_message || 'Thank you for shopping with us! We hope to serve you again.' }}
          </div>
          <div v-if="mergedSettings.show_return_policy" class="text-[8px] font-bold uppercase">
            {{ mergedSettings.return_policy_text || '*** GOODS ONCE SOLD WILL NOT BE TAKEN BACK ***' }}
          </div>
          <div v-if="mergedSettings.show_developed_by_credit" class="font-bold pt-1 text-[9px]">
            Developed By Tech Googly
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { usePrinterStore } from '../../stores/printerStore';

const props = defineProps({
  documentType: {
    type: String,
    default: 'invoice', // 'invoice', 'return', 'exchange', 'payment'
  },
  data: {
    type: Object,
    default: () => ({}),
  },
  settings: {
    type: Object,
    default: null,
  },
});

const printerStore = usePrinterStore();

onMounted(() => {
  if (!printerStore.loaded) {
    printerStore.fetchSettings();
  }
});

const mergedSettings = computed(() => {
  if (props.settings && typeof props.settings === 'object' && Object.keys(props.settings).length > 0) {
    return { ...printerStore.settings, ...props.settings };
  }
  return printerStore.settings;
});

const storeGstin = computed(() => props.data.store?.gstin || props.data.store_gstin || '19ABCDE1234F1Z5');
const storeEmail = computed(() => props.data.store?.email || props.data.store_email || '');

function formatCurrency(val) {
  return Number(val || 0).toLocaleString('en-IN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function formatDateTime(dtStr) {
  if (!dtStr) return 'N/A';
  try {
    const d = new Date(dtStr);
    return d.toLocaleString('en-IN', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch (e) {
    return dtStr;
  }
}

function getDocumentTitle() {
  if (props.documentType === 'return') return 'SALES RETURN';
  if (props.documentType === 'exchange') return 'FOOTWEAR EXCHANGE';
  if (props.documentType === 'payment') return 'PAYMENT RECEIPT';
  return 'SALES INVOICE';
}

function getDocNumberLabel() {
  if (props.documentType === 'return') return 'RETURN #';
  if (props.documentType === 'exchange') return 'EXCHANGE #';
  if (props.documentType === 'payment') return 'RECEIPT #';
  return 'INVOICE #';
}

function getDocNumber() {
  if (props.documentType === 'return') return props.data.return_number || ('RET-' + props.data.id);
  if (props.documentType === 'exchange') return props.data.exchange_number || props.data.return_number || ('EXC-' + props.data.id);
  if (props.documentType === 'payment') return props.data.receipt_number || ('PAY-' + props.data.id);
  return props.data.invoice_number || ('INV-' + props.data.id);
}

function getOriginalInvoiceNumber() {
  return props.data.original_invoice_number || props.data.original_invoice?.invoice_number || '';
}

function getDataDate() {
  return props.data.created_at || props.data.payment_time || new Date().toISOString();
}

function getCashierName() {
  return props.data.cashier_name || props.data.processed_by_name || props.data.creator?.name || props.data.processor?.name || 'Staff';
}

function getCustomerName() {
  return props.data.customer_name || props.data.customer?.name || '';
}

function getCustomerMobile() {
  return props.data.customer_mobile || props.data.customer?.mobile_number || '';
}

function getCustomerAddress() {
  return props.data.customer_address || props.data.customer?.address || '';
}

function getItemList() {
  return props.data.items || props.data.returned_items || [];
}

function getReturnedItemsList() {
  return props.data.returned_items || props.data.items || [];
}

function getReplacementItemsList() {
  return props.data.replacement_items || props.data.replacementMovements || [];
}

function getItemName(i) {
  return i.product_name || i.product_name_snapshot || i.variantSize?.variant?.product?.name || 'Footwear Item';
}

function getItemArticle(i) {
  return i.article_number || i.article_number_snapshot || i.sku || i.sku_snapshot || 'N/A';
}

function getItemBrand(i) {
  return i.brand_name || i.variantSize?.variant?.product?.brand?.name || '';
}

function getItemColor(i) {
  return i.color_name || i.color || i.color_name_snapshot || i.variantSize?.variant?.color?.name || 'Std';
}

function getItemSize(i) {
  return i.size_number || i.size || i.size_number_snapshot || i.variantSize?.size?.size_number || 'N/A';
}

function getPaymentList() {
  return props.data.payments || [];
}

function getDueAmount() {
  const gt = Number(props.data.grand_total || 0);
  const paid = Number(props.data.paid_amount || 0);
  return Math.max(0, gt - paid);
}

function getReturnedTotal() {
  if (props.data.returned_total !== undefined) return Number(props.data.returned_total);
  return getReturnedItemsList().reduce((sum, item) => sum + (Number(item.subtotal || (item.quantity * item.unit_price)) || 0), 0);
}

function getReplacementTotal() {
  if (props.data.replacement_total !== undefined) return Number(props.data.replacement_total);
  return getReplacementItemsList().reduce((sum, item) => sum + (Number(item.subtotal || (item.quantity * item.unit_price)) || 0), 0);
}

function getExchangeDiff() {
  if (props.data.price_difference !== undefined) return Number(props.data.price_difference);
  return getReplacementTotal() - getReturnedTotal();
}

function getQrLabel() {
  const mode = mergedSettings.value.qr_code_mode;
  if (mode === 'upi_payment') return 'Scan to Pay via UPI';
  if (mode === 'website') return 'Visit RUPSA Website';
  if (mode === 'whatsapp') return 'WhatsApp Support';
  return 'Scan Invoice Ref';
}
</script>
