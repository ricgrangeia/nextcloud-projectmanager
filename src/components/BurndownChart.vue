<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
	days: { type: Array, required: true },
	doneSeries: { type: Array, required: true },
	estimatedH: { type: Number, required: true },
	doneLabel: { type: String, required: true },
	estimatedLabel: { type: String, required: true },
})

const WIDTH = 600
const HEIGHT = 180
const PAD_LEFT = 42
const PAD_RIGHT = 10
const PAD_TOP = 10
const PAD_BOTTOM = 20

const wrapperRef = ref(null)
const hovered = ref(null)

const maxY = computed(() => Math.max(props.estimatedH, ...props.doneSeries, 1))

function x(i) {
	const n = props.days.length
	if (n <= 1) {
		return PAD_LEFT
	}
	return PAD_LEFT + (i / (n - 1)) * (WIDTH - PAD_LEFT - PAD_RIGHT)
}

function y(value) {
	const h = HEIGHT - PAD_TOP - PAD_BOTTOM
	return PAD_TOP + h - (value / maxY.value) * h
}

const donePoints = computed(() => props.doneSeries.map((v, i) => `${x(i)},${y(v)}`).join(' '))
const estimatedY = computed(() => y(props.estimatedH))
const firstDay = computed(() => props.days[0])
const lastDay = computed(() => props.days[props.days.length - 1])

function onPointHover(i, event) {
	const rect = wrapperRef.value.getBoundingClientRect()
	hovered.value = {
		index: i,
		x: event.clientX - rect.left,
		y: event.clientY - rect.top,
	}
}

function onPointLeave() {
	hovered.value = null
}
</script>

<template>
	<div ref="wrapperRef" class="burndown">
		<svg :viewBox="`0 0 ${WIDTH} ${HEIGHT}`" preserveAspectRatio="none" class="burndown-svg" @mouseleave="onPointLeave">
			<line :x1="PAD_LEFT" :y1="PAD_TOP" :x2="PAD_LEFT" :y2="HEIGHT - PAD_BOTTOM" class="axis-line" />
			<line :x1="PAD_LEFT" :y1="HEIGHT - PAD_BOTTOM" :x2="WIDTH - PAD_RIGHT" :y2="HEIGHT - PAD_BOTTOM" class="axis-line" />

			<line :x1="PAD_LEFT" :y1="estimatedY" :x2="WIDTH - PAD_RIGHT" :y2="estimatedY" class="estimated-line" />

			<polyline :points="donePoints" fill="none" class="done-line" />
			<circle v-for="(v, i) in doneSeries" :key="'dot-' + i" :cx="x(i)" :cy="y(v)" r="2.5" class="done-dot" :class="{ 'done-dot-active': hovered && hovered.index === i }" />
			<circle
				v-for="(v, i) in doneSeries"
				:key="'hit-' + i"
				:cx="x(i)"
				:cy="y(v)"
				r="9"
				class="done-hit"
				@mouseenter="onPointHover(i, $event)"
				@mousemove="onPointHover(i, $event)" />

			<text :x="4" :y="PAD_TOP + 4" class="axis-label">{{ maxY.toFixed(0) }}h</text>
			<text :x="4" :y="HEIGHT - PAD_BOTTOM" class="axis-label">0h</text>
			<text :x="PAD_LEFT" :y="HEIGHT - 4" class="axis-label">{{ firstDay }}</text>
			<text :x="WIDTH - PAD_RIGHT" :y="HEIGHT - 4" text-anchor="end" class="axis-label">{{ lastDay }}</text>
		</svg>
		<div v-if="hovered" class="burndown-tooltip" :style="{ left: hovered.x + 'px', top: hovered.y + 'px' }">
			<strong>{{ days[hovered.index] }}</strong>
			<span>{{ doneSeries[hovered.index].toFixed(2) }}h</span>
		</div>
		<div class="burndown-legend">
			<span class="legend-item"><span class="legend-swatch legend-swatch-done" /> {{ doneLabel }}</span>
			<span class="legend-item"><span class="legend-swatch legend-swatch-estimated" /> {{ estimatedLabel }}</span>
		</div>
	</div>
</template>

<style scoped>
.burndown {
	position: relative;
	margin-top: 12px;
}

.burndown-svg {
	width: 100%;
	height: 180px;
	display: block;
}

.axis-line {
	stroke: var(--color-border);
	stroke-width: 1;
}

.estimated-line {
	stroke: var(--color-warning, orange);
	stroke-width: 1.5;
	stroke-dasharray: 4 3;
}

.done-line {
	stroke: var(--color-primary-element);
	stroke-width: 2;
}

.done-dot {
	fill: var(--color-primary-element);
	pointer-events: none;
}

.done-dot-active {
	r: 4;
	stroke: var(--color-main-background);
	stroke-width: 1.5;
}

.done-hit {
	fill: transparent;
	pointer-events: all;
	cursor: pointer;
}

.axis-label {
	font-size: 9px;
	fill: var(--color-text-maxcontrast);
}

.burndown-tooltip {
	position: absolute;
	transform: translate(-50%, calc(-100% - 10px));
	display: flex;
	flex-direction: column;
	gap: 2px;
	padding: 6px 10px;
	background-color: var(--color-main-background);
	color: var(--color-main-text);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
	font-size: 12px;
	white-space: nowrap;
	pointer-events: none;
	z-index: 1;
}

.burndown-tooltip strong {
	font-size: 11px;
	color: var(--color-text-maxcontrast);
}

.burndown-legend {
	display: flex;
	gap: 16px;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	margin-top: 4px;
}

.legend-item {
	display: inline-flex;
	align-items: center;
	gap: 4px;
}

.legend-swatch {
	width: 10px;
	height: 10px;
	border-radius: 2px;
	display: inline-block;
}

.legend-swatch-done {
	background: var(--color-primary-element);
}

.legend-swatch-estimated {
	background: var(--color-warning, orange);
}
</style>
