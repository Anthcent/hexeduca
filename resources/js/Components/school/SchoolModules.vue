<script setup>
import { AlertCircle, ArrowRight, BarChart3, CalendarClock, CheckCircle2, ChevronLeft, ChevronRight, ClipboardCheck, CloudDownload, FileCheck2, FileText, Files, Megaphone, MoreHorizontal, PencilLine, Plus, Send, Sparkles, Users } from 'lucide-vue-next'
import UiBadge from '../UiBadge.vue'
import UiButton from '../UiButton.vue'

defineProps({ active: { type: String, required: true } })
defineEmits(['navigate'])

const documentStats = [
  { l: 'Solicitudes activas', v: '18', m: '+4 hoy', c: 'bg-sky-100 text-sky-700', i: Files },
  { l: 'Por validar', v: '7', m: 'Prioridad alta', c: 'bg-amber-100 text-amber-700', i: FileCheck2 },
  { l: 'Listos para firma', v: '5', m: 'Antes de las 3:00', c: 'bg-violet-100 text-violet-700', i: PencilLine },
  { l: 'Emitidos este mes', v: '84', m: '+16% vs. agosto', c: 'bg-emerald-100 text-emerald-700', i: CheckCircle2 },
]
const documents = [
  { id: 'DOC-0284', name: 'Constancia de estudios', student: 'Sofía Rojas', state: 'En revisión', tone: 'brand' },
  { id: 'DOC-0283', name: 'Certificación de notas', student: 'Diego Martínez', state: 'Por validar', tone: 'warning' },
  { id: 'DOC-0282', name: 'Carta de conducta', student: 'Valentina Acosta', state: 'Lista para firma', tone: 'violet' },
  { id: 'DOC-0281', name: 'Constancia de inscripción', student: 'José Pérez', state: 'Emitida', tone: 'success' },
]
const week = [
  { d: 'Lunes 14', items: [['07:30', 'Matemática', 'bg-sky-500'], ['09:20', 'Castellano', 'bg-violet-500'], ['11:10', 'Biología', 'bg-emerald-500']] },
  { d: 'Martes 15', items: [['07:30', 'Historia', 'bg-amber-500'], ['09:20', 'Inglés', 'bg-rose-500'], ['11:10', 'Educación física', 'bg-cyan-500']] },
  { d: 'Miércoles 16', items: [['07:30', 'Matemática', 'bg-sky-500'], ['09:20', 'Química', 'bg-fuchsia-500']] },
  { d: 'Jueves 17', items: [['07:30', 'Geografía', 'bg-orange-500'], ['09:20', 'Castellano', 'bg-violet-500'], ['11:10', 'Proyecto', 'bg-emerald-500']] },
  { d: 'Viernes 18', items: [['07:30', 'Física', 'bg-indigo-500'], ['09:20', 'Orientación', 'bg-teal-500']] },
]
const channels = [
  { n: '5.º “A” · Representantes', m: '128 miembros', u: 4, c: 'bg-emerald-500' },
  { n: 'Personal docente', m: '34 miembros', u: 1, c: 'bg-violet-500' },
  { n: 'Coordinación académica', m: '8 miembros', u: 0, c: 'bg-sky-500' },
  { n: 'Comunidad escolar', m: '512 miembros', u: 0, c: 'bg-amber-500' },
]
const posts = [
  { t: 'Reunión de representantes', d: 'La reunión del primer momento será este viernes a las 2:00 p. m. en el auditorio.', a: 'Coordinación', time: 'Hace 18 min', tone: 'border-l-brand-500' },
  { t: 'Horario de evaluaciones', d: 'Ya está disponible el cronograma actualizado de evaluaciones de la próxima semana.', a: 'Control de Estudio', time: 'Ayer, 4:20 p. m.', tone: 'border-l-violet-500' },
  { t: 'Jornada deportiva', d: 'Los estudiantes deben asistir con el uniforme deportivo y su hidratación personal.', a: 'Bienestar estudiantil', time: '16 sep', tone: 'border-l-amber-500' },
]
</script>

<template>
  <section v-if="active === 'documents'" class="space-y-5 animate-float-in">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <article v-for="item in documentStats" :key="item.l" class="section-card p-4 sm:p-5">
        <div class="flex items-start justify-between"><div><p class="muted text-sm font-semibold">{{ item.l }}</p><p class="mt-1 font-display text-3xl font-extrabold">{{ item.v }}</p><p class="mt-1 text-xs font-bold text-brand-600">{{ item.m }}</p></div><span class="grid size-10 place-items-center rounded-2xl" :class="item.c"><component :is="item.i" class="size-5" /></span></div>
      </article>
    </div>
    <div class="grid gap-5 xl:grid-cols-[1.25fr_.75fr]">
      <div class="section-card overflow-hidden">
        <div class="flex flex-col gap-3 border-b p-5 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="font-display text-lg font-extrabold">Bandeja documental</h2><p class="muted mt-1 text-sm">Seguimiento y emisión sin salir de la bandeja.</p></div><UiButton variant="info" size="sm"><template #icon><Plus class="size-4" /></template>Nueva solicitud</UiButton></div>
        <div class="divide-y"><button v-for="doc in documents" :key="doc.id" class="flex w-full items-center gap-3 p-4 text-left transition hover:bg-[rgb(var(--surface-muted))]/60" @click="$emit('navigate', 'tracking')"><span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-700 dark:bg-brand-950"><FileText class="size-[18px]" /></span><span class="min-w-0 flex-1"><strong class="block truncate text-sm">{{ doc.name }}</strong><span class="muted mt-0.5 block truncate text-xs">{{ doc.id }} · {{ doc.student }}</span></span><UiBadge :tone="doc.tone" dot>{{ doc.state }}</UiBadge><ChevronRight class="muted hidden size-4 sm:block" /></button></div>
      </div>
      <aside class="rounded-[28px] bg-brand-950 p-5 text-white shadow-lift"><span class="grid size-11 place-items-center rounded-2xl bg-white/10"><Sparkles class="size-5 text-brand-200" /></span><h2 class="mt-5 font-display text-xl font-extrabold">Generación rápida</h2><p class="mt-2 text-sm leading-relaxed text-brand-200">Crea documentos frecuentes con el contexto académico ya aplicado.</p><div class="mt-5 space-y-2"><button v-for="item in ['Constancia de estudios','Constancia de inscripción','Resumen de calificaciones']" :key="item" class="flex w-full items-center justify-between rounded-2xl bg-white/[.07] px-3.5 py-3 text-left text-sm font-bold transition hover:bg-white/13">{{ item }}<ArrowRight class="size-4 text-brand-200" /></button></div></aside>
    </div>
  </section>

  <section v-else-if="active === 'schedule'" class="space-y-5 animate-float-in">
    <div class="section-card p-3 sm:p-4"><div class="flex flex-col gap-3 sm:flex-row sm:items-center"><div class="flex items-center gap-3"><button class="grid size-9 place-items-center rounded-xl border"><ChevronLeft class="size-4" /></button><div><h2 class="font-display font-extrabold">Semana 14–18 de septiembre</h2><p class="muted text-xs">5.º año · Sección “A”</p></div><button class="grid size-9 place-items-center rounded-xl border"><ChevronRight class="size-4" /></button></div><div class="flex gap-2 sm:ml-auto"><UiButton variant="secondary" size="sm">Hoy</UiButton><UiButton variant="violet" size="sm"><template #icon><Plus class="size-4" /></template>Nuevo bloque</UiButton></div></div></div>
    <div class="grid gap-3 lg:grid-cols-5"><article v-for="(day,index) in week" :key="day.d" class="section-card p-3" :class="index===4&&'ring-2 ring-brand-400/40'"><div class="flex items-center justify-between px-1 pb-3"><h3 class="text-sm font-extrabold">{{ day.d }}</h3><UiBadge v-if="index===4" tone="success">Hoy</UiBadge></div><div class="space-y-2"><button v-for="item in day.items" :key="item[0]+item[1]" class="w-full rounded-2xl border p-3 text-left transition hover:-translate-y-0.5 hover:shadow-soft"><span class="flex items-center gap-2 text-xs font-bold"><i class="size-2 rounded-full" :class="item[2]" />{{ item[0] }}</span><strong class="mt-2 block text-sm">{{ item[1] }}</strong><span class="muted mt-1 block text-xs">Aula 12 · 90 min</span></button></div></article></div>
  </section>

  <section v-else-if="active === 'communications'" class="grid gap-5 xl:grid-cols-[.8fr_1.2fr] animate-float-in">
    <aside class="section-card p-5"><div class="flex items-center justify-between"><div><h2 class="font-display text-lg font-extrabold">Canales</h2><p class="muted mt-1 text-sm">Comunicación segmentada.</p></div><UiButton variant="coral" size="sm" icon-only aria-label="Crear anuncio"><Plus class="size-4" /></UiButton></div><div class="mt-5 space-y-2"><button v-for="(channel,index) in channels" :key="channel.n" class="flex w-full items-center gap-3 rounded-2xl p-3 text-left transition" :class="index===0?'bg-brand-50 dark:bg-brand-950/50':'hover:bg-[rgb(var(--surface-muted))]'" ><span class="size-3 rounded-full" :class="channel.c" /><span class="min-w-0 flex-1"><strong class="block truncate text-sm">{{ channel.n }}</strong><span class="muted text-xs">{{ channel.m }}</span></span><span v-if="channel.u" class="grid size-6 place-items-center rounded-full bg-rose-500 text-[10px] font-extrabold text-white">{{ channel.u }}</span></button></div></aside>
    <div class="section-card overflow-hidden"><div class="flex items-center gap-3 border-b p-4 sm:p-5"><span class="grid size-10 place-items-center rounded-2xl bg-brand-950 text-white"><Megaphone class="size-5" /></span><div class="min-w-0"><h2 class="truncate font-display font-extrabold">5.º “A” · Representantes</h2><p class="muted text-xs">128 miembros · Solo administradores publican</p></div><UiButton class="ml-auto" variant="coral" size="sm"><template #icon><Send class="size-4" /></template><span class="hidden sm:inline">Nuevo aviso</span></UiButton></div><div class="space-y-3 p-4 sm:p-5"><article v-for="post in posts" :key="post.t" class="rounded-2xl border border-l-4 p-4" :class="post.tone"><div class="flex items-start justify-between gap-3"><h3 class="text-sm font-extrabold">{{ post.t }}</h3><button class="muted"><MoreHorizontal class="size-4" /></button></div><p class="muted mt-2 text-sm leading-relaxed">{{ post.d }}</p><p class="mt-3 text-xs font-bold text-brand-600">{{ post.a }} · {{ post.time }}</p></article></div></div>
  </section>

  <section v-else class="space-y-5 animate-float-in">
    <div class="grid gap-4 md:grid-cols-3"><article v-for="item in [{l:'Rendimiento general',v:'16,8',m:'+0,7 este momento',tone:'from-brand-700 to-brand-950'},{l:'Asistencia acumulada',v:'93,4%',m:'+1,2% vs. período anterior',tone:'from-sky-600 to-indigo-800'},{l:'Riesgo académico',v:'12',m:'3 requieren intervención',tone:'from-amber-500 to-orange-700'}]" :key="item.l" class="relative overflow-hidden rounded-[26px] bg-linear-to-br p-5 text-white shadow-lg" :class="item.tone"><span class="absolute -right-6 -top-8 size-28 rounded-full border-22 border-white/10" /><p class="relative text-sm font-semibold text-white/75">{{ item.l }}</p><p class="relative mt-2 font-display text-3xl font-extrabold">{{ item.v }}</p><p class="relative mt-2 text-xs font-bold text-white/80">{{ item.m }}</p></article></div>
    <div class="grid gap-5 xl:grid-cols-[1.25fr_.75fr]"><div class="section-card p-5 sm:p-6"><div class="flex items-center justify-between"><div><h2 class="font-display text-lg font-extrabold">Comparativo por sección</h2><p class="muted mt-1 text-sm">Promedio académico del 1.er momento.</p></div><UiButton variant="secondary" size="sm"><template #icon><CloudDownload class="size-4" /></template>Exportar</UiButton></div><div class="mt-7 space-y-5"><div v-for="item in [{l:'5.º A',v:91,c:'bg-brand-500'},{l:'5.º B',v:84,c:'bg-sky-500'},{l:'4.º A',v:78,c:'bg-violet-500'},{l:'4.º B',v:71,c:'bg-amber-500'},{l:'3.º A',v:66,c:'bg-rose-500'}]" :key="item.l" class="grid grid-cols-[52px_1fr_40px] items-center gap-3"><span class="text-sm font-bold">{{ item.l }}</span><span class="h-3 overflow-hidden rounded-full bg-[rgb(var(--surface-muted))]"><i class="block h-full rounded-full" :class="item.c" :style="{width:item.v+'%'}" /></span><strong class="text-right text-sm">{{ item.v }}%</strong></div></div></div><aside class="section-card p-5"><h2 class="font-display text-lg font-extrabold">Reportes frecuentes</h2><div class="mt-4 space-y-2"><button v-for="item in [{t:'Cierre del momento',d:'Notas, asistencia e incidencias',i:ClipboardCheck,c:'text-brand-600 bg-brand-50'},{t:'Riesgo académico',d:'Alertas y planes de intervención',i:AlertCircle,c:'text-amber-700 bg-amber-50'},{t:'Matrícula por sección',d:'Altas, retiros y traslados',i:Users,c:'text-sky-700 bg-sky-50'}]" :key="item.t" class="flex w-full items-center gap-3 rounded-2xl border p-3 text-left transition hover:border-brand-300 hover:shadow-soft"><span class="grid size-10 place-items-center rounded-xl" :class="item.c"><component :is="item.i" class="size-[18px]" /></span><span class="min-w-0 flex-1"><strong class="block text-sm">{{ item.t }}</strong><span class="muted block truncate text-xs">{{ item.d }}</span></span><ChevronRight class="muted size-4" /></button></div></aside></div>
  </section>
</template>
