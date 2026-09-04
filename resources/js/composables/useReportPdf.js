import { ref } from 'vue';
import api from '../services/api';

export function useReportPdf() {
  const isGenerating = ref(false);

  async function exportReportPdf(type, filters = {}, action = 'inline') {
    // 1. Strict Frontend Validation
    if (!type || typeof type !== 'string' || type.trim() === '' || type === 'undefined' || type === 'null') {
      console.error('[PDF Export] Aborted: Invalid or missing report type parameter:', type);
      alert('Error: Unable to generate PDF because report type is invalid or missing.');
      return;
    }

    const cleanType = type.trim();
    isGenerating.value = true;

    try {
      // 2. Prepare query parameters preserving active filters
      const params = {
        type: cleanType,
        action: action === 'attachment' ? 'attachment' : 'inline',
      };

      if (filters.period) params.period = filters.period;
      if (filters.date_from) params.date_from = filters.date_from;
      if (filters.date_to) params.date_to = filters.date_to;
      if (filters.group_by) params.group_by = filters.group_by;
      if (filters.store_id && filters.store_id !== 'all') params.store_id = filters.store_id;
      if (filters.search) params.search = filters.search;
      if (filters.brand_id) params.brand_id = filters.brand_id;
      if (filters.category_id) params.category_id = filters.category_id;
      if (filters.color_id) params.color_id = filters.color_id;
      if (filters.status) params.status = filters.status;

      // 3. Authenticated Axios API call with blob response type
      const response = await api.get('/reports/pdf', {
        params,
        responseType: 'blob',
      });

      // 4. Derive dynamic filename from response header or parameters
      let filename = `RUPSA-${cleanType.replace(/_/g, '-')}-${new Date().toISOString().slice(0, 10)}.pdf`;
      const headers = response?.headers || {};
      const disposition = headers['content-disposition'] || headers['Content-Disposition'];
      if (disposition && disposition.includes('filename=')) {
        const matches = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/.exec(disposition);
        if (matches != null && matches[1]) {
          filename = matches[1].replace(/['"]/g, '');
        }
      }

      const rawData = response?.data || response;
      const blob = rawData instanceof Blob ? rawData : new Blob([rawData], { type: 'application/pdf' });
      const blobUrl = window.URL.createObjectURL(blob);

      if (action === 'inline') {
        // Open PDF in a new browser tab for viewing
        const viewerWindow = window.open(blobUrl, '_blank');
        if (!viewerWindow) {
          // If popup blocker prevents new tab, fallback to direct download
          const link = document.createElement('a');
          link.href = blobUrl;
          link.download = filename;
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);
        }
      } else {
        // Download PDF directly with meaningful filename
        const link = document.createElement('a');
        link.href = blobUrl;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
      }

      // Revoke Object URL after delay
      setTimeout(() => {
        window.URL.revokeObjectURL(blobUrl);
      }, 60000);

    } catch (error) {
      console.error('[PDF Export] Failed to fetch PDF report:', error);
      let errorMsg = error?.message || 'Failed to generate PDF report. Please try again.';

      if (error?.raw?.response) {
        const res = error.raw.response;
        if (res.status === 401) {
          errorMsg = 'Session expired or unauthenticated. Please log in to view PDF reports.';
        } else if (res.status === 403) {
          errorMsg = 'Access Denied: You do not have permission to view or export this report.';
        } else if (res.status === 422) {
          errorMsg = 'Validation Error: Invalid parameters submitted for PDF generation.';
        }
      }

      alert(errorMsg);
    } finally {
      isGenerating.value = false;
    }
  }

  return {
    isGenerating,
    exportReportPdf,
  };
}
