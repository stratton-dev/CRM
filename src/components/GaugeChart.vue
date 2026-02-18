<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  value: number
  min?: number
  max?: number
  segments?: { color: string; from: number; to: number }[]
  label?: string
  sublabel?: string
}>()

const width = 600
const height = 350
const cx = 300
const cy = 300
const r = 240
const strokeWidth = 70

const minValue = computed(() => props.min ?? 0)
const maxValue = computed(() => props.max ?? 100)

const defaultSegments = computed(() => {
  if (props.segments) return props.segments
  const range = maxValue.value - minValue.value
  const step = range / 4
  return [
    { color: '#ef4444', from: minValue.value, to: minValue.value + step }, // Red
    { color: '#f97316', from: minValue.value + step, to: minValue.value + step * 2 }, // Orange
    { color: '#eab308', from: minValue.value + step * 2, to: minValue.value + step * 3 }, // Yellow
    { color: '#22c55e', from: minValue.value + step * 3, to: maxValue.value }, // Green
  ]
})

const valueToAngle = (val: number) => {
  const range = maxValue.value - minValue.value
  const safeVal = Math.max(minValue.value, Math.min(maxValue.value, val))
  const percentage = (safeVal - minValue.value) / range
  // We want -180 (Left) to 0 (Right) clockwise
  // Wait, standard SVG circle: 0 is Right, 90 is Down, 180 is Left, 270 is Up.
  // We want to draw arc from 180 (Left) -> 270 (Top) -> 360/0 (Right).
  return 180 + (percentage * 180)
}

const polarToCartesian = (centerX: number, centerY: number, radius: number, angleInDegrees: number) => {
  const angleInRadians = (angleInDegrees) * Math.PI / 180.0
  return {
    x: centerX + (radius * Math.cos(angleInRadians)),
    y: centerY + (radius * Math.sin(angleInRadians))
  }
}

const describeArc = (x: number, y: number, radius: number, startAngle: number, endAngle: number) => {
    const start = polarToCartesian(x, y, radius, endAngle)
    const end = polarToCartesian(x, y, radius, startAngle)
    const largeArcFlag = endAngle - startAngle <= 180 ? "0" : "1"
    
    // SVG Path A rx ry x-axis-rotation large-arc-flag sweep-flag x y
    // We sweep from startAngle to endAngle clockwise?
    // If startAngle=180 (Left) and endAngle=225 (Left-Top)
    // We want to Go from (Left) to (Left-Top). clockwise.
    return [
        "M", start.x, start.y, 
        "A", radius, radius, 0, largeArcFlag, 0, end.x, end.y
    ].join(" ")
}

// Fixed Needle Rotation logic
const needleRotation = computed(() => {
    // 0% -> 180 deg (Left)
    // 100% -> 360 deg (Right)
    const range = maxValue.value - minValue.value
    const val = Math.max(minValue.value, Math.min(maxValue.value, props.value))
    const pct = (val - minValue.value) / range
    return 180 + (pct * 180)
})


const sublabelColor = computed(() => {
    if (props.value >= 100) return '#22c55e' // Green
    return '#ef4444' // Red
})

</script>

<template>
  <div class="flex flex-col items-center justify-center p-2 w-full h-full">
    <svg :viewBox="`0 0 ${width} ${height}`" class="w-full h-full overflow-visible">
        <defs>
            <filter id="shadow" x="-50%" y="-50%" width="200%" height="200%">
              <feDropShadow dx="1" dy="2" stdDeviation="2" flood-color="#000000" flood-opacity="0.3" />
            </filter>
        </defs>

        <!-- Segments -->
        <!-- Note: SVG Arc drawing order matters for "move to" command if we want continuous? No, individual paths are fine -->
        <path
            v-for="(seg, i) in defaultSegments"
            :key="i"
            :d="describeArc(cx, cy, r, valueToAngle(seg.from), valueToAngle(seg.to))"
            fill="none"
            :stroke="seg.color"
            :stroke-width="strokeWidth"
            stroke-linecap="butt"
        />
        
        <!-- White Separators -->
        <path 
            v-for="i in 3"
            :key="`sep-${i}`"
            :d="describeArc(cx, cy, r, 180 + (i * 45) - 0.5, 180 + (i * 45) + 0.5)"
            stroke="white"
            :stroke-width="strokeWidth + 2"
            fill="none"
        />

        <!-- Needle -->
        <!-- Needle points to 0 deg (Right) by default geometry. We rotate it. -->
        <!-- Tip at (cx + r - 10, cy). Base at cx, cy. -->
        <g :transform="`rotate(${needleRotation}, ${cx}, ${cy})`" filter="url(#shadow)">
             <!-- Draws a needle pointing to Right (0 deg) -->
             <!-- Then we rotate it. 180 deg puts it Left. 270 Up. 360 Right. -->
            <path :d="`M ${cx} ${cy - 8} L ${cx + r - 20} ${cy} L ${cx} ${cy + 8} Z`" fill="#000000" />
            <circle :cx="cx" :cy="cy" r="12" fill="#000000" />
        </g>
        
        <!-- Value Label -->
        <text :x="cx" :y="cy + 70" text-anchor="middle" class="text-6xl font-black fill-slate-900 font-serif tracking-tight" style="font-size: 64px;">
            {{ label }}
        </text>

         <!-- Sub Label -->
        <text v-if="sublabel" :x="cx" :y="cy + 110" text-anchor="middle" class="uppercase font-bold" :style="{ fontSize: '24px', letterSpacing: '1.5px', fill: sublabelColor }">
            {{ sublabel }}
        </text>
        
        <!-- Tick Labels (Optional, simple formatting) -->
    </svg>
  </div>
</template>
