<template>
  <div class="space-y-6 max-w-5xl mx-auto pb-16 antialiased font-sans">
    <!-- Header -->
    <div class="border-b border-slate-200 pb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Company Profile & Branding</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">
          Manage business identity, GSTIN registration details & dual logo variants for light and dark UI themes
        </p>
      </div>
      <button
        @click="saveProfile"
        :disabled="saving"
        class="px-5 py-2.5 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white rounded-xl font-black text-xs uppercase tracking-wider shadow-md shadow-red-600/20 transition-all flex items-center gap-2 cursor-pointer shrink-0"
      >
        <span>{{ saving ? 'Saving...' : '💾 Save Company Details' }}</span>
      </button>
    </div>

    <!-- DUAL LOGO CONFIGURATION CARDS -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- 1. PRIMARY LOGO CARD -->
      <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs space-y-4 flex flex-col justify-between">
        <div>
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="text-base">☀️</span>
              <h2 class="font-black text-sm text-slate-900 uppercase tracking-wide">Primary Logo</h2>
            </div>
            <span class="px-2 py-0.5 bg-slate-100 text-slate-700 rounded text-[10px] font-extrabold uppercase">
              Light Backgrounds
            </span>
          </div>
          <p class="text-xs text-slate-500 mt-1">
            Displayed on white/light UI elements such as the Top Navbar, Mobile Drawer Menu, Invoices, and Reports.
          </p>

          <!-- Light Preview Surface -->
          <div class="mt-4 p-6 bg-slate-50 border-2 border-dashed border-slate-200 rounded-2xl flex flex-col items-center justify-center min-h-[140px]">
            <div v-if="companyStore.profile.primary_logo_url" class="relative group flex items-center justify-center">
              <img
                :src="companyStore.profile.primary_logo_url"
                alt="Primary Logo Preview"
                class="max-h-20 max-w-full object-contain"
              />
            </div>
            <div v-else class="text-center space-y-1">
              <div class="text-3xl">🖼️</div>
              <div class="text-xs font-bold text-slate-400">No Primary Logo Uploaded</div>
              <p class="text-[10px] text-slate-400">PNG, SVG, JPG, WEBP (Max 5MB)</p>
            </div>
          </div>
        </div>

        <!-- Primary Action Buttons -->
        <div class="pt-2 flex items-center gap-3">
          <label class="flex-1 py-2 px-4 bg-slate-900 hover:bg-slate-800 text-white text-center rounded-xl font-bold text-xs cursor-pointer transition-colors">
            <span>{{ uploadingPrimary ? 'Uploading...' : (companyStore.profile.primary_logo_url ? '🔄 Replace Primary Logo' : '📤 Upload Primary Logo') }}</span>
            <input
              type="file"
              accept="image/png,image/jpeg,image/webp,image/svg+xml"
              @change="handlePrimaryLogoUpload"
              class="hidden"
              :disabled="uploadingPrimary"
            />
          </label>
          <button
            v-if="companyStore.profile.primary_logo_url"
            @click="handlePrimaryLogoRemove"
            :disabled="removingPrimary"
            class="py-2 px-3 bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 rounded-xl font-bold text-xs transition-colors"
          >
            🗑️ Remove
          </button>
        </div>
      </div>

      <!-- 2. WHITE LOGO CARD -->
      <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs space-y-4 flex flex-col justify-between">
        <div>
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="text-base">🌙</span>
              <h2 class="font-black text-sm text-slate-900 uppercase tracking-wide">White Logo</h2>
            </div>
            <span class="px-2 py-0.5 bg-slate-900 text-white rounded text-[10px] font-extrabold uppercase">
              Dark Backgrounds
            </span>
          </div>
          <p class="text-xs text-slate-500 mt-1">
            Displayed on dark UI surfaces like the Desktop Admin Sidebar. If not configured, automatically falls back to Primary Logo.
          </p>

          <!-- Dark Navy Preview Surface -->
          <div class="mt-4 p-6 bg-slate-950 border-2 border-dashed border-slate-800 rounded-2xl flex flex-col items-center justify-center min-h-[140px]">
            <div v-if="companyStore.profile.white_logo_url" class="relative group flex items-center justify-center">
              <img
                :src="companyStore.profile.white_logo_url"
                alt="White Logo Preview"
                class="max-h-20 max-w-full object-contain"
              />
            </div>
            <div v-else-if="companyStore.profile.primary_logo_url" class="text-center space-y-1">
              <div class="text-xs font-extrabold text-amber-400">⚡ Falling Back to Primary Logo</div>
              <img
                :src="companyStore.profile.primary_logo_url"
                alt="Primary Fallback Preview"
                class="max-h-16 max-w-full object-contain opacity-70 mt-2"
              />
            </div>
            <div v-else class="text-center space-y-1">
              <div class="text-3xl text-slate-600">🖼️</div>
              <div class="text-xs font-bold text-slate-500">No White Logo Uploaded</div>
              <p class="text-[10px] text-slate-500">PNG, SVG, JPG, WEBP (Max 5MB)</p>
            </div>
          </div>
        </div>

        <!-- White Action Buttons -->
        <div class="pt-2 flex items-center gap-3">
          <label class="flex-1 py-2 px-4 bg-slate-900 hover:bg-slate-800 text-white text-center rounded-xl font-bold text-xs cursor-pointer transition-colors">
            <span>{{ uploadingWhite ? 'Uploading...' : (companyStore.profile.white_logo_url ? '🔄 Replace White Logo' : '📤 Upload White Logo') }}</span>
            <input
              type="file"
              accept="image/png,image/jpeg,image/webp,image/svg+xml"
              @change="handleWhiteLogoUpload"
              class="hidden"
              :disabled="uploadingWhite"
            />
          </label>
          <button
            v-if="companyStore.profile.white_logo_url"
            @click="handleWhiteLogoRemove"
            :disabled="removingWhite"
            class="py-2 px-3 bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 rounded-xl font-bold text-xs transition-colors"
          >
            🗑️ Remove
          </button>
        </div>
      </div>
    </div>

    <!-- BUSINESS DETAILS FORM -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-5">
      <div class="border-b border-slate-100 pb-3">
        <h2 class="font-black text-sm text-slate-900 uppercase tracking-wide">Business & Contact Information</h2>
        <p class="text-xs text-slate-500 mt-0.5">Used across official invoices, tax filings, and receipt headers</p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
        <div>
          <label class="block font-bold text-slate-700 mb-1">Brand Trade Name *</label>
          <input
            v-model="form.company_name"
            type="text"
            placeholder="RUPSA PADUKALAYA"
            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500/20"
          />
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1">Legal Entity Name</label>
          <input
            v-model="form.legal_name"
            type="text"
            placeholder="RUPSA PADUKALAYA RETAIL PRIVATE LIMITED"
            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500/20"
          />
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1">Company Tagline / Motto</label>
          <input
            v-model="form.tagline"
            type="text"
            placeholder="STEP INTO COMFORT"
            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500/20"
          />
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1">GSTIN Registration Number</label>
          <input
            v-model="form.gstin"
            type="text"
            placeholder="19AAECR1234F1Z5"
            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-mono font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500/20 uppercase"
          />
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1">Contact Phone Number</label>
          <input
            v-model="form.phone"
            type="text"
            placeholder="+91 9735125112"
            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500/20"
          />
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1">Support Email Address</label>
          <input
            v-model="form.email"
            type="email"
            placeholder="support@rupsapadukalaya.com"
            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500/20"
          />
        </div>

        <div class="sm:col-span-2">
          <label class="block font-bold text-slate-700 mb-1">Website URL</label>
          <input
            v-model="form.website"
            type="text"
            placeholder="https://rupsapadukalaya.com"
            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-mono font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500/20"
          />
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1">Address Line 1</label>
          <input
            v-model="form.address_line1"
            type="text"
            placeholder="DHANTALA BAZAR"
            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-900 focus:outline-none"
          />
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1">Address Line 2</label>
          <input
            v-model="form.address_line2"
            type="text"
            placeholder="DHANTALA, RANAGHAT - II"
            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-900 focus:outline-none"
          />
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1">City / District</label>
          <input
            v-model="form.city"
            type="text"
            placeholder="NADIA"
            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-900 focus:outline-none"
          />
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1">State & PIN Code</label>
          <div class="grid grid-cols-2 gap-2">
            <input
              v-model="form.state"
              type="text"
              placeholder="WEST BENGAL"
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-900 focus:outline-none"
            />
            <input
              v-model="form.pincode"
              type="text"
              placeholder="741202"
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-mono font-bold text-slate-900 focus:outline-none"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue';
import { useCompanyStore } from '../../stores/companyStore';

const companyStore = useCompanyStore();
const saving = ref(false);
const uploadingPrimary = ref(false);
const removingPrimary = ref(false);
const uploadingWhite = ref(false);
const removingWhite = ref(false);

const form = reactive({
  company_name: '',
  tagline: '',
  legal_name: '',
  gstin: '',
  phone: '',
  email: '',
  website: '',
  address_line1: '',
  address_line2: '',
  city: '',
  state: '',
  pincode: '',
  country: 'INDIA',
});

function syncForm() {
  Object.assign(form, {
    company_name: companyStore.profile.company_name || 'RUPSA PADUKALAYA',
    tagline: companyStore.profile.tagline || 'STEP INTO COMFORT',
    legal_name: companyStore.profile.legal_name || '',
    gstin: companyStore.profile.gstin || '',
    phone: companyStore.profile.phone || '',
    email: companyStore.profile.email || '',
    website: companyStore.profile.website || '',
    address_line1: companyStore.profile.address_line1 || '',
    address_line2: companyStore.profile.address_line2 || '',
    city: companyStore.profile.city || '',
    state: companyStore.profile.state || '',
    pincode: companyStore.profile.pincode || '',
    country: companyStore.profile.country || 'INDIA',
  });
}

onMounted(async () => {
  await companyStore.fetchCompanyProfile();
  syncForm();
});

watch(() => companyStore.profile, () => {
  syncForm();
}, { deep: true });

async function saveProfile() {
  saving.value = true;
  try {
    await companyStore.updateProfile(form);
    alert('Company details updated successfully!');
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to save company details.');
  } finally {
    saving.value = false;
  }
}

async function handlePrimaryLogoUpload(event) {
  const file = event.target.files?.[0];
  if (!file) return;

  uploadingPrimary.value = true;
  try {
    await companyStore.uploadPrimaryLogo(file);
    alert('Primary Logo uploaded successfully!');
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to upload Primary Logo.');
  } finally {
    uploadingPrimary.value = false;
    event.target.value = '';
  }
}

async function handlePrimaryLogoRemove() {
  if (!confirm('Are you sure you want to remove the Primary Logo?')) return;
  removingPrimary.value = true;
  try {
    await companyStore.deletePrimaryLogo();
    alert('Primary Logo removed.');
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to remove Primary Logo.');
  } finally {
    removingPrimary.value = false;
  }
}

async function handleWhiteLogoUpload(event) {
  const file = event.target.files?.[0];
  if (!file) return;

  uploadingWhite.value = true;
  try {
    await companyStore.uploadWhiteLogo(file);
    alert('White Logo uploaded successfully!');
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to upload White Logo.');
  } finally {
    uploadingWhite.value = false;
    event.target.value = '';
  }
}

async function handleWhiteLogoRemove() {
  if (!confirm('Are you sure you want to remove the White Logo?')) return;
  removingWhite.value = true;
  try {
    await companyStore.deleteWhiteLogo();
    alert('White Logo removed.');
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to remove White Logo.');
  } finally {
    removingWhite.value = false;
  }
}
</script>
