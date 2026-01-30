<template>
  <div class="space-y-1.5">
    <label
      v-if="label"
      class="block text-sm font-medium text-slate-700"
    >
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>
    <Popover v-slot="{ open, close }" class="relative block">
      <div class="hidden" :data-close="(closeRef = close)"></div>
      <PopoverButton
        type="button"
        class="input-base w-full flex items-center justify-between text-left"
        :class="[open ? 'ring-2 ring-blue-500 border-blue-500' : '']"
      >
        <span :class="displayText ? 'text-slate-800' : 'text-slate-400'">
          {{ displayText || placeholder }}
        </span>
        <CalendarDaysIcon class="h-5 w-5 shrink-0 text-slate-400" />
      </PopoverButton>
      <PopoverPanel
        class="absolute right-0 z-50 mt-1 w-auto min-w-max rounded-xl border border-slate-200 bg-white p-4 shadow-lg"
        @mouseleave="hoveredDay = null"
      >
        <div class="flex items-center justify-between mb-4">
          <button
            v-if="tempStart || tempEnd"
            type="button"
            class="text-xs text-slate-500 hover:text-slate-700"
            @click="clearSelection"
          >
            Limpar
          </button>
        </div>
        <div class="flex gap-8">
          <!-- Mês 1 -->
          <div class="flex flex-col">
            <div class="flex items-center justify-between mb-2">
              <button
                type="button"
                class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-600"
                @click="prevMonth"
              >
                <ChevronLeftIcon class="h-5 w-5" />
              </button>
              <span class="text-sm font-medium text-slate-800">{{ monthLabel(calendarStart) }}</span>
              <button
                type="button"
                class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-600"
                @click="nextMonth"
              >
                <ChevronRightIcon class="h-5 w-5" />
              </button>
            </div>
            <div class="grid grid-cols-7 gap-0.5 text-center">
              <div
                v-for="w in weekDays"
                :key="w"
                class="text-xs font-medium text-slate-500 py-1"
              >
                {{ w }}
              </div>
              <button
                v-for="(day, idx) in calendarDays"
                :key="idx"
                type="button"
                class="w-9 h-9 rounded-lg text-sm transition-colors"
                :class="dayClasses(day, calendarStart)"
                :disabled="isDisabled(day, calendarStart)"
                @click="onDayClick(day)"
                @mouseenter="hoveredDay = day"
                @mouseleave="hoveredDay = null"
              >
                {{ day.getDate() }}
              </button>
            </div>
          </div>
          <!-- Mês 2 -->
          <div class="flex flex-col">
            <span class="text-sm font-medium text-slate-800 mb-2 mt-9">{{ monthLabel(calendarStartNext) }}</span>
            <div class="grid grid-cols-7 gap-0.5 text-center">
              <div
                v-for="w in weekDays"
                :key="'2-' + w"
                class="text-xs font-medium text-slate-500 py-1"
              >
                {{ w }}
              </div>
              <button
                v-for="(day, idx) in calendarDaysNext"
                :key="'n-' + idx"
                type="button"
                class="w-9 h-9 rounded-lg text-sm transition-colors"
                :class="dayClasses(day, calendarStartNext)"
                :disabled="isDisabled(day, calendarStartNext)"
                @click="onDayClick(day)"
                @mouseenter="hoveredDay = day"
                @mouseleave="hoveredDay = null"
              >
                {{ day.getDate() }}
              </button>
            </div>
          </div>
        </div>
        <p class="mt-3 text-xs text-slate-500">Selecione a data de saída e depois a data de retorno.</p>
      </PopoverPanel>
    </Popover>
    <p
      v-if="error"
      class="text-xs text-red-600"
    >
      {{ error }}
    </p>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'
import {
  format,
  startOfMonth,
  endOfMonth,
  eachDayOfInterval,
  addMonths,
  subMonths,
  isSameDay,
  isWithinInterval,
  isBefore,
  parseISO,
  startOfWeek,
  endOfWeek,
  isSameMonth,
  startOfDay,
} from 'date-fns'
import { ptBR } from 'date-fns/locale'
import { CalendarDaysIcon, ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  label: { type: String, default: '' },
  departureDate: { type: String, default: '' },
  returnDate: { type: String, default: '' },
  placeholder: { type: String, default: 'Selecione o período' },
  required: { type: Boolean, default: false },
  error: { type: String, default: '' },
  minDate: { type: [String, Date], default: null },
})

const emit = defineEmits(['update:departureDate', 'update:returnDate'])

let closeRef = null
const weekDays = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S']

const calendarStart = ref(new Date())
const tempStart = ref(null)
const tempEnd = ref(null)
const hoveredDay = ref(null)

const minDateObj = computed(() => {
  if (props.minDate) {
    return typeof props.minDate === 'string' ? parseISO(props.minDate) : props.minDate
  }
  return startOfDay(new Date())
})

function monthLabel(d) {
  return format(d, 'MMMM yyyy', { locale: ptBR })
}

const calendarStartNext = computed(() => addMonths(calendarStart.value, 1))

function buildCalendarDays(monthStart) {
  const start = startOfWeek(startOfMonth(monthStart), { weekStartsOn: 0 })
  const end = endOfWeek(endOfMonth(monthStart), { weekStartsOn: 0 })
  return eachDayOfInterval({ start, end })
}

const calendarDays = computed(() => buildCalendarDays(calendarStart.value))
const calendarDaysNext = computed(() => buildCalendarDays(calendarStartNext.value))

const displayText = computed(() => {
  const start = props.departureDate ? parseISO(props.departureDate) : null
  const end = props.returnDate ? parseISO(props.returnDate) : null
  if (!start || !end) return ''
  const sameYear = start.getFullYear() === end.getFullYear()
  if (sameYear) {
    return `${format(start, 'd \'de\' MMM', { locale: ptBR })} – ${format(end, 'd \'de\' MMM. yyyy', { locale: ptBR })}`
  }
  return `${format(start, 'd \'de\' MMM. yyyy', { locale: ptBR })} – ${format(end, 'd \'de\' MMM. yyyy', { locale: ptBR })}`
})

watch(
  () => [props.departureDate, props.returnDate],
  ([dep, ret]) => {
    if (dep) tempStart.value = parseISO(dep)
    else tempStart.value = null
    if (ret) tempEnd.value = parseISO(ret)
    else tempEnd.value = null
  },
  { immediate: true }
)

function isDisabled(day, month) {
  if (!isSameMonth(day, month)) return true
  if (minDateObj.value && isBefore(day, minDateObj.value)) return true
  return false
}

function dayClasses(day, month) {
  if (!isSameMonth(day, month)) {
    return 'text-slate-300 cursor-default'
  }
  if (minDateObj.value && isBefore(day, minDateObj.value)) {
    return 'text-slate-300 cursor-not-allowed'
  }
  const startRaw = tempStart.value
  const endRaw = tempEnd.value
  const hovered = hoveredDay.value
  if (!startRaw && !endRaw) {
    return 'hover:bg-slate-100 text-slate-800'
  }
  const dayStart = startOfDay(day)
  const start = startRaw ? startOfDay(startRaw) : null
  const canUseHover =
    hovered && start && !isBefore(hovered, startRaw) &&
    (!minDateObj.value || !isBefore(hovered, minDateObj.value))
  const endComputed =
    endRaw ? startOfDay(endRaw) : canUseHover ? startOfDay(hovered) : null
  const end = endComputed
  const hasRange = start && end && !isBefore(end, start)
  const inRange =
    hasRange &&
    (isWithinInterval(dayStart, { start, end }) ||
      isSameDay(dayStart, start) ||
      isSameDay(dayStart, end))
  const isStart = start && isSameDay(dayStart, start)
  const isEnd = end && isSameDay(dayStart, end)
  if (isStart || isEnd) {
    return 'bg-blue-600 text-white font-medium hover:bg-blue-700'
  }
  if (inRange) {
    return 'bg-blue-50 text-blue-700 hover:bg-blue-100'
  }
  return 'hover:bg-slate-100 text-slate-800'
}

function onDayClick(day) {
  if (!tempStart.value || (tempStart.value && tempEnd.value)) {
    tempStart.value = day
    tempEnd.value = null
    emit('update:departureDate', format(day, 'yyyy-MM-dd'))
    emit('update:returnDate', '')
    return
  }
  if (isBefore(day, tempStart.value)) {
    tempEnd.value = tempStart.value
    tempStart.value = day
  } else {
    tempEnd.value = day
  }
  emit('update:departureDate', format(tempStart.value, 'yyyy-MM-dd'))
  emit('update:returnDate', format(tempEnd.value, 'yyyy-MM-dd'))
  if (typeof closeRef === 'function') closeRef()
}

function clearSelection() {
  tempStart.value = null
  tempEnd.value = null
  emit('update:departureDate', '')
  emit('update:returnDate', '')
}

function prevMonth() {
  calendarStart.value = subMonths(calendarStart.value, 1)
}

function nextMonth() {
  calendarStart.value = addMonths(calendarStart.value, 1)
}
</script>
