<template>
  <div class="flex items-center gap-2">
    <!-- View PDF Button -->
    <button
      type="button"
      @click="handleView"
      :disabled="isGenerating"
      class="px-3 py-2 bg-slate-900 hover:bg-slate-800 active:scale-95 text-white rounded-xl text-xs font-extrabold shadow-sm transition-all flex items-center gap-1.5 cursor-pointer touch-target shrink-0 disabled:opacity-50"
      title="Open report in browser PDF viewer"
    >
      <span>📄</span>
      <span class="whitespace-nowrap">View PDF</span>
    </button>

    <!-- Download PDF Button -->
    <button
      type="button"
      @click="handleDownload"
      :disabled="isGenerating"
      class="px-3 py-2 bg-red-600 hover:bg-red-700 active:scale-95 text-white rounded-xl text-xs font-black shadow-md shadow-red-600/20 transition-all flex items-center gap-1.5 cursor-pointer touch-target shrink-0 disabled:opacity-50"
      title="Download PDF report file"
    >
      <span>⬇️</span>
      <span class="whitespace-nowrap">Download PDF</span>
    </button>
  </div>
</template>

<script setup>
import { useReportPdf } from '../../composables/useReportPdf';

const props = defineProps({
  reportType: {
    type: String,
    default: '',
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
});

const emit = defineEmits(['view-pdf', 'download-pdf']);

const { isGenerating, exportReportPdf } = useReportPdf();

function handleView() {
  emit('view-pdf');
  if (props.reportType) {
    exportReportPdf(props.reportType, props.filters, 'inline');
  }
}

function handleDownload() {
  emit('download-pdf');
  if (props.reportType) {
    exportReportPdf(props.reportType, props.filters, 'attachment');
  }
}
</script>
