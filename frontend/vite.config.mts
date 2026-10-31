import type { ProxyOptions } from 'vite'
import { fileURLToPath, URL } from 'node:url'
import Vue from '@vitejs/plugin-vue'
import { defineConfig, loadEnv } from 'vite'
import Vuetify, { transformAssetUrls } from 'vite-plugin-vuetify'

export default defineConfig(({ mode }) => {
  const environment = loadEnv(mode, process.cwd(), 'VITE_')
  const apiTarget
    = process.env.VITE_API_PROXY_TARGET ?? environment.VITE_API_PROXY_TARGET ?? 'http://localhost:8081'
  const proxy: ProxyOptions = {
    target: apiTarget,
    changeOrigin: true,
    configure: server => {
      server.on('proxyReq', (outgoing, incoming) => {
        // The Java development workspace uses a same-origin relay. Preserve foreign
        // origins so the API can still reject requests from untrusted sites.
        const origin = incoming.headers.origin
        if (
          environment.VITE_BACKEND === 'java'
          && (origin === `http://${incoming.headers.host}` || origin === `https://${incoming.headers.host}`)
        ) {
          outgoing.setHeader('Origin', new URL(apiTarget).origin)
        }
      })
    },
  }
  return {
    plugins: [
      Vue({ template: { transformAssetUrls } }),
      Vuetify({ autoImport: true, styles: { configFile: 'src/styles/settings.scss' } }),
    ],
    define: { 'process.env': {} },
    resolve: {
      alias: { '@': fileURLToPath(new URL('src', import.meta.url)) },
      extensions: ['.js', '.json', '.jsx', '.mjs', '.ts', '.tsx', '.vue'],
    },
    server: { proxy: { '/api': proxy, '/health': proxy } },
  }
})
