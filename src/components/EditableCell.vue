<script setup>
import { ref, nextTick } from 'vue'

const props = defineProps({
	modelValue: { type: [String, Number, null], default: '' },
	type: { type: String, default: 'text' },
	placeholder: { type: String, default: '—' },
})

const emit = defineEmits(['save'])

const editing = ref(false)
const draft = ref('')
const inputEl = ref(null)

async function startEdit() {
	draft.value = props.modelValue ?? ''
	editing.value = true
	await nextTick()
	inputEl.value?.focus()
	inputEl.value?.select()
}

function commit() {
	editing.value = false
	const original = props.modelValue ?? ''
	if (String(draft.value) !== String(original)) {
		emit('save', draft.value)
	}
}

function cancel() {
	editing.value = false
}
</script>

<template>
	<input
		v-if="editing"
		ref="inputEl"
		v-model="draft"
		:type="type"
		class="editable-cell-input"
		@blur="commit"
		@keydown.enter="commit"
		@keydown.esc="cancel">
	<span
		v-else
		class="editable-cell"
		tabindex="0"
		:class="{ 'is-empty': modelValue === null || modelValue === undefined || modelValue === '' }"
		@click="startEdit"
		@keydown.enter="startEdit">
		{{ modelValue !== null && modelValue !== undefined && modelValue !== '' ? modelValue : placeholder }}
	</span>
</template>

<style scoped>
.editable-cell {
	display: inline-block;
	min-width: 24px;
	min-height: 1.2em;
	cursor: text;
	border-radius: var(--border-radius);
	padding: 2px 4px;
	margin: -2px -4px;
}

.editable-cell:hover,
.editable-cell:focus {
	background-color: var(--color-background-hover);
	outline: 1px dashed var(--color-border-dark);
}

.editable-cell.is-empty {
	color: var(--color-text-maxcontrast);
	font-style: italic;
}

.editable-cell-input {
	width: 100%;
	min-width: 60px;
	box-sizing: border-box;
}
</style>
