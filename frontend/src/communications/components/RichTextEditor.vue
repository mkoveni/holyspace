<template>
  <div
    class="rich-editor"
    :class="{ 'rich-editor--disabled': disabled }"
  >
    <div
      v-if="editor"
      aria-label="Email formatting"
      class="rich-editor__toolbar"
      role="toolbar"
    >
      <v-btn
        v-for="tool in tools"
        :key="tool.label"
        :aria-label="tool.label"
        :color="tool.active() ? 'primary' : undefined"
        :disabled="disabled"
        :icon="tool.icon"
        size="small"
        :title="tool.label"
        :variant="tool.active() ? 'tonal' : 'text'"
        @click="tool.run()"
      />

      <span class="rich-editor__separator" />

      <v-menu>
        <template #activator="{ props: menu }">
          <v-btn
            v-bind="menu"
            :disabled="disabled || variables.length === 0"
            prepend-icon="mdi-code-braces"
            size="small"
            variant="text"
          >
            Insert variable
          </v-btn>
        </template>

        <v-list aria-label="Template variables">
          <v-list-item
            v-for="variable in variables"
            :key="variable"
            prepend-icon="mdi-code-braces"
            :title="variable"
            @click="insertVariable(variable)"
          />
        </v-list>
      </v-menu>

      <v-btn
        aria-label="Insert link"
        :disabled="disabled"
        icon="mdi-link-variant"
        size="small"
        title="Insert link"
        variant="text"
        @click="openLink"
      />

      <v-btn
        aria-label="Undo"
        :disabled="disabled || !editor.can().undo()"
        icon="mdi-undo"
        size="small"
        variant="text"
        @click="editor.chain().focus().undo().run()"
      />

      <v-btn
        aria-label="Redo"
        :disabled="disabled || !editor.can().redo()"
        icon="mdi-redo"
        size="small"
        variant="text"
        @click="editor.chain().focus().redo().run()"
      />
    </div>

    <EditorContent :editor="editor" />

    <div class="rich-editor__footer">
      Use variables for personal details. Links, headings and lists are
      supported.
    </div>

    <v-dialog
      v-model="linkOpen"
      max-width="460"
    >
      <v-card
        rounded="xl"
        title="Add a link"
      >
        <v-card-text>
          <v-text-field
            v-model="link"
            label="Link address"
            placeholder="https://example.org"
          />

          <p class="text-body-2">Use a full https, http or mailto address.</p>
        </v-card-text>

        <v-card-actions>
          <v-btn @click="linkOpen = false">Cancel</v-btn>

          <v-btn
            :disabled="!validLink"
            variant="tonal"
            @click="applyLink"
          >
            Apply link
          </v-btn>

          <v-btn
            v-if="editor?.isActive('link')"
            @click="removeLink"
          >
            Remove
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
<script setup lang="ts">
  import StarterKit from '@tiptap/starter-kit'
  import { EditorContent, useEditor } from '@tiptap/vue-3'
  import { computed, onBeforeUnmount, ref, watch } from 'vue'
  const props = withDefaults(
    defineProps<{
      modelValue: string
      variables?: string[]
      disabled?: boolean
    }>(),
    { variables: () => [], disabled: false },
  )
  const emit = defineEmits<{ 'update:model-value': [value: string] }>()
  const editor = useEditor({
    content: props.modelValue,
    extensions: [
      StarterKit.configure({
        heading: { levels: [2, 3] },
        code: false,
        codeBlock: false,
        horizontalRule: false,
        link: { openOnClick: false, autolink: false },
      }),
    ],
    editable: !props.disabled,
    editorProps: {
      attributes: {
        'role': 'textbox',
        'aria-label': 'Email body',
        'aria-multiline': 'true',
      },
    },
    onUpdate: ({ editor }) => emit('update:model-value', editor.getHTML()),
  })
  watch(
    () => props.modelValue,
    value => {
      if (editor.value && editor.value.getHTML() !== value)
        editor.value.commands.setContent(value, { emitUpdate: false })
    },
  )
  watch(
    () => props.disabled,
    value => editor.value?.setEditable(!value),
  )
  onBeforeUnmount(() => editor.value?.destroy())
  const tools = [
    {
      label: 'Bold',
      icon: 'mdi-format-bold',
      active: () => editor.value?.isActive('bold'),
      run: () => editor.value?.chain().focus().toggleBold().run(),
    },
    {
      label: 'Italic',
      icon: 'mdi-format-italic',
      active: () => editor.value?.isActive('italic'),
      run: () => editor.value?.chain().focus().toggleItalic().run(),
    },
    {
      label: 'Underline',
      icon: 'mdi-format-underline',
      active: () => editor.value?.isActive('underline'),
      run: () => editor.value?.chain().focus().toggleUnderline().run(),
    },
    {
      label: 'Heading',
      icon: 'mdi-format-header-2',
      active: () => editor.value?.isActive('heading', { level: 2 }),
      run: () =>
        editor.value?.chain().focus().toggleHeading({ level: 2 }).run(),
    },
    {
      label: 'Bullet list',
      icon: 'mdi-format-list-bulleted',
      active: () => editor.value?.isActive('bulletList'),
      run: () => editor.value?.chain().focus().toggleBulletList().run(),
    },
    {
      label: 'Numbered list',
      icon: 'mdi-format-list-numbered',
      active: () => editor.value?.isActive('orderedList'),
      run: () => editor.value?.chain().focus().toggleOrderedList().run(),
    },
  ]
  function insertVariable (name: string) {
    editor.value?.chain().focus().insertContent(`{{${name}}}`).run()
  }
  const linkOpen = ref(false),
        link = ref('')
  const validLink = computed(() =>
    /^(?:https?:\/\/|mailto:)[^\s<>"{}]+$/i.test(link.value),
  )
  function openLink () {
    link.value = editor.value?.getAttributes('link').href || ''
    linkOpen.value = true
  }
  function applyLink () {
    if (!validLink.value) return
    editor.value
      ?.chain()
      .focus()
      .extendMarkRange('link')
      .setLink({ href: link.value })
      .run()
    linkOpen.value = false
  }
  function removeLink () {
    editor.value?.chain().focus().extendMarkRange('link').unsetLink().run()
    linkOpen.value = false
  }
</script>
<style scoped>
  .rich-editor {
    border: 1px solid #dce5df;
    border-radius: 16px;
    overflow: hidden;
    background: white;
  }
  .rich-editor:focus-within {
    border-color: #568a71;
    box-shadow: 0 0 0 3px #568a7114;
  }
  .rich-editor__toolbar {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 3px;
    padding: 10px;
    border-bottom: 1px solid #e6ece7;
    background: #f7f9f6;
  }
  .rich-editor__separator {
    height: 24px;
    border-left: 1px solid #dce5df;
    margin: 0 6px;
  }
  .rich-editor :deep(.tiptap) {
    min-height: 280px;
    padding: 24px;
    outline: none;
    line-height: 1.7;
    color: #253f35;
    overflow-wrap: anywhere;
  }
  .rich-editor :deep(.tiptap p) {
    margin-bottom: 12px;
  }
  .rich-editor :deep(.tiptap ul),
  .rich-editor :deep(.tiptap ol) {
    padding-left: 24px;
  }
  .rich-editor :deep(.tiptap a) {
    color: #21694f;
    text-decoration: underline;
  }
  .rich-editor__footer {
    padding: 10px 16px;
    border-top: 1px solid #eef1ec;
    color: #6c7d72;
    font-size: 13px;
  }
  .rich-editor--disabled {
    opacity: 0.75;
  }
</style>
