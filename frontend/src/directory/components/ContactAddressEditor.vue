<template>
  <v-dialog
    v-model="open"
    max-width="640"
    persistent
    scrollable
  ><v-card class="directory-form">
    <div class="directory-form__header">
      <div>
        <span class="directory-eyebrow">PERSON PROFILE</span>
        <h2>{{ mode === 'contact' ? 'Stay connected' : 'Add an address' }}</h2>

        <p>
          {{
            mode === 'contact'
              ? 'A reliable way to reach them.'
              : 'A place to keep in touch, without changing their household.'
          }}
        </p>
      </div>

      <v-btn
        aria-label="Close form"
        :disabled="task.busy.value"
        icon="mdi-close"
        variant="text"
        @click="open = false"
      />
    </div>

    <v-card-text><TaskAlert :conflict="task.conflict.value" :error="task.error.value" @reload="refresh" />

      <v-form id="contact-address-editor" ref="formRef" @submit.prevent="save">
        <template v-if="mode === 'contact'">
          <v-select
            v-model="contact.kind"
            :items="[
              { title: 'Email address', value: 'EMAIL' },
              { title: 'Phone number', value: 'PHONE' },
            ]"
            label="Contact method"
          />

          <v-text-field
            v-model="contact.value"
            :label="contact.kind === 'EMAIL' ? 'Email address *' : 'Phone number *'"
            :maxlength="contact.kind === 'EMAIL' ? 254 : 40"
            :rules="[required]"
            :type="contact.kind === 'EMAIL' ? 'email' : 'tel'"
          />

          <v-switch
            v-model="contact.primary"
            color="primary"
            hint="Replaces the current preferred email or phone. Shared family contact details are welcome."
            label="Preferred for this contact method"
            persistent-hint
          />
        </template>

        <template v-else>
          <v-text-field
            v-model="address.line1"
            autocomplete="address-line1"
            label="Street address *"
            maxlength="200"
            :rules="[required]"
          />

          <v-text-field
            v-model="address.line2"
            autocomplete="address-line2"
            label="Apartment, building, or area"
            maxlength="200"
          />

          <v-row><v-col
                   cols="12"
                   sm="6"
                 ><v-text-field
                   v-model="address.city"
                   label="City *"
                   maxlength="100"
                   :rules="[required]"
                 /></v-col>

            <v-col
              cols="12"
              sm="6"
            ><v-text-field
              v-model="address.region"
              label="Province / region"
              maxlength="100"
            /></v-col>

            <v-col
              cols="12"
              sm="6"
            ><v-text-field
              v-model="address.postalCode"
              label="Postal code"
              maxlength="20"
            /></v-col>

            <v-col
              cols="12"
              sm="6"
            ><v-text-field
              v-model="address.countryCode"
              label="Country code *"
              maxlength="2"
              placeholder="ZA"
              :rules="[
                (value) => /^[a-z]{2}$/i.test(value) || 'Use a two-letter country code, such as ZA.',
              ]"
            /></v-col></v-row>
        </template>
      </v-form>
    </v-card-text>

    <div class="directory-form__footer">
      <span>* Required fields</span>

      <div>
        <v-btn :disabled="task.busy.value" variant="text" @click="open = false">Cancel</v-btn>

        <v-btn
          color="primary"
          :disabled="task.conflict.value"
          form="contact-address-editor"
          :loading="task.busy.value"
          type="submit"
          variant="flat"
        >{{ mode === 'contact' ? 'Add contact' : 'Add address' }}</v-btn>
      </div>
    </div>
  </v-card></v-dialog>
</template>
<script setup lang="ts">
  import type { PersonProfile } from '../types'
  import { reactive, ref, watch } from 'vue'
  import { useUiStore } from '@/stores/ui'
  import { directoryApi } from '../api'
  import { useTask } from '../useTask'
  import TaskAlert from './TaskAlert.vue'
  const open = defineModel<boolean>({ required: true })
  const props = defineProps<{ person: PersonProfile, mode: 'contact' | 'address' }>()
  const emit = defineEmits<{ saved: [], reload: [] }>()
  const task = useTask()
  const baseline = ref<PersonProfile>(props.person)
  const ui = useUiStore()
  const formRef = ref<{ validate: () => Promise<{ valid: boolean }> }>()
  const contact = reactive({ kind: 'EMAIL' as 'EMAIL' | 'PHONE', value: '', primary: false })
  const address = reactive({ line1: '', line2: '', city: '', region: '', postalCode: '', countryCode: 'ZA' })
  const required = (value: string) => Boolean(value.trim()) || 'This field is required.'
  watch(open, value => {
    if (value) {
      task.clear()
      baseline.value = props.person
      Object.assign(contact, { kind: 'EMAIL', value: '', primary: false })
      Object.assign(address, { line1: '', line2: '', city: '', region: '', postalCode: '', countryCode: 'ZA' })
    }
  })
  function refresh () {
    open.value = false
    emit('reload')
  }
  async function save () {
    if (!(await formRef.value?.validate())?.valid) return
    const result = await task.run(() =>
      props.mode === 'contact'
        ? directoryApi.addContact(baseline.value, { ...contact, value: contact.value.trim() })
        : directoryApi.addAddress(baseline.value, {
          ...address,
          countryCode: address.countryCode.toUpperCase(),
        }),
    )
    if (result) {
      open.value = false
      ui.notifySuccess(props.mode === 'contact' ? 'Contact added.' : 'Address added.')
      emit('saved')
    }
  }
</script>
