<template>
  <MinistryDialog
    v-model="open"
    :save-label="mode === 'policy' ? 'Publish policy' : 'Save & continue'"
    :submit="save"
    :subtitle="subtitle"
    :title="title"
    @reload="$emit('reload')"
    @saved="$emit('saved')"
  >
    <template v-if="mode === 'programme'">
      <div class="directory-section-heading">
        <span>01</span>

        <div>
          <h3>A place to belong</h3>
          <p>Give this programme a name families will recognise.</p>
        </div>
      </div>

      <v-text-field
        v-model="name"
        label="Programme name *"
        maxlength="120"
        :rules="[required]"
      />

      <v-row>
        <v-col
          cols="12"
          sm="6"
        >
          <v-text-field
            v-model.number="minimum"
            label="Minimum staff present *"
            min="1"
            :rules="[positive]"
            type="number"
          />
        </v-col>

        <v-col
          cols="12"
          sm="6"
        >
          <v-text-field
            v-model.number="ratio"
            label="Children per staff member *"
            min="1"
            :rules="[positive]"
            type="number"
          />
        </v-col>
      </v-row>

      <p class="children-form-note">
        Every open session needs a qualified leader. These staffing limits
        determine how many children can safely arrive.
      </p>
    </template>

    <template v-else-if="mode === 'class'">
      <v-text-field
        v-model="name"
        label="Class name *"
        maxlength="120"
        :rules="[required]"
      />

      <div class="children-form-note">
        Choose the age range in months. Children can join from the minimum age
        until the month they reach the upper age.
      </div>

      <v-row>
        <v-col cols="6">
          <v-text-field
            v-model.number="minimumAge"
            label="From age (months) *"
            max="215"
            min="0"
            :rules="[(v) => Number(v) >= 0 || 'Enter zero or more.']"
            type="number"
          />
        </v-col>

        <v-col cols="6">
          <v-text-field
            v-model.number="maximumAge"
            label="Until age (months) *"
            max="216"
            min="1"
            :rules="[positive]"
            type="number"
          />
        </v-col>
      </v-row>
    </template>

    <template v-else>
      <div class="children-form-note">
        Publishing a new policy requires families to give fresh consent before
        future check-in. Previous consent stays in the history.
      </div>

      <v-text-field
        v-model="name"
        label="Policy version name *"
        maxlength="40"
        placeholder="Family consent · 2026"
        :rules="[required]"
      />

      <v-text-field
        v-model="reference"
        label="Policy document link or reference *"
        maxlength="255"
        :rules="[required]"
      />

      <v-file-input
        v-model="file"
        hint="Choose the document held at that reference. Files stay in your existing document storage."
        label="Policy document *"
        persistent-hint
        :rules="[required]"
        show-size
      />
    </template>
  </MinistryDialog>
</template>
<script setup lang="ts">
  import type { Programme } from '../types'
  import { computed, ref, watch } from 'vue'
  import { childrenApi } from '../api'
  import { documentHash, positive, required } from '../presentation'
  import MinistryDialog from './MinistryDialog.vue'
  const open = defineModel<boolean>({ required: true })
  const props = defineProps<{
    mode: 'programme' | 'class' | 'policy'
    programme?: Programme | null
  }>()
  defineEmits<{ saved: [], reload: [] }>()
  const name = ref('')
  const minimum = ref(2)
  const ratio = ref(6)
  const minimumAge = ref(0)
  const maximumAge = ref(144)
  const reference = ref('')
  const file = ref<File | File[] | null>(null)
  const title = computed(
    () =>
      ({
        programme: 'A new programme',
        class: 'Create a class',
        policy: 'Publish a consent policy',
      })[props.mode],
  )
  const subtitle = computed(
    () =>
      props.programme?.name
      || 'Create a welcoming space, with thoughtful care built in.',
  )
  watch(open, value => {
    if (value) {
      name.value = ''
      reference.value = ''
      file.value = null
      minimum.value = 2
      ratio.value = 6
      minimumAge.value = 0
      maximumAge.value = 144
    }
  })
  async function save () {
    if (props.mode === 'programme')
      return childrenApi.createProgramme({
        name: name.value,
        minStaff: minimum.value,
        childrenPerStaff: ratio.value,
      })
    if (!props.programme) throw new Error('Choose a programme first.')
    if (props.mode === 'class')
      return childrenApi.createClass({
        programmeId: props.programme.id,
        name: name.value,
        minAgeMonths: minimumAge.value,
        maxAgeMonths: maximumAge.value,
      })
    const document = Array.isArray(file.value) ? file.value[0] : file.value
    if (!document) throw new Error('Choose the policy document first.')
    return childrenApi.publishPolicy({
      programmeId: props.programme.id,
      expectedVersion: props.programme.version,
      label: name.value,
      documentReference: reference.value,
      contentHash: await documentHash(document),
    })
  }
</script>
