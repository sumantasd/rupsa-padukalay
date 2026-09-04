<template>
  <div class="space-y-6 max-w-7xl mx-auto pb-16 antialiased font-sans">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/80 pb-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Inter-Store Stock Transfers</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">
          Transfer workflow engine between retail outlets & central warehouses for multi-store operations
        </p>
      </div>
      <div class="flex items-center gap-2">
        <button
          disabled
          class="px-5 py-2.5 bg-slate-300 text-slate-500 cursor-not-allowed rounded-xl font-black text-xs uppercase tracking-wider flex items-center gap-1.5"
        >
          <span>🚚</span>
          <span>New Stock Transfer</span>
        </button>
      </div>
    </div>

    <!-- SINGLE OUTLET NOTICE BANNER -->
    <div class="p-5 bg-blue-50 border-2 border-blue-200 rounded-3xl flex flex-col sm:flex-row items-start sm:items-center gap-4 text-xs shadow-xs">
      <div class="w-12 h-12 bg-blue-600 text-white rounded-2xl flex items-center justify-center font-black text-xl shrink-0">
        🏪
      </div>
      <div class="space-y-1">
        <div class="font-black text-blue-900 text-sm">Single Outlet Configured (STR-001)</div>
        <p class="text-blue-800 font-medium leading-relaxed">
          RUPSA PADUKALAYA is currently operating as a single store outlet (<strong>RUPSA PADUKALAYA - Main Outlet</strong> / Code: <code>STR-001</code>).
          Inter-store stock transfers are disabled until additional retail outlets or external warehouses are registered in Store Management.
        </p>
      </div>
    </div>

    <!-- MULTI-STORE WORKFLOW READINESS INFO -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
      <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs space-y-2">
        <div class="flex items-center gap-2">
          <span class="p-1.5 bg-emerald-100 text-emerald-800 rounded-lg font-black text-xs">1</span>
          <h3 class="font-black text-slate-900">Transfer Request & Approval</h3>
        </div>
        <p class="text-slate-500 text-[11px] font-medium leading-normal">
          Draft transfer requests created by store managers requiring approval before stock lock & dispatch.
        </p>
      </div>

      <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs space-y-2">
        <div class="flex items-center gap-2">
          <span class="p-1.5 bg-blue-100 text-blue-800 rounded-lg font-black text-xs">2</span>
          <h3 class="font-black text-slate-900">Transit & TRANSFER_OUT</h3>
        </div>
        <p class="text-slate-500 text-[11px] font-medium leading-normal">
          Stock deduction from source store logging immutable <code>TRANSFER_OUT</code> movements upon dispatch.
        </p>
      </div>

      <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs space-y-2">
        <div class="flex items-center gap-2">
          <span class="p-1.5 bg-purple-100 text-purple-800 rounded-lg font-black text-xs">3</span>
          <h3 class="font-black text-slate-900">Receiving & TRANSFER_IN</h3>
        </div>
        <p class="text-slate-500 text-[11px] font-medium leading-normal">
          Destination store physical count confirmation logging <code>TRANSFER_IN</code> movements upon receipt.
        </p>
      </div>
    </div>

    <!-- TRANSFERS LIST PLACEHOLDER TABLE -->
    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
      <div class="px-5 py-3 bg-slate-900 text-white flex items-center justify-between">
        <h2 class="text-xs font-extrabold uppercase tracking-wider">Historical Inter-Store Transfer Records</h2>
        <span class="text-[10px] font-bold bg-slate-800 px-2 py-0.5 rounded text-slate-300">STR-001 Scope</span>
      </div>

      <div class="p-12 text-center text-slate-400 space-y-2">
        <div class="text-3xl">🚚</div>
        <div class="font-black text-slate-800 text-sm">No Inter-Store Transfers Executed</div>
        <p class="text-xs text-slate-500 max-w-md mx-auto font-medium">
          All stock movements for RUPSA PADUKALAYA are currently managed within Main Outlet (STR-001).
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
</script>
