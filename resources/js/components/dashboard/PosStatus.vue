<template>
  <div class="bg-white rounded-2xl border border-slate-200/90 p-5 shadow-xs flex items-center justify-between">
    <div class="flex items-center gap-4">
      <div class="h-12 w-12 rounded-2xl bg-blue-50 border border-blue-100 text-blue-600 flex items-center justify-center text-xl shadow-xs">
        🏪
      </div>
      <div>
        <div class="flex items-center gap-2">
          <h4 class="text-xs font-black text-slate-900 uppercase tracking-wider">POS Status</h4>
          <router-link to="/admin/pos" class="text-[10px] font-bold text-blue-600 hover:underline">View Details ∨</router-link>
        </div>
        <div class="text-2xl font-black text-slate-900 tracking-tight mt-0.5">
          {{ openRegisters }}
        </div>
        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
          Active Open Registers
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  openRegisters: { type: [Number, String], default: 0 }
});
</script>
