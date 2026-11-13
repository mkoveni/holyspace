<template>
  <v-dialog
    v-model="open"
    class="command-palette"
    max-width="620"
    scrollable
    transition="dialog-top-transition"
    @after-enter="focusInput"
  >
    <v-card>
      <div class="command-palette__search">
        <v-icon icon="mdi-magnify" />

        <input
          ref="input"
          v-model="query"
          :aria-expanded="results.length > 0"
          aria-label="Search pages, people and actions"
          autocomplete="off"
          placeholder="Search pages, people or actions…"
          role="combobox"
          @keydown.down.prevent="move(1)"
          @keydown.enter.prevent="choose(results[active])"
          @keydown.up.prevent="move(-1)"
        >

        <kbd>Esc</kbd>
      </div>

      <v-divider />

      <ul
        v-if="results.length > 0"
        class="command-palette__list"
        role="listbox"
      >
        <li
          v-for="(entry, index) in results"
          :key="entry.id"
          :aria-selected="index === active"
          class="command-palette__item"
          :class="{ 'command-palette__item--active': index === active }"
          role="option"
          @click="choose(entry)"
          @mousemove="active = index"
        >
          <v-icon
            :icon="entry.icon"
            size="20"
          />

          <span class="command-palette__text">
            <strong>{{ entry.title }}</strong>
            <small>{{ entry.subtitle }}</small>
          </span>

          <span class="command-palette__kind">{{ entry.kind }}</span>
        </li>
      </ul>

      <div
        v-else
        class="empty-state"
      >
        <div class="empty-state__icon">
          <v-icon
            icon="mdi-text-search"
            size="28"
          />
        </div>

        <h3>No matches</h3>
        <p>Try a person’s name, or a page like “giving” or “volunteers”.</p>
      </div>

      <div class="command-palette__hint">
        <span><kbd>↑</kbd><kbd>↓</kbd> to move</span>
        <span><kbd>Enter</kbd> to open</span>
      </div>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
  import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
  import { useRouter } from 'vue-router'
  import { directoryApi } from '@/directory/api'
  import { fullName } from '@/directory/presentation'
  import {
    actionEntries,
    filterEntries,
    pageEntries,
    type PaletteEntry,
  } from '@/navigation/commandPalette'
  import { visibleQuickActions } from '@/navigation/quickActions'
  import { useAuthStore } from '@/stores/auth'

  const open = defineModel<boolean>({ default: false })
  const auth = useAuthStore(),
        router = useRouter()
  const query = ref(''),
        active = ref(0),
        input = ref<HTMLInputElement | null>(null)

  const staticEntries = computed(() => [
    ...actionEntries(visibleQuickActions(permission => auth.hasPermission(permission))),
    ...pageEntries(permission => auth.hasPermission(permission)),
  ])
  // People come from the directory's server-side search, so the palette scales with the church.
  const peopleEntries = ref<PaletteEntry[]>([])
  let searchTimer: ReturnType<typeof setTimeout> | undefined
  let searchGeneration = 0
  function searchPeople (text: string) {
    clearTimeout(searchTimer)
    const needle = text.trim()
    if (needle.length < 2 || !auth.hasPermission('people.read')) {
      peopleEntries.value = []
      return
    }
    const generation = ++searchGeneration
    searchTimer = setTimeout(async () => {
      try {
        const found = await directoryApi.people({ search: needle })
        if (generation !== searchGeneration) return
        peopleEntries.value = found.map(person => ({
          id: `person:${person.id}`,
          title: fullName(person),
          subtitle: 'Person',
          icon: 'mdi-account-outline',
          to: `/people/${person.id}`,
          kind: 'Person' as const,
        }))
      } catch {
        if (generation === searchGeneration) peopleEntries.value = []
      }
    }, 200)
  }
  const results = computed(() => {
    const matches = filterEntries(staticEntries.value, query.value, 8)
    // People only appear once the person starts typing; the server already matched them.
    return query.value.trim() ? [...matches, ...peopleEntries.value.slice(0, 5)] : matches
  })

  watch(query, value => {
    active.value = 0
    searchPeople(value)
  })
  watch(open, async value => {
    if (!value) return
    query.value = ''
    active.value = 0
    peopleEntries.value = []
  })

  function move (step: number) {
    const count = results.value.length
    if (count > 0) active.value = (active.value + step + count) % count
  }
  function choose (entry?: PaletteEntry) {
    if (!entry) return
    open.value = false
    void router.push(entry.to)
  }
  function focusInput () {
    void nextTick(() => input.value?.focus())
  }
  function onKeydown (event: KeyboardEvent) {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault()
      open.value = !open.value
    }
  }
  onMounted(() => globalThis.addEventListener('keydown', onKeydown))
  onBeforeUnmount(() => globalThis.removeEventListener('keydown', onKeydown))
</script>

<style scoped>
  .command-palette :deep(.v-overlay__content) {
    align-self: flex-start;
    margin-top: 12vh;
  }
  .command-palette__search {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
  }
  .command-palette__search input {
    flex: 1;
    min-width: 0;
    background: transparent;
    border: 0;
    outline: 0;
    font-size: 16px;
    color: var(--workspace-ink);
  }
  .command-palette__list {
    list-style: none;
    margin: 0;
    padding: 8px;
    max-height: 52vh;
    overflow-y: auto;
  }
  .command-palette__item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 10px 12px;
    border-radius: 12px;
    cursor: pointer;
    color: var(--workspace-muted);
  }
  .command-palette__item--active {
    background: var(--workspace-tint);
    color: var(--workspace-blue);
  }
  .command-palette__text {
    flex: 1;
    min-width: 0;
  }
  .command-palette__text strong {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: var(--workspace-ink);
  }
  .command-palette__text small {
    font-size: 12px;
    color: var(--workspace-muted);
  }
  .command-palette__kind {
    font-size: 11px;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    color: var(--workspace-muted);
  }
  .command-palette__hint {
    display: flex;
    gap: 18px;
    padding: 10px 20px;
    border-top: 1px solid var(--workspace-border);
    background: var(--workspace-soft);
    font-size: 12px;
    color: var(--workspace-muted);
  }
  kbd {
    display: inline-block;
    min-width: 20px;
    margin-right: 4px;
    padding: 1px 6px;
    border: 1px solid var(--workspace-border);
    border-radius: 6px;
    background: var(--workspace-paper);
    font: 600 11px 'Manrope', sans-serif;
    text-align: center;
  }
</style>
