<template>
  <nav
    v-if="groups.length > 0"
    aria-label="Children Ministry"
    class="ministry-navigation"
  >
    <div class="ministry-navigation__compact">
      <v-menu
        location="bottom start"
        max-height="min(70vh, 560px)"
        :max-width="360"
        :offset="8"
      >
        <template #activator="{ props: menu }">
          <v-btn
            v-bind="menu"
            append-icon="mdi-chevron-down"
            aria-label="Choose Children Ministry section"
            class="ministry-navigation__current"
            :prepend-icon="current?.icon || 'mdi-sprout-outline'"
            variant="text"
          >
            <span>
              <small>CHILDREN MINISTRY</small>
              <strong>{{ current?.title || 'Explore ministry' }}</strong>
            </span>
          </v-btn>
        </template>

        <v-list
          aria-label="Children Ministry sections"
          class="ministry-navigation__menu"
          elevation="6"
          rounded="xl"
        >
          <template
            v-for="group in groups"
            :key="group.title"
          >
            <v-list-subheader>{{ group.title }}</v-list-subheader>

            <v-list-item
              v-for="item in group.items"
              :key="item.key"
              :active="item.key === active"
              :aria-current="item.key === active ? 'page' : undefined"
              :exact="true"
              :prepend-icon="item.icon"
              :title="item.title"
              :to="item.to"
            />
          </template>
        </v-list>
      </v-menu>
    </div>

    <div class="ministry-navigation__desktop">
      <router-link
        v-for="item in discover"
        :key="item.key"
        :aria-current="item.key === active ? 'page' : undefined"
        class="ministry-navigation__link"
        :class="{ 'is-active': item.key === active }"
        :to="item.to"
      >
        <v-icon
          :icon="item.icon"
          size="18"
        />
        {{ item.title }}
      </router-link>

      <v-menu
        v-for="group in sections"
        :key="group.title"
        location="bottom start"
        :offset="8"
      >
        <template #activator="{ props: menu }">
          <v-btn
            v-bind="menu"
            append-icon="mdi-chevron-down"
            :aria-label="`${group.title} navigation`"
            class="ministry-navigation__group"
            :class="{ 'is-active': isCurrent(group) }"
            :prepend-icon="group.icon"
            variant="text"
          >
            {{ isCurrent(group) ? current?.title : group.title }}
          </v-btn>
        </template>

        <v-list
          :aria-label="group.title"
          class="ministry-navigation__menu"
          elevation="6"
          rounded="xl"
        >
          <v-list-subheader>{{ group.title }}</v-list-subheader>

          <v-list-item
            v-for="item in group.items"
            :key="item.key"
            :active="item.key === active"
            :aria-current="item.key === active ? 'page' : undefined"
            :exact="true"
            :prepend-icon="item.icon"
            :title="item.title"
            :to="item.to"
          />
        </v-list>
      </v-menu>
    </div>
  </nav>
</template>
<script setup lang="ts">
  import { computed } from 'vue'
  import { useAuthStore } from '@/stores/auth'
  import {
    ministryNavigation,
    type MinistryNavigationGroup,
  } from '../navigation'
  const props = defineProps<{ active: string }>()
  const auth = useAuthStore()
  const groups = computed(() =>
    ministryNavigation(permission => auth.hasPermission(permission)),
  )
  const current = computed(() =>
    groups.value
      .flatMap(group => group.items)
      .find(item => item.key === props.active),
  )
  const discover = computed(
    () => groups.value.find(group => group.title === 'Discover')?.items || [],
  )
  const sections = computed(() =>
    groups.value.filter(group => group.title !== 'Discover'),
  )
  function isCurrent (group: MinistryNavigationGroup) {
    return group.items.some(item => item.key === props.active)
  }
</script>
<style scoped>
  .ministry-navigation {
    margin-bottom: 24px;
    padding: 8px 0 14px;
    border-bottom: 1px solid #e5e9e6;
    min-width: 0;
  }
  .ministry-navigation__desktop {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 5px;
  }
  .ministry-navigation__compact {
    display: none;
  }
  .ministry-navigation__link,
  .ministry-navigation__group {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    border-radius: 10px;
    color: #718176;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0;
    text-transform: none;
    height: 42px;
  }
  .ministry-navigation .is-active {
    color: #365b43;
    background: #eaf0e9;
  }
  .ministry-navigation__menu {
    padding: 8px;
    border: 1px solid #e3eae3;
    min-width: 260px;
  }
  .ministry-navigation__menu :deep(.v-list-subheader) {
    min-height: 30px;
    color: #81917f;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
  }
  .ministry-navigation__menu :deep(.v-list-item) {
    border-radius: 10px;
    min-height: 44px;
  }
  .ministry-navigation__menu :deep(.v-list-item-title) {
    font-size: 13px;
  }
  .ministry-navigation__menu :deep(.v-icon) {
    font-size: 19px;
    color: #62806b;
  }
  .ministry-navigation__current {
    width: 100%;
    max-width: 100%;
    height: 62px;
    justify-content: flex-start;
    background: #edf2eb;
    border: 1px solid #e2e9df;
    border-radius: 14px;
    color: #3b5d46;
    text-transform: none;
    letter-spacing: 0;
  }
  .ministry-navigation__current :deep(.v-btn__content) {
    min-width: 0;
    justify-content: flex-start;
    flex: 1;
    text-align: left;
  }
  .ministry-navigation__current span {
    min-width: 0;
    overflow: hidden;
  }
  .ministry-navigation__current small {
    display: block;
    font-size: 9px;
    letter-spacing: 1.4px;
    color: #7c8f79;
    margin-bottom: 5px;
  }
  .ministry-navigation__current strong {
    display: block;
    font-weight: 600;
    font-size: 13px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  @media (max-width: 1199px) {
    .ministry-navigation__desktop {
      display: none;
    }
    .ministry-navigation__compact {
      display: block;
    }
  }
</style>
