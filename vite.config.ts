import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve, dirname } from 'path'
import { fileURLToPath } from 'url'

const __filename = fileURLToPath(import.meta.url)
const __dirname = dirname(__filename)

export default defineConfig({
  plugins: [vue()],
  build: {
    lib: {
      entry: resolve(__dirname, 'resources/js/index.js'),
      name: 'LaraviltPanel',
      formats: ['es', 'umd'],
      fileName: (format) => `panel.${format}.js`,
    },
    rollupOptions: {
      external: [
        'vue',
        '@inertiajs/vue3',
        'radix-vue',
        'reka-ui',
        'lucide-vue-next',
        'tailwind-merge',
        'clsx',
        'class-variance-authority',
        '@vueuse/core',
        /@laravilt\/.*/,
        // External app-level imports (provided by consuming app)
        /^@\/components\/.*/,
        /^@\/routes.*/,
        /^@\/composables\/.*/,
        /^@\/lib\/.*/,
      ],
      output: {
        globals: {
          vue: 'Vue',
          '@inertiajs/vue3': 'InertiaVue3',
          'radix-vue': 'RadixVue',
          'reka-ui': 'RekaUI',
          'lucide-vue-next': 'LucideVueNext',
          'tailwind-merge': 'TailwindMerge',
          clsx: 'clsx',
          'class-variance-authority': 'ClassVarianceAuthority',
          '@vueuse/core': 'VueUseCore',
        },
        assetFileNames: (assetInfo) => {
          if (assetInfo.name === 'style.css') return 'style.css'
          return assetInfo.name as string
        },
      },
    },
    outDir: 'dist',
    emptyOutDir: true,
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, 'resources/js'),
    },
  },
})
