<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="WORDS THAT BRING PEOPLE CLOSER"
      subtitle="Create a consistent voice with reusable email templates, personal variables and carefully published revisions."
      title="Thoughtful messages, every time."
    >
      <v-btn
        v-if="canManage"
        color="primary"
        prepend-icon="mdi-plus"
        @click="create"
      >
        New email template
      </v-btn>
    </DirectoryHeading>

    <div class="directory-banner directory-banner--sage mb-6">
      <div>
        <span class="directory-banner__icon">
          <v-icon icon="mdi-email-edit-outline" />
        </span>

        <div>
          <strong>A little preparation. A more personal message.</strong>

          <p>
            Work on drafts at your own pace. Publish when ready. Messages
            already approved for delivery keep their exact wording.
          </p>
        </div>
      </div>
    </div>

    <v-alert
      v-if="error"
      class="mb-5"
      type="error"
      variant="tonal"
    >
      {{ error }}
    </v-alert>

    <v-alert
      v-if="notice"
      class="mb-5"
      closable
      type="success"
      variant="tonal"
      @click:close="notice = ''"
    >
      {{ notice }}
    </v-alert>

    <section class="directory-surface template-search mb-5">
      <v-text-field
        v-model="search"
        clearable
        hide-details
        label="Find a template"
        prepend-inner-icon="mdi-magnify"
        @click:clear="clearSearch"
        @keyup.enter="applySearch"
      />

      <v-btn
        :loading="loading"
        variant="tonal"
        @click="applySearch"
      >
        Search
      </v-btn>

      <v-btn
        aria-label="Refresh templates"
        icon="mdi-refresh"
        :loading="loading"
        variant="text"
        @click="load"
      />
    </section>

    <v-progress-linear
      v-if="loading"
      color="primary"
      indeterminate
    />

    <section
      aria-label="Email templates"
      class="template-grid"
    >
      <article
        v-for="template in templates"
        :key="template.id"
        class="directory-surface template-card"
      >
        <div class="template-card__top">
          <span class="template-card__icon">
            <v-icon icon="mdi-email-outline" />
          </span>

          <v-chip
            :color="
              template.status === 'PUBLISHED'
                ? 'success'
                : template.status === 'DRAFT'
                  ? 'warning'
                  : undefined
            "
            size="small"
          >
            {{ statusLabel(template.status) }}
          </v-chip>
        </div>

        <h2>{{ template.name }}</h2>
        <p class="template-card__subject">{{ template.subject }}</p>
        <p class="directory-muted">{{ template.key }}</p>

        <div class="template-card__variables">
          <v-chip
            v-for="variable in template.variables"
            :key="variable"
            prepend-icon="mdi-code-braces"
            size="small"
            variant="tonal"
          >
            {{ variable }}
          </v-chip>

          <span
            v-if="template.variables.length === 0"
            class="directory-muted"
          >
            Ready-to-use wording
          </span>
        </div>

        <div class="template-card__footer">
          <small>
            {{
              template.publishedVersion == null
                ? 'No published revision'
                : `Published revision ${template.publishedVersion + 1}`
            }}
          </small>

          <v-btn
            append-icon="mdi-arrow-top-right"
            variant="text"
            @click="openTemplate(template.id)"
          >
            {{
              canManage && template.status !== 'ARCHIVED'
                ? 'Open editor'
                : 'View template'
            }}
          </v-btn>
        </div>
      </article>

      <div
        v-if="!loading && templates.length === 0"
        class="directory-surface template-empty"
      >
        <v-icon
          icon="mdi-email-plus-outline"
          size="40"
        />

        <h2>
          {{
            appliedSearch
              ? 'No matching templates'
              : 'Make room for a great message'
          }}
        </h2>

        <p>
          {{
            appliedSearch
              ? 'Try another name or template key.'
              : 'Create your first email template and give every message a considered starting point.'
          }}
        </p>
      </div>
    </section>

    <div class="template-pagination">
      <p class="directory-muted">{{ total }} templates</p>

      <v-btn
        :disabled="offset === 0 || loading"
        variant="text"
        @click="page(-25)"
      >
        Previous
      </v-btn>

      <v-btn
        :disabled="offset + 25 >= total || loading"
        variant="text"
        @click="page(25)"
      >
        Next
      </v-btn>
    </div>

    <v-dialog
      v-model="editorOpen"
      max-width="1240"
      persistent
      scrollable
    >
      <v-card
        class="template-editor"
        rounded="xl"
      >
        <div class="template-editor__heading">
          <div>
            <span class="directory-eyebrow">COMMUNICATIONS STUDIO</span>
            <h2>{{ selected ? selected.name : 'A new beginning' }}</h2>
            <p>Shape the message. Add a personal touch.</p>
          </div>

          <v-btn
            aria-label="Close template editor"
            :disabled="busy"
            icon="mdi-close"
            variant="text"
            @click="closeEditor"
          />
        </div>

        <v-card-text>
          <v-alert
            v-if="editorError"
            class="mb-4"
            type="error"
            variant="tonal"
          >
            {{ editorError }}
          </v-alert>

          <v-alert
            v-if="
              selected?.status === 'DRAFT' && selected.publishedVersion != null
            "
            class="mb-4"
            type="info"
            variant="tonal"
          >
            Your previous published revision is still in use. Publish this draft
            when you are ready to replace it.
          </v-alert>

          <v-alert
            v-if="draft.key.startsWith('children.')"
            class="mb-4"
            type="info"
            variant="tonal"
          >
            Keep the message variable in the body. It contains the required
            ministry wording. Personalize the introduction and closing around
            it.
          </v-alert>

          <div class="template-editor__columns">
            <section aria-label="Template editor">
              <div class="template-fields">
                <v-text-field
                  v-model="draft.name"
                  :disabled="readOnly || busy"
                  label="Template name"
                  maxlength="150"
                />

                <v-text-field
                  v-model="draft.key"
                  :disabled="!!selected || readOnly || busy"
                  hint="A permanent reference used by the sending workflow."
                  label="Unique template key"
                  persistent-hint
                  placeholder="welcome.new-member"
                />
              </div>

              <v-text-field
                v-model="draft.subject"
                counter
                :disabled="readOnly || busy"
                label="Email subject"
                maxlength="200"
              />

              <v-combobox
                v-model="draft.variables"
                chips
                class="mb-5"
                closable-chips
                :disabled="readOnly || busy"
                hint="Type a name and press Enter. Use the insert menu to place it in the message."
                label="Variables"
                multiple
                persistent-hint
              />

              <label class="template-label">Email body</label>

              <RichTextEditor
                v-model="draft.html"
                :disabled="readOnly || busy"
                :variables="draft.variables"
              />
            </section>

            <aside
              aria-label="Preview settings"
              class="template-preview-panel"
            >
              <div class="template-preview-panel__heading">
                <div>
                  <h3>A personal preview</h3>
                  <p>Try sample values. Nothing is sent.</p>
                </div>

                <v-icon icon="mdi-eye-outline" />
              </div>

              <v-textarea
                v-for="variable in draft.variables"
                :key="variable"
                v-model="values[variable]"
                auto-grow
                :disabled="busy"
                :label="`Sample ${variable}`"
                maxlength="5000"
                rows="2"
              />

              <v-btn
                block
                :disabled="busy"
                :loading="previewing"
                prepend-icon="mdi-eye-outline"
                variant="tonal"
                @click="preview"
              >
                Update preview
              </v-btn>

              <p
                v-if="previewStale && rendered"
                class="directory-muted mt-3"
              >
                The content or sample values changed. Update the preview to see
                your changes.
              </p>

              <template v-if="rendered">
                <p class="template-preview-subject">{{ rendered.subject }}</p>
                <EmailPreview :html="rendered.html" />
              </template>

              <div
                v-else
                class="template-preview-placeholder"
              >
                <v-icon
                  icon="mdi-email-open-outline"
                  size="34"
                />

                <p>Your finished message will appear here.</p>
              </div>
            </aside>
          </div>

          <section
            v-if="selected"
            class="template-history mt-6"
          >
            <div class="d-flex align-center ga-3">
              <h3>Published revisions</h3>

              <v-btn
                size="small"
                variant="text"
                @click="loadHistory"
              >
                Refresh history
              </v-btn>
            </div>

            <p
              v-if="history.length === 0"
              class="directory-muted"
            >
              This template has not been published yet.
            </p>

            <div
              v-for="revision in history"
              :key="revision.version"
              class="template-history__row"
            >
              <span>
                Revision {{ revision.version + 1 }} ·
                {{ new Date(revision.publishedAt).toLocaleString() }}
              </span>

              <v-btn
                size="small"
                variant="text"
                @click="showRevision(revision)"
              >
                View wording
              </v-btn>
            </div>

            <v-btn
              v-if="history.length > 0 && history.length % 25 === 0"
              variant="text"
              @click="moreHistory"
            >
              Load earlier revisions
            </v-btn>
          </section>
        </v-card-text>

        <v-card-actions class="template-editor__actions">
          <span class="directory-muted">
            {{
              dirty
                ? 'Unsaved changes'
                : selected
                  ? statusLabel(selected.status)
                  : 'New draft'
            }}
          </span>

          <v-spacer />

          <v-btn
            :disabled="busy"
            @click="closeEditor"
          >
            Close
          </v-btn>

          <v-btn
            v-if="selected && !readOnly"
            color="error"
            :disabled="busy || dirty"
            variant="text"
            @click="archiveOpen = true"
          >
            Archive
          </v-btn>

          <v-btn
            v-if="!readOnly"
            :disabled="!validDraft || (selected !== null && !dirty)"
            :loading="busy"
            variant="tonal"
            @click="save"
          >
            Save draft
          </v-btn>

          <v-btn
            v-if="selected?.status === 'DRAFT' && !readOnly"
            color="primary"
            :disabled="busy || dirty"
            variant="flat"
            @click="publishOpen = true"
          >
            Publish revision
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog
      v-model="discardOpen"
      max-width="420"
    >
      <v-card
        rounded="xl"
        title="Keep your changes?"
      >
        <v-card-text>You have unsaved changes to this template.</v-card-text>

        <v-card-actions>
          <v-btn @click="discardOpen = false">Keep editing</v-btn>

          <v-btn
            color="error"
            @click="discard"
          >
            Discard changes
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog
      v-model="publishOpen"
      max-width="460"
    >
      <v-card
        rounded="xl"
        title="Publish this revision?"
      >
        <v-card-text>
          New message reviews will use this wording. Already approved deliveries
          retain their original content.
        </v-card-text>

        <v-card-actions>
          <v-btn
            :disabled="busy"
            @click="publishOpen = false"
          >
            Cancel
          </v-btn>

          <v-btn
            color="primary"
            :loading="busy"
            @click="change('publish')"
          >
            Publish template
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog
      v-model="archiveOpen"
      max-width="460"
    >
      <v-card
        rounded="xl"
        title="Archive this template?"
      >
        <v-card-text>
          New messages cannot use this template. Already approved deliveries
          continue with their saved wording.
        </v-card-text>

        <v-card-actions>
          <v-btn
            :disabled="busy"
            @click="archiveOpen = false"
          >
            Cancel
          </v-btn>

          <v-btn
            color="error"
            :loading="busy"
            @click="change('archive')"
          >
            Archive template
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog
      v-model="revisionOpen"
      max-width="760"
      scrollable
    >
      <v-card
        rounded="xl"
        title="Published wording"
      >
        <v-card-text v-if="revisionView">
          <h3 class="mb-4">{{ revisionView.subject }}</h3>

          <EmailPreview
            :html="revisionView.html"
            title="Published template revision"
          />

          <p class="directory-muted mt-3">
            Variables are shown as placeholders in this saved revision.
          </p>
        </v-card-text>

        <v-card-actions>
          <v-btn @click="revisionOpen = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
<script setup lang="ts">
  import { computed, onMounted, reactive, ref, watch } from 'vue'
  import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
  import { extractErrorMessage } from '@/composables/apiErrors'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import { useAuthStore } from '@/stores/auth'
  import {
    type EmailTemplate,
    type RenderedEmail,
    type TemplateDraft,
    type TemplateRevision,
    templatesApi,
  } from '../api'
  import EmailPreview from '../components/EmailPreview.vue'
  import RichTextEditor from '../components/RichTextEditor.vue'
  import '@/directory/directory.scss'
  const auth = useAuthStore(),
        route = useRoute(),
        router = useRouter()
  const canManage = computed(() =>
    auth.hasPermission('communications.templates.manage'),
  )
  const templates = ref<EmailTemplate[]>([]),
        total = ref(0),
        offset = ref(0),
        search = ref(''),
        appliedSearch = ref('')
  const loading = ref(false),
        error = ref(''),
        notice = ref(''),
        editorError = ref(''),
        busy = ref(false),
        previewing = ref(false)
  const editorOpen = ref(false),
        selected = ref<EmailTemplate | null>(null),
        original = ref(''),
        discardOpen = ref(false),
        publishOpen = ref(false),
        archiveOpen = ref(false)
  const draft = reactive<TemplateDraft>({
    key: '',
    name: '',
    subject: '',
    html: '<p></p>',
    variables: [],
  })
  const values = reactive<Record<string, string>>({}),
        rendered = ref<RenderedEmail | null>(null),
        previewSource = ref('')
  const history = ref<TemplateRevision[]>([]),
        revisionOpen = ref(false),
        revisionView = ref<TemplateRevision | null>(null)
  const readOnly = computed(
    () => !canManage.value || selected.value?.status === 'ARCHIVED',
  )
  const dirty = computed(() => JSON.stringify(draft) !== original.value)
  const validDraft = computed(
    () =>
      draft.name.trim()
      && /^[a-z][a-z0-9.-]{2,99}$/.test(draft.key)
      && draft.subject.trim()
      && draft.html.length <= 50_000
      && draft.variables.length <= 30,
  )
  const previewStale = computed(
    () => previewSource.value !== JSON.stringify({ ...draft, values }),
  )
  function message (err: unknown) {
    return extractErrorMessage(err, 'We could not complete this request. Please try again.')
  }
  function statusLabel (status: string) {
    return status === 'PUBLISHED'
      ? 'Published'
      : (status === 'ARCHIVED'
        ? 'Archived'
        : 'Draft')
  }
  let request = 0
  async function load () {
    const current = ++request
    loading.value = true
    error.value = ''
    try {
      const result = await templatesApi.list(appliedSearch.value, offset.value)
      if (current === request) {
        templates.value = result.templates
        total.value = result.total
      }
    } catch (error_) {
      if (current === request) error.value = message(error_)
    } finally {
      if (current === request) loading.value = false
    }
  }
  function applySearch () {
    appliedSearch.value = search.value || ''
    offset.value = 0
    void load()
  }
  function clearSearch () {
    search.value = ''
    applySearch()
  }
  function page (amount: number) {
    offset.value += amount
    void load()
  }
  function reset (template: EmailTemplate | null) {
    selected.value = template
    Object.assign(
      draft,
      template
        ? {
          key: template.key,
          name: template.name,
          subject: template.subject,
          html: template.html,
          variables: [...template.variables],
        }
        : { key: '', name: '', subject: '', html: '<p></p>', variables: [] },
    )
    original.value = JSON.stringify(draft)
    editorError.value = ''
    rendered.value = null
    history.value = []
    for (const key of Object.keys(values)) delete values[key]
    for (const key of draft.variables)
      values[key]
        = key === 'message'
          ? 'Hello Alex, please contact the ministry team to review the arrangements for your child. Thank you.'
          : `Sample ${key}`
    editorOpen.value = true
  }
  function create () {
    reset(null)
  }
  async function openTemplate (id: string) {
    error.value = ''
    try {
      const template = await templatesApi.get(id)
      reset(template)
      await router.replace({ query: { ...route.query, template: id } })
      await loadHistory()
    } catch (error_) {
      error.value = message(error_)
    }
  }
  function closeEditor () {
    if (dirty.value && !readOnly.value) {
      discardOpen.value = true
      return
    }
    discard()
  }
  function discard () {
    discardOpen.value = false
    editorOpen.value = false
    void router.replace({ query: { ...route.query, template: undefined } })
  }
  watch(
    () => draft.variables.slice(),
    keys => {
      for (const key of keys) {
        if (values[key] === undefined) values[key] = ''
      }
      for (const key of Object.keys(values)) {
        if (!keys.includes(key)) delete values[key]
      }
    },
  )
  async function preview () {
    previewing.value = true
    editorError.value = ''
    const snapshot = JSON.stringify({ ...draft, values })
    try {
      const result = await templatesApi.preview(draft, values)
      if (snapshot === JSON.stringify({ ...draft, values })) {
        rendered.value = result
        previewSource.value = snapshot
      }
    } catch (error_) {
      editorError.value = message(error_)
    } finally {
      previewing.value = false
    }
  }
  async function save () {
    busy.value = true
    editorError.value = ''
    try {
      const saved = selected.value
        ? await templatesApi.update(
          selected.value.id,
          draft,
          selected.value.version,
        )
        : await templatesApi.create(draft)
      const template = await templatesApi.get(saved.id)
      reset(template)
      notice.value = 'Your draft is saved.'
      await router.replace({ query: { ...route.query, template: saved.id } })
      await Promise.all([load(), loadHistory()])
    } catch (error_) {
      editorError.value = message(error_)
    } finally {
      busy.value = false
    }
  }
  async function change (action: 'publish' | 'archive') {
    if (!selected.value) return
    busy.value = true
    editorError.value = ''
    try {
      await templatesApi.change(
        selected.value.id,
        action,
        selected.value.version,
      )
      reset(await templatesApi.get(selected.value.id))
      notice.value
        = action === 'publish'
          ? 'Your revision is published and ready for new messages.'
          : 'Your template is archived.'
      await Promise.all([load(), loadHistory()])
    } catch (error_) {
      editorError.value = message(error_)
    } finally {
      busy.value = false
      publishOpen.value = false
      archiveOpen.value = false
    }
  }
  async function loadHistory () {
    if (!selected.value) return
    try {
      history.value = await templatesApi.revisions(selected.value.id)
    } catch (error_) {
      editorError.value = message(error_)
    }
  }
  async function moreHistory () {
    if (!selected.value) return
    try {
      history.value.push(
        ...(await templatesApi.revisions(
          selected.value.id,
          history.value.length,
        )),
      )
    } catch (error_) {
      editorError.value = message(error_)
    }
  }
  function showRevision (revision: TemplateRevision) {
    revisionView.value = revision
    revisionOpen.value = true
  }
  onBeforeRouteLeave(() => {
    if (editorOpen.value && dirty.value && !readOnly.value) {
      discardOpen.value = true
      return false
    }
  })
  onMounted(async () => {
    await load()
    if (typeof route.query.template === 'string')
      await openTemplate(route.query.template)
  })
</script>
<style scoped>
  .template-search {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
  }
  .template-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 22px;
  }
  .template-card {
    padding: 26px;
    display: flex;
    flex-direction: column;
    gap: 14px;
  }
  .template-card__top,
  .template-card__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }
  .template-card__icon {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    background: #edf4ed;
    color: #3e6f53;
  }
  .template-card h2 {
    font-size: 21px;
    font-weight: 650;
    letter-spacing: -0.5px;
    color: #263f31;
  }
  .template-card__subject {
    font-size: 14px;
    color: #68796d;
    overflow-wrap: anywhere;
  }
  .template-card__variables {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    min-height: 32px;
  }
  .template-card__footer {
    margin-top: auto;
    border-top: 1px solid #edf0e9;
    padding-top: 16px;
    color: #738276;
  }
  .template-empty {
    grid-column: 1/-1;
    text-align: center;
    padding: 60px 24px;
    color: #617361;
  }
  .template-empty h2 {
    margin: 16px 0 8px;
    color: #304c3a;
  }
  .template-pagination {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 12px;
    margin: 24px 0;
  }
  .template-pagination p {
    margin-right: auto;
  }
  .template-editor__heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 28px 32px 22px;
    border-bottom: 1px solid #e7ece5;
    background: #f7f9f5;
  }
  .template-editor__heading h2 {
    font-size: 28px;
    font-weight: 650;
    letter-spacing: -0.8px;
    margin: 8px 0;
    color: #253f31;
  }
  .template-editor__heading p {
    color: #748171;
  }
  .template-editor__columns {
    display: grid;
    grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr);
    gap: 28px;
  }
  .template-fields {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
  }
  .template-label {
    display: block;
    margin-bottom: 10px;
    font-size: 14px;
    font-weight: 600;
    color: #415743;
  }
  .template-preview-panel {
    background: #f6f8f3;
    padding: 24px;
    border-radius: 20px;
  }
  .template-preview-panel__heading {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    color: #435e47;
    margin-bottom: 22px;
  }
  .template-preview-panel__heading p {
    font-size: 14px;
    color: #7b8776;
    margin-top: 6px;
  }
  .template-preview-placeholder {
    display: grid;
    place-items: center;
    text-align: center;
    padding: 64px 20px;
    color: #93a18c;
    gap: 12px;
  }
  .template-preview-subject {
    padding: 24px 4px 12px;
    font-weight: 600;
    color: #314e3b;
    overflow-wrap: anywhere;
  }
  .template-editor__actions {
    padding: 18px 28px;
    border-top: 1px solid #e6ece2;
    flex-wrap: wrap;
  }
  .template-history {
    border-top: 1px solid #e7ece4;
    padding-top: 20px;
  }
  .template-history__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 0;
    color: #6a7865;
    font-size: 14px;
    border-bottom: 1px solid #edf0e8;
  }
  @media (max-width: 850px) {
    .template-editor__columns {
      grid-template-columns: 1fr;
    }
    .template-fields {
      grid-template-columns: 1fr;
      gap: 0;
    }
    .template-editor__heading {
      padding: 20px;
    }
    .template-search {
      flex-wrap: wrap;
    }
    .template-search .v-input {
      min-width: 100%;
    }
    .template-grid {
      grid-template-columns: minmax(0, 1fr);
    }
  }
</style>
