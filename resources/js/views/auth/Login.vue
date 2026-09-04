<template>
  <!-- Full Viewport Layout (No Large Outer Framed Container) -->
  <div class="min-h-screen w-full grid grid-cols-1 lg:grid-cols-12 bg-white text-slate-800 font-sans antialiased overflow-x-hidden">
    
    <!-- LEFT SECTION: Footwear Branding & Visual (≈50% Viewport, Desktop Only) -->
    <div class="hidden lg:flex lg:col-span-6 relative min-h-screen flex-col justify-between p-12 lg:p-16 text-white bg-slate-950 overflow-hidden">
      <!-- Footwear Hero Image -->
      <img
        :src="'/images/login-hero-sneaker.jpg'"
        alt="RUPSA PADUKALAYA Premium Footwear"
        class="absolute inset-0 w-full h-full object-cover z-0"
      />

      <!-- Dark Gradient & Accent Overlay for Readability -->
      <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/65 to-slate-950/75 z-10"></div>
      <div class="absolute top-0 right-0 w-96 h-96 bg-red-600/10 rounded-full blur-3xl pointer-events-none z-10"></div>

      <!-- Top Left Branding Section -->
      <div class="relative z-20 space-y-1">
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-xl bg-red-600 flex items-center justify-center text-white shadow-lg shadow-red-600/40">
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
              <path d="M21.7 14.3c-.4-.4-.9-.7-1.5-.9l-3.2-1.1c-.8-.3-1.7-.1-2.3.4l-1.4 1.2c-.4.3-.9.5-1.4.5H8.5c-.8 0-1.5-.7-1.5-1.5 0-.6.4-1.1 1-1.4l4.2-1.8c.6-.3 1-.8 1.1-1.5l.3-1.8c.1-.8-.4-1.5-1.2-1.7l-3.2-.8c-.7-.2-1.5.1-2 .7L3.4 8.2C2.5 9.3 2 10.7 2 12.1V17c0 1.7 1.3 3 3 3h13.5c1.4 0 2.6-.9 2.9-2.3l.5-2.2c.2-.4.1-.9-.2-1.2z"/>
            </svg>
          </div>
          <div>
            <div class="font-black text-xl tracking-wider text-white leading-none">RUPSA</div>
            <div class="text-[10px] font-extrabold tracking-widest text-slate-200 uppercase">PADUKALAYA</div>
          </div>
        </div>
        <div class="text-[9px] font-extrabold text-red-500 tracking-[0.25em] uppercase pl-13">
          — STEP INTO COMFORT —
        </div>
      </div>

      <!-- Center Collection Badge -->
      <div class="relative z-20 my-auto py-6">
        <span class="inline-block px-3.5 py-1.5 rounded-full bg-red-700/80 text-white text-[10px] font-extrabold uppercase tracking-widest border border-red-500/50 shadow-md">
          COLLECTION 2026
        </span>
      </div>

      <!-- Lower-Left Marketing Typography & Features -->
      <div class="relative z-20 space-y-6">
        <div class="space-y-0.5">
          <div class="text-4xl lg:text-5xl font-black text-white tracking-tight leading-none">COMFORT.</div>
          <div class="text-4xl lg:text-5xl font-black text-white tracking-tight leading-none">QUALITY.</div>
          <div class="text-4xl lg:text-5xl font-black text-red-600 tracking-tight leading-none">STYLE.</div>
          <p class="text-xs text-slate-300 font-medium pt-3 max-w-md">
            Premium Footwear for Every Step You Take.
          </p>
        </div>

        <!-- Bottom Feature Items Row -->
        <div class="grid grid-cols-3 gap-3 pt-6 border-t border-slate-800/80 text-[11px] font-bold text-slate-200">
          <div class="flex items-center gap-2">
            <span class="p-1.5 rounded-full bg-slate-900/90 text-red-500 text-xs border border-slate-800">🛡️</span>
            <span>Premium Quality</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="p-1.5 rounded-full bg-slate-900/90 text-red-500 text-xs border border-slate-800">🏅</span>
            <span>Trusted Brand</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="p-1.5 rounded-full bg-slate-900/90 text-red-500 text-xs border border-slate-800">🎧</span>
            <span>Dedicated Support</span>
          </div>
        </div>
      </div>
    </div>

    <!-- RIGHT SECTION: Admin & Staff Login Card (≈50% Viewport, Full Viewport Height) -->
    <div class="lg:col-span-6 min-h-screen bg-slate-50/60 flex flex-col justify-between items-center p-6 sm:p-12 lg:p-16">
      
      <!-- Mobile Top Logo Header (Mobile Only) -->
      <div class="lg:hidden w-full text-center space-y-2 mb-4 pt-2">
        <div class="inline-flex items-center gap-2.5">
          <div class="h-9 w-9 rounded-xl bg-red-600 text-white flex items-center justify-center font-black text-lg shadow-md">
            R
          </div>
          <div class="text-left">
            <div class="font-black text-lg tracking-wider text-slate-900 leading-none">RUPSA</div>
            <div class="text-[9px] font-extrabold tracking-widest text-slate-500 uppercase">PADUKALAYA</div>
          </div>
        </div>
      </div>

      <!-- Mobile Footwear Hero Card (Mobile Only) -->
      <div class="lg:hidden w-full max-w-[480px] h-36 rounded-2xl overflow-hidden relative mb-4 shadow-md">
        <img
          :src="'/images/login-hero-sneaker.jpg'"
          alt="RUPSA Footwear"
          class="w-full h-full object-cover"
        />
        <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-transparent to-transparent flex items-end p-3">
          <span class="text-white text-xs font-bold">Premium Footwear Collection 2026</span>
        </div>
      </div>

      <!-- Centered Login Card -->
      <div class="max-w-[480px] w-full bg-white rounded-3xl shadow-xl border border-slate-200/80 overflow-hidden flex flex-col justify-between my-auto">
        
        <div class="p-8 sm:p-10 space-y-6">
          <!-- Header Tag & Title -->
          <div class="text-center">
            <div class="inline-block px-3 py-1 rounded-md bg-red-50 text-red-700 border border-red-100 text-[10px] font-extrabold uppercase tracking-wider mb-2">
              ADMIN & STAFF PORTAL
            </div>
            <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
              Welcome <span class="text-red-600">Back!</span>
            </h2>
            <p class="text-xs text-slate-500 font-medium mt-1">
              Sign in to access your admin dashboard
            </p>
          </div>

          <!-- Error Alert -->
          <ErrorAlert v-if="error" :message="error" />

          <!-- Login Form -->
          <form @submit.prevent="handleLogin" class="space-y-4">
            <!-- Email Address / Username Field -->
            <div class="space-y-1.5">
              <label for="email-input" class="text-xs font-bold text-slate-700 block">
                Email Address
              </label>
              <div class="relative">
                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm">✉️</span>
                <input
                  id="email-input"
                  type="text"
                  v-model="form.login"
                  placeholder="Enter your email or username"
                  required
                  class="w-full bg-white border border-slate-300 rounded-xl pl-10 pr-4 py-3 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:border-transparent transition-all font-medium"
                />
              </div>
            </div>

            <!-- Password Field -->
            <div class="space-y-1.5">
              <label for="password-input" class="text-xs font-bold text-slate-700 block">
                Password
              </label>
              <div class="relative">
                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm">🔒</span>
                <input
                  id="password-input"
                  :type="showPassword ? 'text' : 'password'"
                  v-model="form.password"
                  placeholder="Enter your password"
                  required
                  class="w-full bg-white border border-slate-300 rounded-xl pl-10 pr-10 py-3 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:border-transparent transition-all font-medium"
                />
                <button
                  type="button"
                  @click="showPassword = !showPassword"
                  class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 text-xs font-bold transition-colors"
                >
                  {{ showPassword ? '🙈' : '👁️' }}
                </button>
              </div>
            </div>

            <!-- Remember Me & Forgot Password -->
            <div class="flex items-center justify-between text-xs pt-1">
              <label class="flex items-center gap-2 font-medium text-slate-600 cursor-pointer select-none">
                <input
                  type="checkbox"
                  v-model="rememberMe"
                  class="h-4 w-4 rounded border-slate-300 text-red-600 focus:ring-red-500"
                />
                Remember me
              </label>

              <button
                type="button"
                @click="showForgotToast = true"
                class="font-bold text-red-600 hover:underline"
              >
                Forgot Password?
              </button>
            </div>

            <!-- Forgot Password Toast -->
            <div v-if="showForgotToast" class="p-3 rounded-xl bg-red-50 border border-red-200 text-red-800 text-[11px] font-medium flex items-center justify-between">
              <span>Please contact your Super Administrator to reset your credentials.</span>
              <button type="button" @click="showForgotToast = false" class="text-red-900 font-bold ml-2">✕</button>
            </div>

            <!-- Large Primary LOGIN Button (NO OTP SECTION BELOW) -->
            <button
              type="submit"
              :disabled="loading"
              class="w-full py-3.5 rounded-xl bg-red-600 hover:bg-red-700 text-white font-extrabold text-xs tracking-wider uppercase shadow-lg shadow-red-600/30 transition-all flex items-center justify-center gap-2 disabled:opacity-50 mt-2"
            >
              <span v-if="loading" class="inline-block animate-spin">⏳</span>
              <span v-else>🔒 LOGIN</span>
            </button>
          </form>
        </div>

        <!-- Security Notice at Bottom of Login Card -->
        <div class="bg-red-50/80 p-4 border-t border-red-100 flex items-center gap-3 rounded-b-3xl">
          <div class="h-8 w-8 rounded-full bg-red-100 text-red-600 flex items-center justify-center font-bold text-sm shrink-0">
            🛡️
          </div>
          <div>
            <div class="text-xs font-extrabold text-slate-900">Secure Admin Access</div>
            <div class="text-[10px] text-slate-500 font-medium">All activities are monitored and protected</div>
          </div>
        </div>

      </div>

      <!-- Return to Public Website & Copyright Footer -->
      <div class="w-full text-center py-4 space-y-2">
        <a href="/" class="text-xs font-bold text-slate-500 hover:text-red-600 transition-colors inline-block">
          ← Return to Public Website
        </a>
        <div class="text-[11px] text-slate-400 font-medium">
          © 2026 <span class="font-bold text-slate-600">RUPSA PADUKALAYA</span>. All rights reserved.
        </div>
      </div>

    </div>

  </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useAuth } from '../../composables/useAuth';
import { useRouter } from 'vue-router';
import ErrorAlert from '../../components/ui/ErrorAlert.vue';

const { login } = useAuth();
const router = useRouter();

const form = reactive({
  login: '',
  password: '',
});

const showPassword = ref(false);
const rememberMe = ref(true);
const showForgotToast = ref(false);
const loading = ref(false);
const error = ref('');

async function handleLogin() {
  loading.value = true;
  error.value = '';
  try {
    const res = await login({
      login: form.login.trim(),
      password: form.password,
      device_name: 'Admin Web Portal',
    });
    if (res && res.success) {
      router.push('/admin/dashboard');
    }
  } catch (err) {
    error.value = err.message || 'Invalid email address or password.';
  } finally {
    loading.value = false;
  }
}
</script>
